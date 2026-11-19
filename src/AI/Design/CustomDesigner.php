<?php
/**
 * Asks the model for a custom design system.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Design;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\Generation\Kit;
use SwishfolioCore\AI\Prompt\PromptManager;

/**
 * One structured call: the owner's design prompt and business in, a token
 * set (DesignTokens::schema()) out, validated before anything else sees it.
 * The model is told every existing template's look so it designs away from
 * all of them, and a reply that still lands on a template's exact font
 * pairing has its display font swapped for another of the same role.
 *
 * With a seed (a theme style variation, StylePresets::seed()) the model gets
 * those tokens as its starting point and changes only what the prompt asks;
 * fonts it keeps from the seed are not swapped.
 */
class CustomDesigner
{
    const MAX_TOKENS = 3000;

    /** @var AIProviderInterface */
    private $provider;

    /** @var PromptManager */
    private $prompts;

    public function __construct(AIProviderInterface $provider, PromptManager $prompts)
    {
        $this->provider = $provider;
        $this->prompts  = $prompts;
    }

    /**
     * @param string $prompt   The owner's design prompt.
     * @param string $business What the business is (plain text).
     * @param Kit[]  $kits     Every template kit (for their looks).
     * @param string $model    Model id.
     * @param array|null $seed   Starting tokens (StylePresets::seed()), or null.
     *
     * @throws \SwishfolioCore\AI\ProviderException On provider failure.
     *
     * @return array {tokens, response}
     */
    public function design($prompt, $business, array $kits, $model, array $seed = null)
    {
        $looks = [];
        foreach ($kits as $kit) {
            if ($kit->getLook() !== '') {
                $looks[] = '- ' . $kit->getName() . ': ' . $kit->getLook();
            }
        }

        $response = $this->provider->generate(new AIRequest(
            $this->prompts->system('custom-design'),
            $this->prompts->task('custom-design', [
                'business'  => trim((string) $business) !== '' ? trim((string) $business) : 'A small business.',
                'prompt'    => trim((string) $prompt),
                'templates' => implode("\n", $looks),
                'fonts'     => FontCatalogue::describe(),
                'start'     => $seed === null ? '' : self::startFrom($seed),
            ]),
            DesignTokens::schema(),
            (string) $model,
            self::MAX_TOKENS
        ));

        $tokens = DesignTokens::validate((array) $response->getDecoded());
        // A preset's own pairing is the owner's choice, not a template copy.
        $keptSeedFonts = $seed !== null && isset($seed['fonts']['display'], $seed['fonts']['body'])
            && $tokens['fonts']['display'] === $seed['fonts']['display'] && $tokens['fonts']['body'] === $seed['fonts']['body'];

        return ['tokens' => $keptSeedFonts ? $tokens : self::avoidKitPairs($tokens, $kits), 'response' => $response];
    }

    /**
     * The prompt block that hands the model a preset to modify.
     *
     * @param array $seed Seed tokens.
     *
     * @return string
     */
    private static function startFrom(array $seed)
    {
        $name = isset($seed['name']) ? (string) $seed['name'] : 'the chosen style';

        return "\nThe owner chose to start from our \"" . $name . "\" style. These are its tokens. Start from them: keep every value the brief does not ask to change, and change what it does ask for (and anything that must change with it to stay coherent). Where these tokens share something with a template above, keep it: the owner picked this style. Keep the name unless the look changes a lot.\n"
            . json_encode($seed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * Pure: a display + body pairing that is exactly a template's moves the
     * display font to the next catalogue font of the same role.
     *
     * @param array $tokens Valid tokens.
     * @param Kit[] $kits   Kits.
     *
     * @return array
     */
    public static function avoidKitPairs(array $tokens, array $kits)
    {
        $pairs = [];
        foreach ($kits as $kit) {
            $fonts = isset($kit->getDesign()['fonts']) ? array_values((array) $kit->getDesign()['fonts']) : [];
            if (count($fonts) >= 2) {
                $pairs[] = $fonts;
            }
        }

        foreach ($pairs as $fonts) {
            if (in_array($tokens['fonts']['display'], $fonts, true) && in_array($tokens['fonts']['body'], $fonts, true) && $tokens['fonts']['display'] !== $tokens['fonts']['body']) {
                $role = FontCatalogue::role($tokens['fonts']['display']);
                $all  = FontCatalogue::families();
                $i    = array_search($tokens['fonts']['display'], $all, true);
                for ($n = 1; $n < count($all); $n++) {
                    $next = $all[($i + $n) % count($all)];
                    if (FontCatalogue::role($next) === $role && ! in_array($next, $fonts, true)) {
                        $tokens['fonts']['display'] = $next;
                        $tokens['headingWeight']    = FontCatalogue::clampWeight($tokens['headingWeight'], $next);
                        break;
                    }
                }
            }
        }

        return $tokens;
    }

    /**
     * @param \SwishfolioCore\AI\AIResponse|null $response Response.
     *
     * @return array {in, out, model}
     */
    public static function usage($response)
    {
        return $response === null ? ['in' => 0, 'out' => 0, 'model' => ''] : ['in' => (int) $response->getInputTokens(), 'out' => (int) $response->getOutputTokens(), 'model' => (string) $response->getModel()];
    }
}
