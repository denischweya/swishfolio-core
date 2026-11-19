<?php
/**
 * Picks the site template (kit) closest to an owner's brief.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;

/**
 * One short classification call (the caller passes the follow-up model, see
 * ModelRouter::KIND_TEMPLATE_MATCH) over a one-line description of every
 * template, and a keyword ranking that answers on its own when there is no
 * provider, the call fails, or the model names a template that does not exist.
 *
 * Writes nothing: the result is a proposal the owner confirms or changes.
 */
class TemplateMatcher
{
    const ALTERNATIVES = 3;
    const DOC_CHARS    = 3000;

    /**
     * Below this fit (0 to 100) no design is a close match, and the wizard
     * recommends a custom design instead (see lowFit()).
     */
    const LOW_FIT = 60;

    /** @var AIProviderInterface|null */
    private $provider;

    /** @var PromptManager|null */
    private $prompts;

    /**
     * @param AIProviderInterface|null $provider Provider, or null for keywords only.
     * @param PromptManager|null       $prompts  Prompts (required with a provider).
     */
    public function __construct(AIProviderInterface $provider = null, PromptManager $prompts = null)
    {
        $this->provider = $provider;
        $this->prompts  = $prompts;
    }

    /**
     * @param array   $brief     business, contact, prompt, documents, profile, design, images.
     * @param array[] $templates slug => SiteTemplates entry.
     * @param string  $model     Model id for the classification.
     *
     * @return array {slug, alternatives, reason, designWishes, fit (0-100), lowFit, business, source ('ai'|'keywords'), response (AIResponse|null)}
     */
    public function match(array $brief, array $templates, $model = '')
    {
        if (! $templates) {
            return ['slug' => '', 'alternatives' => [], 'reason' => '', 'designWishes' => '', 'fit' => 0, 'lowFit' => false, 'business' => '', 'source' => 'none', 'response' => null];
        }

        $text   = self::briefText($brief);
        $ranked = self::rank($text, $templates);

        if ($this->provider !== null && $this->prompts !== null && count($templates) > 1 && trim($text) !== '') {
            try {
                $response = $this->provider->generate(new AIRequest(
                    $this->prompts->system('agent'),
                    $this->prompts->task('match-template', [
                        'templates' => self::catalogText($templates),
                        'brief'     => $text,
                    ]),
                    self::schema(array_keys($templates)),
                    (string) $model,
                    800
                ));

                $result = self::fromModel((array) $response->getDecoded(), $templates, $ranked);

                if ($result !== null) {
                    $result['source']   = 'ai';
                    $result['response'] = $response;

                    return $result;
                }
            } catch (ProviderException $e) {
                // Fall through to the keyword ranking.
                unset($e);
            }
        }

        return self::fallback($brief, $templates, $ranked);
    }

    /**
     * Pure: everything the owner told us, as one block of text.
     *
     * @param array $brief Brief.
     *
     * @return string
     */
    public static function briefText(array $brief)
    {
        $b     = isset($brief['business']) ? (array) $brief['business'] : [];
        $p     = isset($brief['profile']) && is_array($brief['profile']) ? $brief['profile'] : [];
        $d     = isset($brief['design']) ? (array) $brief['design'] : [];
        $lines = [];

        foreach (['name' => 'Business', 'tagline' => 'Tagline', 'city' => 'City', 'country' => 'Country'] as $key => $label) {
            if (! empty($b[$key]) && is_scalar($b[$key])) {
                $lines[] = $label . ': ' . $b[$key];
            }
        }
        if (! empty($b['brief']) && is_scalar($b['brief'])) {
            $lines[] = 'In their words: ' . $b['brief'];
        }
        if (! empty($brief['prompt']) && is_scalar($brief['prompt'])) {
            $lines[] = 'Their requests: ' . $brief['prompt'];
        }
        foreach (['headline' => 'Profile', 'summary' => 'About'] as $key => $label) {
            if (! empty($p[$key]) && is_scalar($p[$key])) {
                $lines[] = $label . ': ' . $p[$key];
            }
        }
        if (! empty($p['services']) && is_array($p['services'])) {
            $titles = [];
            foreach ($p['services'] as $service) {
                if (is_array($service) && ! empty($service['title'])) {
                    $titles[] = (string) $service['title'];
                }
            }
            if ($titles) {
                $lines[] = 'Services: ' . implode(', ', $titles);
            }
        }
        if (! empty($d['designStyle']) && is_scalar($d['designStyle'])) {
            $lines[] = 'Style they chose: ' . $d['designStyle'];
        }
        if (! empty($brief['images']) && is_array($brief['images'])) {
            $notes = [];
            foreach ($brief['images'] as $image) {
                if (is_array($image)) {
                    $notes[] = trim((isset($image['role']) ? $image['role'] : 'image') . (! empty($image['note']) ? ' "' . $image['note'] . '"' : ''));
                }
            }
            $lines[] = 'Images they uploaded: ' . implode(', ', $notes);
        }
        $docs = isset($brief['documents']) && is_scalar($brief['documents']) ? trim((string) $brief['documents']) : '';
        if ($docs !== '') {
            $lines[] = "Start of their documents:\n" . mb_substr($docs, 0, self::DOC_CHARS);
        }

        return implode("\n", $lines);
    }

    /**
     * Pure: keyword score per template. A phrase hit counts more than a word.
     *
     * @param string  $text      Brief text.
     * @param array[] $templates Templates.
     *
     * @return int[] slug => score, best first (ties keep catalog order).
     */
    public static function scores($text, array $templates)
    {
        $text   = ' ' . strtolower((string) $text) . ' ';
        $scores = [];
        $order  = 0;
        $keyed  = [];

        foreach ($templates as $slug => $template) {
            $score = 0;
            foreach (isset($template['keywords']) ? (array) $template['keywords'] : [] as $word) {
                $word = strtolower(trim((string) $word));
                // Word start only: "cafe" matches "cafes", "bar" does not match "barber".
                if ($word !== '' && preg_match('/(?<![a-z0-9])' . preg_quote($word, '/') . '(?:s|es)?(?![a-z0-9])/u', $text)) {
                    $score += strpos($word, ' ') !== false ? 3 : 2;
                }
            }
            $keyed[] = [(string) $slug, $score, $order++];
        }

        usort($keyed, static function ($a, $b) {
            return $b[1] === $a[1] ? $a[2] - $b[2] : $b[1] - $a[1];
        });

        foreach ($keyed as $row) {
            $scores[$row[0]] = $row[1];
        }

        return $scores;
    }

    /**
     * @param string  $text      Brief text.
     * @param array[] $templates Templates.
     *
     * @return string[] Slugs, best first.
     */
    public static function rank($text, array $templates)
    {
        return array_map('strval', array_keys(self::scores($text, $templates)));
    }

    /**
     * Pure: the deterministic answer.
     *
     * @param array    $brief     Brief.
     * @param array[]  $templates Templates.
     * @param string[] $ranked    rank() output.
     *
     * @return array
     */
    public static function fallback(array $brief, array $templates, array $ranked)
    {
        $scores = self::scores(self::briefText($brief), $templates);
        $best   = (string) reset($ranked);

        // Nothing in the brief points anywhere: the catalog's default design.
        if (! $scores || (int) reset($scores) === 0) {
            $best = (string) array_keys($templates)[0];
            foreach ($templates as $slug => $template) {
                if (! empty($template['default'])) {
                    $best = (string) $slug;
                    break;
                }
            }
        }

        $style = isset($brief['design']['designStyle']) && is_scalar($brief['design']['designStyle']) ? trim((string) $brief['design']['designStyle']) : '';
        $fit   = self::keywordFit($scores ? (int) reset($scores) : 0);

        return [
            'slug'         => $best,
            'alternatives' => self::alternatives($best, [], $templates, $ranked),
            'reason'       => '',
            'designWishes' => $style !== '' ? 'Style: ' . $style : '',
            'fit'          => $fit,
            'lowFit'       => self::lowFit($fit),
            'business'     => '',
            'source'       => 'keywords',
            'response'     => null,
        ];
    }

    /**
     * Pure: validate the model's answer; null when its pick is unusable.
     *
     * @param array    $decoded   Model output.
     * @param array[]  $templates Templates.
     * @param string[] $ranked    rank() output, to fill missing alternatives.
     *
     * @return array|null
     */
    public static function fromModel(array $decoded, array $templates, array $ranked)
    {
        $slug = isset($decoded['slug']) && is_string($decoded['slug']) ? strtolower(trim($decoded['slug'])) : '';

        if ($slug === '' || ! isset($templates[$slug])) {
            return null;
        }

        $clean = static function ($value, $max) {
            $value = is_scalar($value) ? trim(preg_replace('/\s+/', ' ', strip_tags((string) $value))) : '';
            // House style: no long dashes in copy the owner reads.
            $value = str_replace(["\u{2014}", " \u{2013} "], [', ', ', '], $value);

            return mb_substr($value, 0, $max);
        };

        // A reply without a fit (an older schema) is treated as a good one: no nudge.
        $fit = isset($decoded['fit']) && is_numeric($decoded['fit']) ? max(0, min(100, (int) round((float) $decoded['fit']))) : 100;

        return [
            'slug'         => $slug,
            'alternatives' => self::alternatives($slug, isset($decoded['alternatives']) ? (array) $decoded['alternatives'] : [], $templates, $ranked),
            'reason'       => $clean(isset($decoded['reason']) ? $decoded['reason'] : '', 240),
            'designWishes' => $clean(isset($decoded['designWishes']) ? $decoded['designWishes'] : '', 500),
            'fit'          => $fit,
            'lowFit'       => self::lowFit($fit),
            'business'     => $clean(isset($decoded['business']) ? $decoded['business'] : '', 80),
            'source'       => 'ai',
            'response'     => null,
        ];
    }

    /**
     * Pure: whether a fit score means no design is a close match.
     *
     * @param int $fit 0 to 100.
     *
     * @return bool
     */
    public static function lowFit($fit)
    {
        return (int) $fit < self::LOW_FIT;
    }

    /**
     * Pure: a fit score from the best keyword score, for the no-provider path.
     * No keyword at all is a poor fit; one phrase or two words a fair one.
     *
     * @param int $score Best scores() value.
     *
     * @return int 0 to 100.
     */
    public static function keywordFit($score)
    {
        return (int) $score <= 0 ? 30 : min(90, 50 + 10 * (int) $score);
    }

    /**
     * Pure: up to ALTERNATIVES other slugs, the model's first, then the keyword
     * ranking, then the same industry, then catalog order.
     *
     * @param string   $slug      The pick.
     * @param array    $proposed  Model's alternatives.
     * @param array[]  $templates Templates.
     * @param string[] $ranked    rank() output.
     *
     * @return string[]
     */
    public static function alternatives($slug, array $proposed, array $templates, array $ranked)
    {
        $out      = [];
        $industry = isset($templates[$slug]['industry']) ? $templates[$slug]['industry'] : '';
        $sameIndustry = [];

        foreach ($templates as $key => $template) {
            if ($industry !== '' && isset($template['industry']) && $template['industry'] === $industry) {
                $sameIndustry[] = (string) $key;
            }
        }

        foreach ([$proposed, array_slice($ranked, 0, 2), $sameIndustry, $ranked, array_keys($templates)] as $list) {
            foreach ($list as $key) {
                $key = strtolower(trim((string) $key));
                if (count($out) >= self::ALTERNATIVES) {
                    break 2;
                }
                if ($key !== $slug && isset($templates[$key]) && ! in_array($key, $out, true)) {
                    $out[] = $key;
                }
            }
        }

        return $out;
    }

    /**
     * Pure: one line per template for the prompt.
     *
     * @param array[] $templates Templates.
     *
     * @return string
     */
    public static function catalogText(array $templates)
    {
        $lines = [];

        foreach ($templates as $slug => $t) {
            $lines[] = sprintf(
                '- %s: "%s" (%s; %s). %s%s',
                $slug,
                $t['name'] !== '' ? $t['name'] : $slug,
                $t['title'],
                $t['industry'],
                $t['description'],
                $t['look'] !== '' ? ' Look: ' . $t['look'] : ''
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param string[] $slugs Allowed slugs.
     *
     * @return array Output schema.
     */
    public static function schema(array $slugs)
    {
        $slugs = array_values(array_map('strval', $slugs));

        return [
            'type'       => 'object',
            'required'   => ['slug', 'alternatives', 'reason', 'designWishes', 'fit', 'business'],
            'properties' => [
                'slug'         => ['type' => 'string', 'enum' => $slugs, 'description' => 'The closest template.'],
                'alternatives' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $slugs], 'description' => 'The next best three, best first.'],
                'reason'       => ['type' => 'string', 'description' => 'One short sentence to the owner on why this design fits.'],
                'designWishes' => ['type' => 'string', 'description' => 'Only concrete visual instructions they stated (colours, light or dark, fonts, a named style). Empty when none.'],
                'fit'          => ['type' => 'integer', 'description' => 'How closely the picked design fits this business, 0 to 100. 80 or more: made for this kind of business. Below 60: no design is a close match.'],
                'business'     => ['type' => 'string', 'description' => 'What the business is, in a few plain words (for example "a dog grooming salon").'],
            ],
        ];
    }

    /**
     * @param AIResponse|null $response Response.
     *
     * @return int[] [in, out]
     */
    public static function usage($response)
    {
        return $response instanceof AIResponse ? [(int) $response->getInputTokens(), (int) $response->getOutputTokens()] : [0, 0];
    }
}
