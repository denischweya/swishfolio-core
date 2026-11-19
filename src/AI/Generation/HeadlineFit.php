<?php
/**
 * Keeps a page title close to the size its template designed it for.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

/**
 * A kit's H1 is set at a display size chosen for the demo's few words
 * ("Cooked over embers, eaten slowly."). An owner's longer title still wraps
 * (HeadingSafety), but at that size it can take five lines and push the
 * section apart, or run far under a photo. CSS cannot measure text, so the
 * title is scaled from its length against the demo's: a title up to 15%
 * longer keeps the kit's size, a longer one is drawn smaller, by the square
 * root of the ratio (so it covers about the area the demo did), in 5% steps,
 * never below 60%.
 *
 * The scale travels inside the title's own copy, as a wrapper
 *
 *   <span class="sf-fit sf-fit-80">…</span>
 *
 * styled by HeadingSafety (block display, zoom). A span is the one element a
 * text slot accepts with a class, it keeps the heading block valid in the
 * editor, and it scales the kit's size whatever that is (a clamp() in vw), em
 * based letter spacing and line height included. A decorative copy of the
 * title (a "ghost" drawn over the photo, Kit::mirrorSlots()) takes the very
 * same copy, so it wraps and scales line for line with the real one; a title
 * that has a copy always gets the wrapper, even at full size, for that reason.
 * Pure.
 */
class HeadlineFit
{
    const CLASS_NAME = 'sf-fit';

    /** Longest title, as a share of the demo's, kept at the kit's size. */
    const SLACK = 1.15;

    /** Smallest scale, in percent. */
    const MIN = 60;

    /**
     * @param string $demo Demo copy of the slot (inner HTML).
     * @param string $text New copy (inner HTML).
     *
     * @return int Scale in percent, a multiple of 5 from MIN to 100.
     */
    public static function scale($demo, $text)
    {
        $designed = self::length($demo);
        $length   = self::length($text);

        if ($designed < 1 || $length <= $designed * self::SLACK) {
            return 100;
        }

        $scale = (int) (floor(sqrt($designed * self::SLACK / $length) * 20) * 5);

        return max(self::MIN, min(100, $scale));
    }

    /**
     * Wrap a title's copy for its scale (an earlier wrapper is replaced).
     *
     * @param string $text   Title copy (inner HTML).
     * @param int    $scale  scale().
     * @param bool   $always Wrap even at 100% (the title has a decorative copy).
     *
     * @return string
     */
    public static function wrap($text, $scale, $always = false)
    {
        $text  = self::unwrap($text);
        $scale = (int) $scale;

        if ($scale >= 100 && ! $always) {
            return $text;
        }

        $class = self::CLASS_NAME . ($scale < 100 ? ' ' . self::CLASS_NAME . '-' . $scale : '');

        return '<span class="' . $class . '">' . $text . '</span>';
    }

    /**
     * @param string $text Title copy, maybe wrapped.
     *
     * @return string The copy without a fit wrapper.
     */
    public static function unwrap($text)
    {
        $text = trim((string) $text);

        if (preg_match('#^<span class="' . self::CLASS_NAME . '(?: ' . self::CLASS_NAME . '-\d+)?">(.*)</span>$#s', $text, $m)) {
            return $m[1];
        }

        return $text;
    }

    /**
     * Fit every H1 of a kit unit the plan rewrote, and give its decorative
     * copies the same words.
     *
     * @param array $texts   Slot id => new copy.
     * @param array $slots   The unit's CopySlots slots (demo copy).
     * @param array $mirrors Kit::mirrorSlots(): copy slot id => heading slot id.
     *
     * @return array The texts, fitted, with every mirror slot set.
     */
    public static function texts(array $texts, array $slots, array $mirrors)
    {
        $mirrored = array_flip(array_values($mirrors));

        foreach ($texts as $id => $text) {
            if (! isset($slots[$id]) || $slots[$id]['type'] !== 'text' || ! preg_match('/^h1\b/', (string) $slots[$id]['label'])) {
                continue;
            }
            $texts[$id] = self::wrap($text, self::scale((string) $slots[$id]['value'], (string) $text), isset($mirrored[$id]));
        }

        foreach ($mirrors as $mirror => $heading) {
            if (isset($texts[$heading])) {
                $texts[$mirror] = $texts[$heading];
            }
        }

        return $texts;
    }

    /**
     * CSS for the wrapper (part of HeadingSafety::css()).
     *
     * @return string
     */
    public static function css()
    {
        $css = 'body .' . self::CLASS_NAME . '{display:block;white-space:normal!important;text-wrap:balance;overflow-wrap:break-word;hyphens:auto;min-width:0}';
        for ($scale = 95; $scale >= self::MIN; $scale -= 5) {
            $css .= 'body .' . self::CLASS_NAME . '-' . $scale . '{zoom:' . rtrim(number_format($scale / 100, 2, '.', ''), '0') . '}';
        }

        return $css;
    }

    /**
     * @param string $html Copy.
     *
     * @return int Characters a reader sees.
     */
    public static function length($html)
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<br', ' <br', (string) $html)), ENT_QUOTES, 'UTF-8')));

        return function_exists('mb_strlen') ? mb_strlen($plain) : strlen($plain);
    }
}
