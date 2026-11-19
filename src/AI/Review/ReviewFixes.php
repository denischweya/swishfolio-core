<?php
/**
 * The fixes a visual review may make, and the strict check of a model's list.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Review;

use SwishfolioCore\AI\Copy\Dashes;
use SwishfolioCore\AI\Design\Color;

/**
 * Pure. The model only ever names fixes from FIXED_TYPES, each pointing at
 * something the prompt listed (facts): a section by its number, a slot or
 * image by its id, a kit unit or kit image by name, a palette token by slug.
 * Anything else, anything out of bounds, or more than MAX_FIXES, is rejected
 * with a reason and never reaches the site.
 *
 * facts:
 *   pages     key => {postId, title, sections: {n => {unit, role, form, alternatives: {unitId => summary}}},
 *                     texts: {slotId => {text, len, html}}, images: {slotId => file}}
 *   kitImages name => alt
 *   tokens    slug => #rrggbb
 */
class ReviewFixes
{
    const MAX_FIXES = 6;

    const REMOVE_SECTION = 'remove_section';
    const SWAP_SECTION   = 'swap_section';
    const SWAP_IMAGE     = 'swap_image';
    const ADJUST_TOKEN   = 'adjust_token';
    const SHORTEN_COPY   = 'shorten_copy';

    const FIXED_TYPES = [self::REMOVE_SECTION, self::SWAP_SECTION, self::SWAP_IMAGE, self::ADJUST_TOKEN, self::SHORTEN_COPY];

    /** Section changes (remove or swap) per page. */
    const MAX_SECTION_CHANGES = 2;

    /** A page keeps at least this many sections. */
    const MIN_SECTIONS = 3;

    const MAX_TOKEN_CHANGES = 2;

    /** Colour bounds (OKLab / OKLCH). */
    const MAX_LIGHTNESS_SHIFT = 0.25;
    const MAX_CHROMA_SHIFT    = 0.10;
    const MAX_HUE_SHIFT       = 30.0;

    /** Shortened copy is at most this share of the original. */
    const SHORTEN_RATIO = 0.85;

    /**
     * The JSON schema the model answers with.
     *
     * @return array
     */
    public static function schema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['verdict', 'issues', 'fixes'],
            'properties'           => [
                'verdict' => ['type' => 'string', 'enum' => ['good', 'needs-fixes']],
                'issues'  => [
                    'type'  => 'array',
                    'items' => ['type' => 'string'],
                ],
                'fixes'   => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['type', 'reason'],
                        'properties'           => [
                            'type'    => ['type' => 'string', 'enum' => self::FIXED_TYPES],
                            'page'    => ['type' => 'string', 'description' => 'Page key from the facts (home or page).'],
                            'section' => ['type' => 'integer', 'description' => 'Section number on that page.'],
                            'unit'    => ['type' => 'string', 'description' => 'swap_section: one of the alternatives listed for that section.'],
                            'slot'    => ['type' => 'string', 'description' => 'swap_image: an i* id; shorten_copy: a t* id.'],
                            'image'   => ['type' => 'string', 'description' => 'swap_image: a kit image name, or "neutral".'],
                            'token'   => ['type' => 'string', 'description' => 'adjust_token: a palette slug.'],
                            'color'   => ['type' => 'string', 'description' => 'adjust_token: new #rrggbb.'],
                            'text'    => ['type' => 'string', 'description' => 'shorten_copy: the shorter plain text.'],
                            'reason'  => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array $decoded Model reply.
     * @param array $facts   What the prompt offered.
     * @param int   $max     Most fixes accepted.
     *
     * @return array{accepted: array[], rejected: array[]} rejected: {fix, reason}.
     */
    public static function validate(array $decoded, array $facts, $max = self::MAX_FIXES)
    {
        $accepted = [];
        $rejected = [];
        $taken    = [];
        $changes  = [];
        $tokens   = 0;
        $removed  = [];

        $fixes = isset($decoded['fixes']) && is_array($decoded['fixes']) ? array_values($decoded['fixes']) : [];

        foreach ($fixes as $fix) {
            if (! is_array($fix)) {
                $rejected[] = ['fix' => $fix, 'reason' => 'not an object'];
                continue;
            }
            if (count($accepted) >= (int) $max) {
                $rejected[] = ['fix' => $fix, 'reason' => 'over the limit of ' . (int) $max . ' fixes'];
                continue;
            }

            $type = isset($fix['type']) ? (string) $fix['type'] : '';
            if (! in_array($type, self::FIXED_TYPES, true)) {
                $rejected[] = ['fix' => $fix, 'reason' => 'not a fix from the list'];
                continue;
            }

            $result = self::check($type, $fix, $facts);
            if (is_string($result)) {
                $rejected[] = ['fix' => $fix, 'reason' => $result];
                continue;
            }

            $key = $result['target'];
            if (isset($taken[$key])) {
                $rejected[] = ['fix' => $fix, 'reason' => 'a second fix for the same target'];
                continue;
            }

            if ($type === self::REMOVE_SECTION || $type === self::SWAP_SECTION) {
                $page = $result['page'];
                $n    = isset($changes[$page]) ? $changes[$page] : 0;
                if ($n >= self::MAX_SECTION_CHANGES) {
                    $rejected[] = ['fix' => $fix, 'reason' => 'too many section changes on one page'];
                    continue;
                }
                if ($type === self::REMOVE_SECTION) {
                    $left = count($facts['pages'][$page]['sections']) - (isset($removed[$page]) ? $removed[$page] : 0) - 1;
                    if ($left < self::MIN_SECTIONS) {
                        $rejected[] = ['fix' => $fix, 'reason' => 'the page would be left too short'];
                        continue;
                    }
                    $removed[$page] = (isset($removed[$page]) ? $removed[$page] : 0) + 1;
                }
                $changes[$page] = $n + 1;
            }

            if ($type === self::ADJUST_TOKEN) {
                if ($tokens >= self::MAX_TOKEN_CHANGES) {
                    $rejected[] = ['fix' => $fix, 'reason' => 'too many colour changes'];
                    continue;
                }
                $tokens++;
            }

            // A section that is removed or swapped takes its slot fixes with it.
            $taken[$key] = true;
            unset($result['target']);
            $result['type']   = $type;
            $result['reason'] = Dashes::text(mb_substr(trim(isset($fix['reason']) ? (string) $fix['reason'] : ''), 0, 240));
            $accepted[]       = $result;
        }

        return ['accepted' => self::dropShadowed($accepted, $facts, $rejected), 'rejected' => $rejected];
    }

    /**
     * @param string $type  Fix type.
     * @param array  $fix   Raw fix.
     * @param array  $facts Facts.
     *
     * @return array|string Normalised fix plus "target", or why it was rejected.
     */
    private static function check($type, array $fix, array $facts)
    {
        if ($type === self::ADJUST_TOKEN) {
            return self::checkToken($fix, $facts);
        }

        $pageKey = isset($fix['page']) ? (string) $fix['page'] : '';
        if (! isset($facts['pages'][$pageKey])) {
            return 'unknown page';
        }
        $page = $facts['pages'][$pageKey];

        if ($type === self::REMOVE_SECTION || $type === self::SWAP_SECTION) {
            $n = isset($fix['section']) && is_numeric($fix['section']) ? (int) $fix['section'] : 0;
            if (! isset($page['sections'][$n])) {
                return 'unknown section';
            }
            $section = $page['sections'][$n];
            if (! empty($section['form'])) {
                return 'the section holds the contact form';
            }
            if ($type === self::REMOVE_SECTION) {
                if ($n === 1) {
                    return 'the first section of a page is never removed';
                }

                return ['page' => $pageKey, 'section' => $n, 'target' => $pageKey . ':s' . $n];
            }
            $unit = isset($fix['unit']) ? (string) $fix['unit'] : '';
            if ($unit === '' || ! isset($section['alternatives'][$unit])) {
                return 'not an alternative offered for that section';
            }

            return ['page' => $pageKey, 'section' => $n, 'unit' => $unit, 'target' => $pageKey . ':s' . $n];
        }

        $slot = isset($fix['slot']) ? (string) $fix['slot'] : '';

        if ($type === self::SWAP_IMAGE) {
            if (! isset($page['images'][$slot])) {
                return 'unknown image slot';
            }
            $image = isset($fix['image']) ? (string) $fix['image'] : '';
            if ($image !== 'neutral' && ! isset($facts['kitImages'][$image])) {
                return 'not a kit image';
            }
            // The build chose the stand-in because no photo fitted (often a
            // portrait spot): a kit photo there would show a stranger as the owner.
            if ($image !== 'neutral' && ! empty($page['images'][$slot]['neutral'])) {
                return 'the build left a neutral placeholder there on purpose';
            }

            return ['page' => $pageKey, 'slot' => $slot, 'image' => $image, 'target' => $pageKey . ':' . $slot];
        }

        // shorten_copy
        if (! isset($page['texts'][$slot])) {
            return 'unknown text slot';
        }
        $old = $page['texts'][$slot];
        if (! empty($old['html'])) {
            return 'the slot holds links or markup';
        }
        $raw  = isset($fix['text']) ? (string) $fix['text'] : '';
        $text = trim(preg_replace('/\s+/u', ' ', $raw));
        if ($text === '' || $text !== trim(preg_replace('/\s+/u', ' ', strip_tags($raw))) || strpos($text, '{{') !== false || preg_match('/[<>]/', $text)) {
            return 'not plain text';
        }
        $text = Dashes::text($text);
        $len  = mb_strlen($text);
        if ($len < 3 || $len > (int) floor(((int) $old['len']) * self::SHORTEN_RATIO)) {
            return 'not clearly shorter than the original';
        }

        return ['page' => $pageKey, 'slot' => $slot, 'text' => $text, 'target' => $pageKey . ':' . $slot];
    }

    /**
     * @param array $fix   Raw fix.
     * @param array $facts Facts.
     *
     * @return array|string
     */
    private static function checkToken(array $fix, array $facts)
    {
        $tokens = isset($facts['tokens']) ? (array) $facts['tokens'] : [];
        $slug   = isset($fix['token']) ? (string) $fix['token'] : '';
        $raw    = isset($fix['color']) ? trim((string) $fix['color']) : '';

        if (! isset($tokens[$slug])) {
            return 'unknown palette token';
        }
        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $raw)) {
            return 'not a #rrggbb colour';
        }

        $old = strtolower($tokens[$slug]);
        $new = strtolower($raw);
        if ($old === $new) {
            return 'no change';
        }

        if (abs(Color::lightness($new) - Color::lightness($old)) > self::MAX_LIGHTNESS_SHIFT) {
            return 'lightness changes too much';
        }
        $cOld = Color::chroma($old);
        $cNew = Color::chroma($new);
        if (abs($cNew - $cOld) > self::MAX_CHROMA_SHIFT) {
            return 'saturation changes too much';
        }
        if ($cOld > 0.04 && $cNew > 0.04) {
            $d = abs(Color::hue($new) - Color::hue($old));
            if (min($d, 360 - $d) > self::MAX_HUE_SHIFT) {
                return 'hue changes too much';
            }
        }

        // Against the page and text colours, never less legible than before
        // (or than AA, when it was already above it).
        $partners = [];
        foreach (['base', 'contrast'] as $p) {
            if ($p !== $slug && isset($tokens[$p])) {
                $partners[] = strtolower($tokens[$p]);
            }
        }
        if ($partners) {
            $best = static function ($hex) use ($partners) {
                return max(array_map(static function ($p) use ($hex) {
                    return Color::contrast($hex, $p);
                }, $partners));
            };
            if ($best($new) + 0.01 < min($best($old), Color::AA)) {
                return 'lowers contrast';
            }
        }

        return ['token' => $slug, 'from' => $old, 'color' => $new, 'target' => 'token:' . $slug];
    }

    /**
     * Slot fixes inside a section that another fix removes or swaps would
     * edit content that is gone; drop them.
     *
     * @param array[] $accepted Accepted fixes.
     * @param array   $facts    Facts (texts/images carry their section number).
     * @param array[] $rejected Rejections, appended to.
     *
     * @return array[]
     */
    private static function dropShadowed(array $accepted, array $facts, array &$rejected)
    {
        $gone = [];
        foreach ($accepted as $f) {
            if (isset($f['section'])) {
                $gone[$f['page'] . ':' . $f['section']] = true;
            }
        }

        $out = [];
        foreach ($accepted as $f) {
            if (isset($f['slot'])) {
                $map  = $f['type'] === self::SWAP_IMAGE ? 'images' : 'texts';
                $slot = $facts['pages'][$f['page']][$map][$f['slot']];
                $in   = is_array($slot) && isset($slot['section']) ? (int) $slot['section'] : 0;
                if ($in && isset($gone[$f['page'] . ':' . $in])) {
                    $rejected[] = ['fix' => $f, 'reason' => 'inside a section another fix changes'];
                    continue;
                }
            }
            $out[] = $f;
        }

        return $out;
    }
}
