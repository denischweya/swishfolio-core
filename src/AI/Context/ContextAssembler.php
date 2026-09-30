<?php
/**
 * Builds the AI request for one editor selection.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Context;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\Actions\ActionSchema;
use SwishfolioCore\Blocks\BlockRegistry;

/**
 * Context economy is the point of this class. The selected block goes in whole;
 * its parent, siblings and inner blocks contribute a name and a purpose and
 * nothing else; and only the manifests for block types actually in play are
 * included. Sending the whole registry, or sibling attributes, would multiply
 * prompt size for no gain.
 *
 * Ordering matters too: system prompt, manifests and tokens are byte-stable
 * for a given selection shape and go first, so a provider's prompt cache can
 * hold them. The volatile selection and instruction go in the user message.
 */
class ContextAssembler
{
    /** @var BlockRegistry */
    private $registry;

    /** @var TokenResolver */
    private $tokens;

    /** @var PromptManager */
    private $prompts;

    /** @var array Brand context from Swish AI settings. */
    private $brand;

    /**
     * @param BlockRegistry $registry Capability registry.
     * @param TokenResolver $tokens   Live design tokens.
     * @param PromptManager $prompts  Prompt loader.
     * @param array         $brand    Brand context; empty when unconfigured.
     */
    public function __construct(BlockRegistry $registry, TokenResolver $tokens, PromptManager $prompts, array $brand = [])
    {
        $this->registry = $registry;
        $this->tokens   = $tokens;
        $this->prompts  = $prompts;
        $this->brand    = $brand;
    }

    /**
     * @param array  $selection   Editor selection payload.
     * @param string $instruction The user's natural-language request.
     * @param string $model       Model identifier.
     * @param int    $maxActions  Action cap.
     *
     * @throws \RuntimeException When the selected block has no manifest.
     *
     * @return AIRequest
     */
    public function forSelection(array $selection, $instruction, $model, $maxActions = ActionSchema::DEFAULT_MAX_ACTIONS)
    {
        $capability = $this->registry->get($selection['blockName']);

        if ($capability === null) {
            throw new \RuntimeException(
                sprintf('Block "%s" has no AI capability manifest.', $selection['blockName'])
            );
        }

        return new AIRequest(
            $this->buildSystemPrompt($selection),
            $this->buildUserPrompt($selection, $instruction),
            ActionSchema::build($maxActions),
            $model
        );
    }

    /**
     * Stable prefix: instructions, the manifests in play, the design tokens.
     *
     * @return string
     */
    private function buildSystemPrompt(array $selection)
    {
        $manifests = [];

        foreach ($this->blockTypesInPlay($selection) as $blockName) {
            $capability = $this->registry->get($blockName);

            if ($capability === null) {
                continue;
            }

            $manifests[$blockName] = $this->describeCapability($capability);
        }

        ksort($manifests);

        $tokens = [];

        foreach (['color', 'spacing', 'fontSize'] as $set) {
            $tokens[$set] = $this->tokens->getSlugs($set);
        }

        $segments = [
            $this->prompts->system('block-editing'),
            "Available blocks and the attributes you may change:\n" . $this->encode($manifests),
            "Design tokens available in this theme. Use these slugs, never literal values:\n" . $this->encode($tokens),
        ];

        // Brand context belongs in the stable prefix: it changes far less often
        // than the selection, so it stays cacheable.
        $brand = $this->describeBrand();

        if ($brand !== '') {
            $segments[] = $brand;
        }

        return implode("\n\n", $segments);
    }

    /**
     * The site's brand context, as prose the model can actually use.
     *
     * Empty fields are omitted entirely rather than sent blank — a blank value
     * reads as "no preference expressed" far less clearly than its absence.
     *
     * @return string
     */
    private function describeBrand()
    {
        $labels = [
            'siteName'        => 'Business name',
            'siteDescription' => 'What the business does',
            'designStyle'     => 'Preferred design style',
            'blockShapes'     => 'Preferred corner treatment',
            'copywritingTone' => 'Copywriting tone',
        ];

        $lines = [];

        foreach ($labels as $key => $label) {
            $value = isset($this->brand[$key]) ? trim((string) $this->brand[$key]) : '';

            if ($value !== '') {
                $lines[] = $label . ': ' . $value;
            }
        }

        if ($lines === []) {
            return '';
        }

        return "About this business. Write for it specifically, and respect these preferences:\n"
            . implode("\n", $lines);
    }

    /**
     * Volatile suffix: this selection, this instruction.
     *
     * @return string
     */
    private function buildUserPrompt(array $selection, $instruction)
    {
        $selected = [
            'clientId'   => $selection['clientId'],
            'blockName'  => $selection['blockName'],
            'attributes' => isset($selection['attributes']) ? $selection['attributes'] : [],
        ];

        return $this->prompts->task('modify-block', [
            'selection'   => $this->encode($selected),
            'context'     => $this->encode($this->describeSurroundings($selection)),
            'instruction' => (string) $instruction,
        ]);
    }

    /**
     * Neighbours by name and purpose only — never their attributes.
     *
     * @return array
     */
    private function describeSurroundings(array $selection)
    {
        $summarise = function (array $block) {
            $capability = $this->registry->get($block['blockName']);

            return [
                'clientId'  => isset($block['clientId']) ? $block['clientId'] : null,
                'blockName' => $block['blockName'],
                'purpose'   => $capability === null ? 'unknown' : $capability->getPurpose(),
            ];
        };

        $context = [
            'postType'       => isset($selection['postType']) ? $selection['postType'] : 'page',
            'styleVariation' => isset($selection['styleVariation']) ? $selection['styleVariation'] : 'default',
            'parent'         => null,
            'siblings'       => [],
            'innerBlocks'    => [],
        ];

        if (! empty($selection['parent'])) {
            $context['parent'] = $summarise($selection['parent']);
        }

        foreach ((array) (isset($selection['siblings']) ? $selection['siblings'] : []) as $sibling) {
            $context['siblings'][] = $summarise($sibling);
        }

        foreach ((array) (isset($selection['innerBlocks']) ? $selection['innerBlocks'] : []) as $child) {
            $context['innerBlocks'][] = $summarise($child);
        }

        return $context;
    }

    /**
     * @return string[] Block names whose manifests belong in this prompt.
     */
    private function blockTypesInPlay(array $selection)
    {
        $names = [$selection['blockName']];

        foreach ((array) (isset($selection['innerBlocks']) ? $selection['innerBlocks'] : []) as $child) {
            $names[] = $child['blockName'];
        }

        $capability = $this->registry->get($selection['blockName']);

        if ($capability !== null) {
            // Children the AI may insert must be describable, or it cannot
            // supply valid attributes for them.
            $names = array_merge($names, $capability->getAllowedChildren());
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array
     */
    private function describeCapability($capability)
    {
        $described = [
            'purpose' => $capability->getPurpose(),
            'summary' => $capability->getSummary(),
            'tier'    => $capability->getTier(),
            'fields'  => [],
        ];

        foreach ($capability->getFields() as $attribute => $field) {
            $spec = ['group' => $field->getGroup(), 'type' => $field->getType()];

            if ($field->getRole() !== null) {
                $spec['role'] = $field->getRole();
            }
            if ($field->getEnum() !== []) {
                $spec['enum'] = $field->getEnum();
            }
            if ($field->getTokenSet() !== null) {
                $spec['tokenSet'] = $field->getTokenSet();
            }
            if ($field->getMin() !== null) {
                $spec['min'] = $field->getMin();
            }
            if ($field->getMax() !== null) {
                $spec['max'] = $field->getMax();
            }
            if ($field->getMaxLength() !== null) {
                $spec['maxLength'] = $field->getMaxLength();
            }

            $described['fields'][$attribute] = $spec;
        }

        if ($capability->getAllowedChildren() !== []) {
            $described['children'] = [
                'allowed' => $capability->getAllowedChildren(),
                'min'     => $capability->getChildMin(),
                'max'     => $capability->getChildMax(),
            ];
        }

        return $described;
    }

    private function encode(array $data)
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
