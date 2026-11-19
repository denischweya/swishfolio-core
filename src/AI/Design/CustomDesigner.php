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
     * @param Kit|null   $layout The structural kit (its classes for polish CSS), or null.
     *
     * @throws \SwishfolioCore\AI\ProviderException On provider failure.
     *
     * @return array {tokens, response}
     */
    public function design($prompt, $business, array $kits, $model, array $seed = null, Kit $layout = null)
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
                'polish'    => self::polishBrief($layout),
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
     * The prompt block on the optional polish CSS: what it may touch and how.
     *
     * @param Kit|null $layout Structural kit.
     *
     * @return string
     */
    public static function polishBrief(Kit $layout = null)
    {
        $prefix = $layout !== null && isset($layout->getDesign()['classPrefix']) ? preg_replace('/[^a-z0-9-]/', '', strtolower((string) $layout->getDesign()['classPrefix'])) : '';
        if ($prefix === '') {
            return "\ncss: answer an empty string.\n";
        }

        $design  = $layout->getDesignFile();
        $classes = [];
        if (preg_match_all('/\.(' . preg_quote($prefix, '/') . '[a-z0-9_-]+)/i', isset($design['globalStyles']['styles']['css']) ? (string) $design['globalStyles']['styles']['css'] : '', $m)) {
            $classes = array_count_values($m[1]);
            arsort($classes);
            $classes = array_slice(array_keys($classes), 0, 40);
        }

        return "\ncss (optional): a few plain CSS rules of polish that the options above cannot express, for example section rhythm, button shape or a heading treatment. Leave it empty when the options already say it. Rules:\n"
            . "- Every selector must use one of the template's classes (they start with ." . $prefix . "). Rules are scoped under body for you.\n"
            . "- Properties only from: margin, padding, gap, border (and its sides, width, style, radius), outline, font-size, font-weight, font-style, letter-spacing, line-height, text-transform, text-align, text-decoration, text-underline-offset, max-width, min-height, aspect-ratio, object-fit, color, background-color, box-shadow, opacity, transition.\n"
            . "- Colours only as the design's variables: var(--sf-bg), var(--sf-surface), var(--sf-text), var(--sf-muted), var(--sf-accent), var(--sf-accent-2), var(--sf-soft), var(--sf-inverse), var(--sf-on-accent), var(--sf-on-inverse), var(--sf-line), or currentColor. Never a hex, rgb() or colour name.\n"
            . "- No @ rules, no url(), no comments, no position, no display, at most 4 KB. Anything else is thrown away.\n"
            . ($classes ? "The template's most used classes: " . implode(', ', array_map(static function ($c) {
                return '.' . $c;
            }, $classes)) . "\n" : '');
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
