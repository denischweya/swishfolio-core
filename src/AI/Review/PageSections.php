<?php
/**
 * A page's top-level sections, by byte offset.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Review;

/**
 * Pure. A generated page is its kit sections, each one top-level block,
 * joined by blank lines (BlockComposer). split() finds them with the block
 * comment delimiters, so removing or replacing one keeps every other byte.
 */
class PageSections
{
    const DELIMITER = '/<!--\s+(\/)?wp:([a-z][a-z0-9_-]*\/)?([a-z][a-z0-9_-]*)\s+(\{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?}\s+)?(\/)?-->/s';

    /**
     * @param string $content Serialized blocks.
     *
     * @return array[] 1-based list of {start, end, name, className}.
     */
    public static function split($content)
    {
        $content = (string) $content;
        $out     = [];
        $depth   = 0;
        $open    = null;

        if (! preg_match_all(self::DELIMITER, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        foreach ($matches as $m) {
            $start   = $m[0][1];
            $end     = $start + strlen($m[0][0]);
            $closer  = isset($m[1]) && $m[1][0] === '/';
            $void    = isset($m[5]) && $m[5][0] === '/';
            $name    = ($m[2][0] !== '' ? $m[2][0] : 'core/') . $m[3][0];
            $attrs   = isset($m[4]) && $m[4][0] !== '' ? json_decode($m[4][0], true) : [];

            if ($closer) {
                $depth = max(0, $depth - 1);
                if ($depth === 0 && $open !== null) {
                    $open['end'] = $end;
                    $out[]       = $open;
                    $open        = null;
                }
                continue;
            }

            if ($depth === 0) {
                $section = [
                    'start'     => $start,
                    'end'       => $end,
                    'name'      => $name,
                    'className' => is_array($attrs) && isset($attrs['className']) ? (string) $attrs['className'] : '',
                ];
                if ($void) {
                    $out[] = $section;
                    continue;
                }
                $open = $section;
            }

            if (! $void) {
                $depth++;
            }
        }

        $list = [];
        foreach ($out as $i => $section) {
            $list[$i + 1] = $section;
        }

        return $list;
    }

    /**
     * Remove and replace sections by their 1-based index.
     *
     * @param string $content Serialized blocks.
     * @param array  $changes index => replacement markup ('' removes it).
     *
     * @return string
     */
    public static function splice($content, array $changes)
    {
        $sections = self::split($content);
        krsort($changes);

        foreach ($changes as $index => $markup) {
            if (! isset($sections[$index])) {
                continue;
            }
            $s     = $sections[$index];
            $start = $s['start'];
            $end   = $s['end'];

            if ($markup === '') {
                // Take the blank line that joined it to the next section with it.
                $after = substr($content, $end, 2);
                $end  += $after === "\n\n" ? 2 : (substr($after, 0, 1) === "\n" ? 1 : 0);
            }

            $content = substr($content, 0, $start) . $markup . substr($content, $end);
        }

        return $content;
    }
}
