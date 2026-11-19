<?php
/**
 * House style: no em dashes or spaced dashes in AI-written copy.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Copy;

/**
 * Prompts ask for no em dashes, and models still write them. This is the
 * deterministic backstop every piece of AI-written copy passes through
 * (plan slots, rewrites of pages, headers and footers, alt text, a custom
 * design's name and summary). Pure.
 *
 * What counts as a dash used as punctuation:
 *
 *   - an em dash or horizontal bar, spaced or not ("fresh—always");
 *   - two or more hyphens ("fresh -- always");
 *   - an en dash with a space on either side ("fresh – always");
 *   - a hyphen with a space on both sides ("fresh - always").
 *
 * An unspaced en dash or hyphen is a range or a compound word and stays
 * ("9:00–12:00", "Tue–Sun", "well-known").
 *
 * Each one is rewritten by its context, never left as " - ":
 *
 *   - figures or day / month names on both sides: a range, "9–5", "Mon–Fri";
 *   - at the start or end of the text: dropped ("— Ada" is "Ada");
 *   - after punctuation already there: just a space;
 *   - two in one sentence (an aside): commas;
 *   - after a short label of at most four words: ": " ("Tuesday: closed");
 *   - before a capital letter: a full stop;
 *   - anything else: ", ".
 */
class Dashes
{
    /** A dash used as punctuation, with the spaces around it. */
    const PATTERN = '/\s*(?:[\x{2014}\x{2015}]|-{2,})\s*|\s+[\x{2013}\x{2012}]\s*|\s*[\x{2013}\x{2012}]\s+|\s+-\s+/u';

    const DAYS = 'mon|tue|tues|wed|thu|thur|thurs|fri|sat|sun|monday|tuesday|wednesday|thursday|friday|saturday|sunday|jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec|january|february|march|april|june|july|august|september|october|november|december';

    /**
     * @param string $text Plain text or HTML.
     *
     * @return bool Whether it holds a dash used as punctuation.
     */
    public static function has($text)
    {
        return (bool) preg_match(self::PATTERN, self::decodeDashes((string) $text));
    }

    /**
     * Plain text (an attribute value, a title, a summary).
     *
     * @param string $text Text.
     *
     * @return string
     */
    public static function text($text)
    {
        $text = (string) $text;
        // A link target or address is not copy.
        if (preg_match('#^\s*(?:[a-z][a-z0-9+.-]*:|/|\#)#i', $text) && ! preg_match('/\s/', trim($text))) {
            return $text;
        }

        $decoded = self::decodeDashes($text);
        if (! preg_match(self::PATTERN, $decoded)) {
            return $text;
        }

        return self::tidy(self::rewrite([$decoded])[0]);
    }

    /**
     * Inline HTML (a slot's inner HTML): only text between tags changes, with
     * the words on either side of a tag read as one sentence.
     *
     * @param string $html HTML.
     *
     * @return string
     */
    public static function html($html)
    {
        $html = (string) $html;
        if (! self::has($html)) {
            return $html;
        }

        $parts = preg_split('/(<!--.*?-->|<[^>]*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $texts = [];
        foreach ($parts as $i => $part) {
            if ($part === '' || $part[0] !== '<') {
                $texts[$i] = self::decodeDashes($part);
            }
        }

        // Tags between two texts: a block boundary (<br>, </p>) ends a sentence.
        $fixed = self::rewrite(array_values($texts), self::breaks($parts, array_keys($texts)));
        $n     = 0;
        foreach (array_keys($texts) as $i) {
            $parts[$i] = $fixed[$n++];
        }

        return self::tidy(implode('', $parts));
    }

    /**
     * Every string leaf of a nested array, as HTML.
     *
     * @param mixed $data Data.
     *
     * @return mixed
     */
    public static function deep($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = self::deep($v);
            }

            return $data;
        }

        return is_string($data) ? self::html($data) : $data;
    }

    /**
     * A site plan: summary, site title and tagline, page titles and every
     * section's texts, attribute values and clones.
     *
     * @param array $plan Validated plan.
     *
     * @return array
     */
    public static function plan(array $plan)
    {
        if (isset($plan['summary']) && is_string($plan['summary'])) {
            $plan['summary'] = self::text($plan['summary']);
        }
        foreach (['title', 'tagline'] as $key) {
            if (isset($plan['site'][$key]) && is_string($plan['site'][$key])) {
                $plan['site'][$key] = self::text($plan['site'][$key]);
            }
        }

        foreach (isset($plan['projects']['items']) && is_array($plan['projects']['items']) ? $plan['projects']['items'] : [] as $i => $item) {
            foreach (['title', 'excerpt', 'category'] as $key) {
                if (isset($item[$key]) && is_string($item[$key])) {
                    $plan['projects']['items'][$i][$key] = self::text($item[$key]);
                }
            }
            if (isset($item['body']) && is_array($item['body'])) {
                $plan['projects']['items'][$i]['body'] = array_map([self::class, 'text'], array_map('strval', $item['body']));
            }
        }

        foreach (isset($plan['pages']) && is_array($plan['pages']) ? $plan['pages'] : [] as $p => $page) {
            if (isset($page['title']) && is_string($page['title'])) {
                $plan['pages'][$p]['title'] = self::text($page['title']);
            }
            foreach (isset($page['sections']) && is_array($page['sections']) ? $page['sections'] : [] as $s => $section) {
                $plan['pages'][$p]['sections'][$s] = self::edits((array) $section);
            }
        }

        return $plan;
    }

    /**
     * CopySlots-style edits: texts (HTML), attrs (plain), clones of both,
     * image alt text.
     *
     * @param array $edits Edits.
     *
     * @return array
     */
    public static function edits(array $edits)
    {
        foreach (['texts' => 'html', 'attrs' => 'value'] as $key => $field) {
            if (isset($edits[$key]) && is_array($edits[$key])) {
                $edits[$key] = self::values($edits[$key], $field, $key === 'texts');
            }
        }
        if (isset($edits['images']) && is_array($edits['images'])) {
            foreach ($edits['images'] as $id => $image) {
                if (is_array($image) && isset($image['alt']) && is_string($image['alt'])) {
                    $edits['images'][$id]['alt'] = self::text($image['alt']);
                }
            }
        }
        if (isset($edits['clones']) && is_array($edits['clones'])) {
            foreach ($edits['clones'] as $i => $clone) {
                if (is_array($clone)) {
                    $edits['clones'][$i] = self::edits($clone);
                }
            }
        }

        return $edits;
    }

    /**
     * @param array  $list  id => value, or [{id, html|value}].
     * @param string $field Value key in the list form.
     * @param bool   $html  Values are HTML.
     *
     * @return array
     */
    private static function values(array $list, $field, $html)
    {
        foreach ($list as $k => $v) {
            if (is_string($v)) {
                $list[$k] = $html ? self::html($v) : self::text($v);
            } elseif (is_array($v) && isset($v[$field]) && is_string($v[$field])) {
                $list[$k][$field] = $html ? self::html($v[$field]) : self::text($v[$field]);
            }
        }

        return $list;
    }

    /**
     * @param string[] $texts  Consecutive text runs (tags between them).
     * @param bool[]   $breaks Whether a sentence ends before run i.
     *
     * @return string[]
     */
    private static function rewrite(array $texts, array $breaks = [])
    {
        $count = count($texts);
        for ($i = 0; $i < $count; $i++) {
            if (! preg_match(self::PATTERN, $texts[$i])) {
                continue;
            }

            $before = '';
            for ($j = $i - 1; $j >= 0 && empty($breaks[$j + 1]); $j--) {
                $before = $texts[$j] . $before;
            }
            $after = '';
            for ($j = $i + 1; $j < $count && empty($breaks[$j]); $j++) {
                $after .= $texts[$j];
            }

            $run = $texts[$i];
            $out = '';
            $pos = 0;
            preg_match_all(self::PATTERN, $run, $m, PREG_OFFSET_CAPTURE);
            foreach ($m[0] as $k => $match) {
                // Context is read from the original words, so a pair of dashes is seen as a pair.
                $left  = $before . substr($run, 0, $match[1]);
                $right = substr($run, $match[1] + strlen($match[0])) . $after;
                $out  .= substr($run, $pos, $match[1] - $pos) . self::replacement($left, $right, $match[0]);
                $pos   = $match[1] + strlen($match[0]);
            }
            $texts[$i] = $out . substr($run, $pos);
        }

        return $texts;
    }

    /**
     * @param string $left  Text before the dash (same sentence run).
     * @param string $right Text after it.
     * @param string $dash  The dash with its spaces.
     *
     * @return string
     */
    private static function replacement($left, $right, $dash)
    {
        $l = rtrim($left);
        $r = ltrim($right);
        if ($l === '' || preg_match('/[\n]\s*$/u', $left)) {
            return '';
        }
        if ($r === '' || preg_match('/^[\n]/u', $right)) {
            return preg_match('/\s$/u', $dash) && $right !== '' ? ' ' : '';
        }
        // Ranges: figures (with a currency sign, a time, "9am") or day and month names.
        if (preg_match('/\d[\d:.,]*\s*(?:am|pm|h|%|k)?$/iu', $l) && preg_match('/^[\p{Sc}]?\d/u', $r)) {
            return "\u{2013}";
        }
        if (preg_match('/(?<![\p{L}])(?:' . self::DAYS . ')\.?$/iu', $l) && preg_match('/^(?:' . self::DAYS . ')(?![\p{L}])/iu', $r)) {
            return "\u{2013}";
        }
        if (preg_match('/[.,;:!?(\[]$/u', $l)) {
            return ' ';
        }

        // The sentence this dash sits in.
        $sentenceLeft  = (string) preg_replace('/^.*[.!?]\s+/su', '', $l);
        $sentenceRight = (string) preg_replace('/[.!?](\s.*)?$/su', '', $r);
        // A range further along ("7am – 6pm") is not the other half of a pair.
        $noRanges = static function ($t) {
            return (string) preg_replace('/\d\s*(?:am|pm)?\s*(?:[\x{2013}\x{2014}-]+)\s*[\p{Sc}]?\d/iu', '0', $t);
        };
        if (preg_match(self::PATTERN, $noRanges($sentenceLeft) . ' ') || preg_match(self::PATTERN, ' ' . $noRanges($sentenceRight))) {
            // One of a pair: an aside, set off with commas.
            return ', ';
        }
        $words = count(preg_split('/\s+/u', trim(strip_tags($sentenceLeft))));
        if (preg_match('/^\p{Lu}/u', $r) && ! preg_match('/^I\b/u', $r) && $words > 4) {
            return '. ';
        }
        if ($words <= 4 && strpos($sentenceLeft, ':') === false) {
            return ': ';
        }

        return ', ';
    }

    /**
     * @param string[] $parts   preg_split parts (texts and tags).
     * @param int[]    $indexes Indexes of the text parts.
     *
     * @return bool[] By text position: a block-level tag or <br> precedes it.
     */
    private static function breaks(array $parts, array $indexes)
    {
        $out  = [];
        $prev = null;
        foreach ($indexes as $n => $i) {
            if ($prev !== null) {
                $between = implode('', array_slice($parts, $prev + 1, $i - $prev - 1));
                $out[$n] = (bool) preg_match('#<(?:br|/?(?:p|li|h[1-6]|div|ul|ol|blockquote|figcaption|section))\b#i', $between);
            }
            $prev = $i;
        }

        return $out;
    }

    /**
     * Dash entities to characters, so they are seen.
     *
     * @param string $text Text.
     *
     * @return string
     */
    private static function decodeDashes($text)
    {
        return str_ireplace(['&mdash;', '&#8212;', '&#x2014;', '&ndash;', '&#8211;', '&#x2013;', '&horbar;', '&#8213;'], ["\u{2014}", "\u{2014}", "\u{2014}", "\u{2013}", "\u{2013}", "\u{2013}", "\u{2015}", "\u{2015}"], (string) $text);
    }

    /**
     * Clean-up after replacement: no doubled punctuation or spaces.
     *
     * @param string $text Text.
     *
     * @return string
     */
    private static function tidy($text)
    {
        $text = (string) preg_replace('/ {2,}/', ' ', $text);
        $text = (string) preg_replace('/\s+([,.;:!?])/u', '$1', $text);
        $text = (string) preg_replace('/([,;:])[,;:]+/u', '$1', $text);
        $text = (string) preg_replace('/[,;:]([.!?])/u', '$1', $text);
        $text = (string) preg_replace('/\.\./u', '.', $text);
        // A full stop we added before a capital that was already after a colon.
        $text = (string) preg_replace('/:\. /u', ': ', $text);

        return $text;
    }
}
