<?php
/**
 * Whether a site shows projects, and what that means for its pages.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

/**
 * Projects (the sfcore_project post type) appear on a site through the
 * portfolio block, which kit sections embed (a unit with needs "projects").
 * A section like that lists nothing until the site has projects, so:
 *
 *  - when the business shows past work (a studio, a contractor's jobs, a
 *    consultancy's case studies), the build keeps its projects sections and
 *    the site gets sample projects written for the business (SampleProjects);
 *  - when it never does (a bakery, a gym), the build drops those sections, and
 *    a page that was only there to list projects (header, list, call to
 *    action) is dropped with them, menu link included.
 *
 * The planner decides (the outline's "projects", or the template path's
 * decision call); everything here is the pure part of applying it.
 */
class ProjectsPolicy
{
    const BLOCK = 'swishfolio-core/portfolio';

    const MAX_ITEMS = 6;
    const MIN_ITEMS = 3;

    /** Roles that only frame a page: a page left with nothing else is dropped. */
    const SHELL_ROLES = ['page-header', 'cta'];

    /** A live page left with this many top-level sections or fewer is only a frame. */
    const SHELL_SECTIONS = 2;

    /**
     * The outline's "projects" property.
     *
     * @return array JSON Schema.
     */
    public static function outlineSchema()
    {
        $text = ['type' => 'string'];

        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['show', 'items'],
            'description'          => 'Whether the site shows examples of past work, and sample projects for it.',
            'properties'           => [
                'show'  => [
                    'type'        => 'boolean',
                    'description' => 'True when this kind of business shows its past work (projects, case studies, jobs, commissions, events). False when it never would.',
                ],
                'items' => [
                    'type'        => 'array',
                    'description' => 'When show is true: 3 to 6 sample projects that fit this business. Empty when show is false.',
                    'items'       => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['title', 'excerpt', 'body', 'category'],
                        'properties'           => [
                            'title'    => ['type' => 'string', 'description' => 'Short project name, a few words. No real client names unless the owner gave them.'],
                            'excerpt'  => ['type' => 'string', 'description' => 'One sentence saying what the work was.'],
                            'body'     => ['type' => 'array', 'items' => $text, 'description' => 'Two or three short plain paragraphs: the brief, what was done, the outcome without invented numbers.'],
                            'category' => ['type' => 'string', 'description' => 'One or two word category, shared between similar projects.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * The outline's decision, cleaned. A reply without one keeps the old
     * behaviour: projects sections allowed, no sample text.
     *
     * @param mixed $raw Outline "projects".
     *
     * @return array {show: bool, items: array[]}
     */
    public static function decision($raw)
    {
        if (! is_array($raw)) {
            return ['show' => true, 'items' => []];
        }

        $show = ! array_key_exists('show', $raw) || ! empty($raw['show']);

        return ['show' => $show, 'items' => $show ? self::items(isset($raw['items']) ? $raw['items'] : []) : []];
    }

    /**
     * Sample project entries, reduced to known fields and capped.
     *
     * @param mixed $raw List of {title, excerpt, body, category}.
     *
     * @return array[] {title, excerpt, body: string[], category}
     */
    public static function items($raw)
    {
        $clean = [];
        $seen  = [];

        foreach (is_array($raw) ? $raw : [] as $item) {
            if (! is_array($item) || count($clean) >= self::MAX_ITEMS) {
                continue;
            }

            $title = self::line(isset($item['title']) ? $item['title'] : '', 120);

            if ($title === '' || isset($seen[strtolower($title)])) {
                continue;
            }

            $body = isset($item['body']) ? $item['body'] : [];
            $body = is_array($body) ? $body : preg_split('/\n\s*\n/', (string) $body);
            $body = array_values(array_filter(array_map(static function ($p) {
                return self::line($p, 1200);
            }, array_slice((array) $body, 0, 5)), 'strlen'));

            $seen[strtolower($title)] = true;
            $clean[]                  = [
                'title'    => $title,
                'excerpt'  => self::line(isset($item['excerpt']) ? $item['excerpt'] : '', 300),
                'body'     => $body,
                'category' => self::line(isset($item['category']) ? $item['category'] : '', 40),
            ];
        }

        return $clean;
    }

    /**
     * Neutral sample projects, when no written ones are available.
     *
     * @param int $count How many.
     *
     * @return array[]
     */
    public static function placeholders($count)
    {
        $count = max(self::MIN_ITEMS, min(self::MAX_ITEMS, (int) $count));
        $names = ['one', 'two', 'three', 'four', 'five', 'six'];
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'title'    => 'Sample project ' . $names[$i],
                'excerpt'  => 'A short summary of a piece of work. Replace it with one of your own projects.',
                'body'     => [
                    'This is a sample project. Describe the brief here: who it was for and what they needed.',
                    'Then say what you did and how you approached it, and finish with the result for the client.',
                    'Add your own photos and replace this text, or delete this sample when you add real projects.',
                ],
                'category' => 'Sample',
            ];
        }

        return $items;
    }

    /**
     * @param PatternUnit|null $unit Kit unit.
     *
     * @return bool Whether the unit lists the site's projects.
     */
    public static function unitShowsProjects($unit)
    {
        return $unit instanceof PatternUnit && in_array('projects', $unit->getNeeds(), true);
    }

    /**
     * @param array $section Plan or outline section ({pattern} or {block}).
     * @param Kit   $kit     The run's kit.
     *
     * @return bool
     */
    public static function sectionShowsProjects(array $section, Kit $kit)
    {
        if (isset($section['block']) && (string) $section['block'] === self::BLOCK) {
            return true;
        }

        return isset($section['pattern']) && self::unitShowsProjects($kit->get((string) $section['pattern']));
    }

    /**
     * @param array $plan Plan (or outline) with pages and sections.
     * @param Kit   $kit  Its kit.
     *
     * @return bool Whether any page lists projects.
     */
    public static function planShowsProjects(array $plan, Kit $kit)
    {
        foreach (isset($plan['pages']) && is_array($plan['pages']) ? $plan['pages'] : [] as $page) {
            foreach (isset($page['sections']) && is_array($page['sections']) ? $page['sections'] : [] as $section) {
                if (is_array($section) && self::sectionShowsProjects($section, $kit)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * An outline page's sections without its projects sections, or null when
     * what is left only frames the page (header and call to action) and the
     * page should go too. The front page always stays.
     *
     * @param array $sections Outline sections.
     * @param Kit   $kit      Kit.
     * @param bool  $isFront  Whether this is the front page.
     *
     * @return array|null
     */
    public static function withoutProjects(array $sections, Kit $kit, $isFront)
    {
        $kept    = [];
        $removed = 0;

        foreach ($sections as $section) {
            if (self::sectionShowsProjects($section, $kit)) {
                $removed++;
                continue;
            }
            $kept[] = $section;
        }

        if ($removed === 0 || $isFront) {
            return $kept;
        }

        foreach ($kept as $section) {
            $unit = isset($section['pattern']) ? $kit->get((string) $section['pattern']) : null;

            if ($unit === null || ! in_array($unit->getRole(), self::SHELL_ROLES, true)) {
                return $kept;
            }
        }

        return null;
    }

    /**
     * The post-check on a written plan: page calls see only their own page
     * and can still name a projects section (a portfolio block for "brands I
     * have worked with") the outline ruled out. When the plan's decision is
     * no projects, those sections go, and a page left only as a frame goes
     * with its menu entry. The front page keeps whatever else it has.
     *
     * @param array $plan Site or page plan (pages, optional site and projects).
     * @param Kit   $kit  Its kit.
     *
     * @return array {plan, removed: int, droppedPages: string[]}
     */
    public static function enforce(array $plan, Kit $kit)
    {
        $out = ['plan' => $plan, 'removed' => 0, 'droppedPages' => []];

        if (! isset($plan['projects']) || self::decision($plan['projects'])['show'] || ! isset($plan['pages']) || ! is_array($plan['pages'])) {
            return $out;
        }

        $front = isset($plan['site']['frontPage']) ? (string) $plan['site']['frontPage'] : '';
        $pages = [];

        foreach ($plan['pages'] as $page) {
            $sections = isset($page['sections']) && is_array($page['sections']) ? $page['sections'] : [];
            $key      = isset($page['key']) ? (string) $page['key'] : '';
            $kept     = self::withoutProjects($sections, $kit, $key === $front);

            if ($kept === null || ($kept === [] && $key !== $front)) {
                $out['removed']       += count($sections);
                $out['droppedPages'][] = $key;
                continue;
            }

            // A front page made of nothing but projects keeps them rather than vanish.
            if ($kept !== []) {
                $out['removed']  += count($sections) - count($kept);
                $page['sections'] = $kept;
            }
            $pages[] = $page;
        }

        $plan['pages'] = $pages;

        if ($out['droppedPages'] !== [] && isset($plan['site']['navigation']) && is_array($plan['site']['navigation'])) {
            $plan['site']['navigation'] = array_values(array_diff($plan['site']['navigation'], $out['droppedPages']));
        }

        $out['plan'] = $plan;

        return $out;
    }

    /**
     * @param string $markup Block markup.
     *
     * @return bool Whether it embeds the portfolio block.
     */
    public static function markupShowsProjects($markup)
    {
        return strpos((string) $markup, '<!-- wp:' . self::BLOCK) !== false;
    }

    /**
     * Removes every top-level section that embeds the portfolio block.
     *
     * @param string $markup Serialized blocks.
     *
     * @return array {markup, removed, remaining} Remaining counts top-level blocks with content.
     */
    public static function stripMarkup($markup)
    {
        $markup = (string) $markup;

        if (! self::markupShowsProjects($markup) || ! function_exists('parse_blocks')) {
            return ['markup' => $markup, 'removed' => 0, 'remaining' => self::countSections($markup)];
        }

        $kept    = [];
        $removed = 0;

        foreach (parse_blocks($markup) as $block) {
            if ($block['blockName'] !== null && self::markupShowsProjects(serialize_block($block))) {
                $removed++;
                continue;
            }
            $kept[] = $block;
        }

        $out = trim(implode('', array_map('serialize_block', $kept)));

        return ['markup' => $out, 'removed' => $removed, 'remaining' => self::countSections($out)];
    }

    /**
     * @param string $markup Serialized blocks.
     *
     * @return int Top-level blocks (whitespace between them not counted).
     */
    public static function countSections($markup)
    {
        if (! function_exists('parse_blocks')) {
            return 0;
        }

        return count(array_filter(parse_blocks((string) $markup), static function ($block) {
            return $block['blockName'] !== null;
        }));
    }

    /**
     * Takes the links to pages that were dropped out of markup: menu links to
     * them go, an entry in a <br>-separated link list goes, any other link
     * points at $fallback instead.
     *
     * @param string $markup   Block markup.
     * @param array  $urls     Dropped page URLs.
     * @param int[]  $ids      Dropped page IDs (navigation-link "id").
     * @param string $fallback Where other links to them now go.
     *
     * @return string
     */
    public static function unlink($markup, array $urls, array $ids, $fallback)
    {
        $markup = (string) $markup;
        $urls   = array_values(array_filter(array_map('strval', $urls)));

        if ($urls === [] && $ids === []) {
            return $markup;
        }

        $variants = [];
        foreach ($urls as $url) {
            $variants[] = rtrim($url, '/');
            $variants[] = rtrim($url, '/') . '/';
        }
        $variants = array_values(array_unique($variants));
        $isDropped = static function ($url) use ($variants) {
            return in_array((string) $url, $variants, true);
        };

        $markup = (string) preg_replace_callback('#<!-- wp:navigation-link (\{.*?\}) /-->\s*#s', static function ($m) use ($isDropped, $ids) {
            $attrs = json_decode($m[1], true);

            if (is_array($attrs) && ((isset($attrs['id']) && in_array((int) $attrs['id'], $ids, true)) || (isset($attrs['url']) && $isDropped($attrs['url'])))) {
                return '';
            }

            return $m[0];
        }, $markup);

        if ($variants === []) {
            return $markup;
        }

        $alt = implode('|', array_map(static function ($u) {
            return preg_quote($u, '#');
        }, $variants));

        // A link in a "<a>Work</a><br><a>Services</a>" list: the entry goes.
        $markup = (string) preg_replace('#<a [^>]*href="(?:' . $alt . ')"[^>]*>[^<]*</a>\s*<br\s*/?>\s*#i', '', $markup);
        $markup = (string) preg_replace('#\s*<br\s*/?>\s*<a [^>]*href="(?:' . $alt . ')"[^>]*>[^<]*</a>#i', '', $markup);

        // Anything else (a button) now points somewhere that exists.
        return (string) preg_replace('#href="(?:' . $alt . ')"#i', 'href="' . str_replace(['\\', '$'], ['\\\\', '\\$'], (string) $fallback) . '"', $markup);
    }

    /**
     * @param mixed $value Raw.
     * @param int   $max   Max length.
     *
     * @return string One plain line.
     */
    private static function line($value, $max)
    {
        $value = is_scalar($value) ? (string) $value : '';
        $value = trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));

        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

