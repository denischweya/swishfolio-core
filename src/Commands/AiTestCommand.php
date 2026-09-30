<?php
/**
 * WP-CLI command to exercise the AI editing pipeline end to end.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Commands;

use SwishfolioCore\Actions\ActionValidator;
use SwishfolioCore\Actions\ValidationContext;
use SwishfolioCore\Actions\ValidationException;
use SwishfolioCore\AI\AIManager;
use SwishfolioCore\AI\Context\ContextAssembler;
use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\AI\UsageLogger;
use SwishfolioCore\Blocks\BlockRegistry;

/**
 * Runs one real AI edit against a synthetic block selection and prints the
 * validated actions, without opening the editor.
 *
 * This makes a REAL, BILLED request to the configured provider. It is the
 * fastest way to answer "does my key work and is the output any good", and the
 * only way to see prompt quality — the recorded eval fixtures deliberately
 * never touch the network.
 *
 * ## EXAMPLES
 *
 *     # Test the configured provider with a hero edit.
 *     wp swishfolio ai-test "make this feel more premium and tighten the headline"
 *
 *     # Pick a different block.
 *     wp swishfolio ai-test "rewrite this for a professional audience" --block=core/paragraph
 *
 *     # Override the model for one run.
 *     wp swishfolio ai-test "make it bolder" --model=claude-haiku-4-5
 *
 *     # Show the prompt that would be sent, and make no request.
 *     wp swishfolio ai-test "make it bolder" --dry-run
 */
class AiTestCommand
{
    /**
     * Run one AI edit against a synthetic selection.
     *
     * ## OPTIONS
     *
     * <instruction>
     * : The natural-language change to ask for.
     *
     * [--block=<name>]
     * : Block to target. Default swishfolio-core/hero.
     *
     * [--model=<model>]
     * : Override the configured model for this run.
     *
     * [--dry-run]
     * : Print the assembled prompt and exit without calling the provider.
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Flags.
     *
     * @return void
     */
    public function __invoke($args, $assoc_args)
    {
        $instruction = isset($args[0]) ? (string) $args[0] : '';
        $blockName   = isset($assoc_args['block']) ? (string) $assoc_args['block'] : 'swishfolio-core/hero';
        $dryRun      = ! empty($assoc_args['dry-run']);

        if ($instruction === '') {
            \WP_CLI::error('Give an instruction, e.g. wp swishfolio ai-test "make this more premium".');
        }

        $registry   = new BlockRegistry([
            SFCORE_PLUGIN_DIR . 'blocks',
            SFCORE_PLUGIN_DIR . 'src/AI/Manifests/core',
        ]);
        $capability = $registry->get($blockName);

        if ($capability === null) {
            \WP_CLI::error(sprintf('Block "%s" has no AI capability manifest.', $blockName));
        }

        $settings = new Settings(new \SwishfolioCore\Services\EncryptionService());
        $stored   = $settings->get();
        $model    = isset($assoc_args['model']) ? (string) $assoc_args['model'] : $stored['model'];

        $tokens    = TokenResolver::fromTheme();
        $prompts   = new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt');
        $assembler = new ContextAssembler($registry, $tokens, $prompts);

        $selection = $this->syntheticSelection($blockName, $capability);

        if ($dryRun) {
            $request = $assembler->forSelection($selection, $instruction, $model);

            \WP_CLI::log('--- SYSTEM PROMPT (' . strlen($request->getSystemPrompt()) . " chars, cache-stable) ---\n");
            \WP_CLI::log($request->getSystemPrompt());
            \WP_CLI::log("\n--- USER PROMPT (" . strlen($request->getUserPrompt()) . " chars, volatile) ---\n");
            \WP_CLI::log($request->getUserPrompt());
            \WP_CLI::success(sprintf(
                'Dry run. Roughly %d tokens total. No request was made.',
                (int) ((strlen($request->getSystemPrompt()) + strlen($request->getUserPrompt())) / 4)
            ));

            return;
        }

        if (! $stored['hasKey']) {
            \WP_CLI::error('No API key stored. Set one under Swish Forms → AI Settings, or with: wp option patch ...');
        }

        try {
            $provider = (new ProviderRegistry($settings))->configured();
        } catch (ProviderException $e) {
            \WP_CLI::error($e->getMessage());

            return;
        }

        \WP_CLI::log(sprintf(
            'Provider %s, model %s, block %s. Making a real (billed) request…',
            $provider->getSlug(),
            $model,
            $blockName
        ));

        $manager = new AIManager($provider, $assembler, ActionValidator::withDefaultStages(), new UsageLogger());

        $context = new ValidationContext(
            [$selection['clientId'] => [
                'name'           => $blockName,
                'attributes'     => $selection['attributes'],
                'parentClientId' => null,
            ]],
            $selection['clientId'],
            $registry,
            $tokens,
            (int) apply_filters('swishfolio_ai_max_actions', 25)
        );

        $started = microtime(true);

        try {
            $result = $manager->edit($selection, $instruction, $context, $model);
        } catch (ValidationException $e) {
            \WP_CLI::warning(sprintf(
                'The AI replied, but validation rejected it at stage "%s"%s: %s',
                $e->getStage(),
                $e->getPath() === null ? '' : ' (' . $e->getPath() . ')',
                $e->getMessage()
            ));
            \WP_CLI::error('Nothing would have been changed.');

            return;
        } catch (ProviderException $e) {
            \WP_CLI::error(sprintf('Provider error (HTTP %d): %s', $e->getStatusCode(), $e->getMessage()));

            return;
        }

        $elapsed = (int) round((microtime(true) - $started) * 1000);

        \WP_CLI::log("\nSummary: " . $result['summary']);
        \WP_CLI::log('');

        $rows = [];

        foreach ($result['actions'] as $action) {
            if ($action['type'] === 'update_block_attributes') {
                foreach ($action['changes'] as $attribute => $value) {
                    $rows[] = [
                        'field'  => $attribute,
                        'before' => $this->short($selection['attributes'], $attribute),
                        'after'  => $this->stringify($value),
                        'reason' => isset($action['reason']) ? $action['reason'] : '',
                    ];
                }
                continue;
            }

            $rows[] = [
                'field'  => $action['type'],
                'before' => '',
                'after'  => isset($action['content']) ? $this->stringify($action['content']) : '',
                'reason' => isset($action['reason']) ? $action['reason'] : '',
            ];
        }

        if ($rows === []) {
            \WP_CLI::warning('The AI proposed no changes. Its summary above should say why.');
        } else {
            \WP_CLI\Utils\format_items('table', $rows, ['field', 'before', 'after', 'reason']);
        }

        \WP_CLI::success(sprintf('%d action(s) validated in %dms.', count($result['actions']), $elapsed));
    }

    /**
     * A plausible starting state for the block, so "before" is meaningful.
     *
     * @param string                                  $blockName  Block name.
     * @param \SwishfolioCore\Blocks\BlockCapability $capability Its manifest.
     *
     * @return array
     */
    private function syntheticSelection($blockName, $capability)
    {
        $attributes = [];

        foreach ($capability->getFields() as $attribute => $field) {
            if ($field->getGroup() !== 'content') {
                continue;
            }

            if (in_array($field->getType(), ['text', 'richtext'], true)) {
                $attributes[$attribute] = $this->placeholderFor($field->getRole(), $attribute);
            }
        }

        return [
            'clientId'       => 'cli-test-1',
            'blockName'      => $blockName,
            'attributes'     => $attributes,
            'innerBlocks'    => [],
            'parent'         => null,
            'siblings'       => [],
            'postType'       => 'page',
            'styleVariation' => (string) apply_filters('swishfolio_ai_cli_style_variation', 'default'),
        ];
    }

    private function placeholderFor($role, $attribute)
    {
        switch ($role) {
            case 'headline':
                return 'Welcome to our website';
            case 'supporting':
            case 'body':
                return 'We help small businesses grow online with practical, measurable work.';
            case 'primary-cta':
                return 'Get started';
            case 'secondary-cta':
                return 'Learn more';
            case 'eyebrow':
                return 'Our approach';
            default:
                return ucfirst(str_replace('_', ' ', $attribute));
        }
    }

    private function short(array $attributes, $attribute)
    {
        return isset($attributes[$attribute]) ? $this->stringify($attributes[$attribute]) : '(unset)';
    }

    private function stringify($value)
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return wp_json_encode($value);
        }

        $string = (string) $value;

        return strlen($string) > 60 ? substr($string, 0, 57) . '…' : $string;
    }
}
