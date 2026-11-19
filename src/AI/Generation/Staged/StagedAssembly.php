<?php
/**
 * Pure shape work for a staged site plan: outline + pages into one plan.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation\Staged;

use SwishfolioCore\AI\Generation\PlanSchema;

/**
 * The plan that comes out is exactly the shape the single-call planner
 * produced (schemaVersion, summary, kit, site, pages), so /ai/generate, the
 * kit finishing stages and undo see no difference. Keys, titles and slugs
 * always come from the outline, never from a page call, which is what keeps
 * navigation, links and the front page consistent across pages written in
 * parallel.
 */
class StagedAssembly
{
    /**
     * What one page call is told about its page.
     *
     * @param array $page Outline page.
     *
     * @return array
     */
    public static function pageSpec(array $page)
    {
        return [
            'key'      => $page['key'],
            'title'    => $page['title'],
            'slug'     => $page['slug'],
            'purpose'  => $page['purpose'],
            'sections' => $page['sections'],
        ];
    }

    /**
     * What every page call is told about the whole site (shared facts and the
     * page list, without other pages' section lists).
     *
     * @param array $outline Clean outline.
     *
     * @return array
     */
    public static function siteSpec(array $outline)
    {
        $pages = [];

        foreach ($outline['pages'] as $page) {
            $pages[] = ['key' => $page['key'], 'title' => $page['title'], 'purpose' => $page['purpose']];
        }

        return [
            'summary'    => $outline['summary'],
            'site'       => $outline['site'],
            'facts'      => $outline['facts'],
            'pages'      => $pages,
        ];
    }

    /**
     * One page call's reply, with the outline's identity forced onto it.
     *
     * @param array $outlinePage Outline page.
     * @param array $decoded     Decoded page-plan reply.
     *
     * @return array Raw page (not validated).
     */
    public static function mergePage(array $outlinePage, array $decoded)
    {
        $page = isset($decoded['pages'][0]) && is_array($decoded['pages'][0]) ? $decoded['pages'][0] : [];

        $page['key']   = $outlinePage['key'];
        $page['title'] = $outlinePage['title'];
        $page['slug']  = $outlinePage['slug'];
        unset($page['status'], $page['existingPostId']);

        if (! empty($outlinePage['onConflict'])) {
            $page['onConflict'] = $outlinePage['onConflict'];
        } else {
            unset($page['onConflict']);
        }

        if (! isset($page['sections']) || ! is_array($page['sections'])) {
            $page['sections'] = [];
        }

        return $page;
    }

    /**
     * A single-page plan, for judging one page on its own.
     *
     * @param array  $page Page.
     * @param string $kit  Kit slug.
     *
     * @return array
     */
    public static function pagePlan(array $page, $kit)
    {
        unset($page['existingPostId']);

        return [
            'schemaVersion' => PlanSchema::SCHEMA_VERSION,
            'summary'       => '',
            'kit'           => (string) $kit,
            'pages'         => [$page],
        ];
    }

    /**
     * The site plan from the pages that were written, in outline order.
     * Navigation keeps only pages that exist.
     *
     * @param array  $outline Clean outline.
     * @param array  $pages   key => validated page.
     * @param string $kit     Kit slug.
     *
     * @throws \InvalidArgumentException When the front page is missing.
     *
     * @return array
     */
    public static function sitePlan(array $outline, array $pages, $kit)
    {
        $front = $outline['site']['frontPage'];

        if (! isset($pages[$front])) {
            throw new \InvalidArgumentException(sprintf('The front page "%s" was not written.', $front));
        }

        $list = [];

        foreach ($outline['pages'] as $outlinePage) {
            if (isset($pages[$outlinePage['key']])) {
                $page = $pages[$outlinePage['key']];
                unset($page['existingPostId']);
                $list[] = $page;
            }
        }

        $navigation = array_values(array_filter($outline['site']['navigation'], static function ($key) use ($pages) {
            return isset($pages[$key]);
        }));

        $site = ['frontPage' => $front, 'navigation' => $navigation];

        if ($outline['site']['title'] !== '') {
            $site['title'] = $outline['site']['title'];
        }
        if ($outline['site']['tagline'] !== '') {
            $site['tagline'] = $outline['site']['tagline'];
        }

        $plan = [
            'schemaVersion' => PlanSchema::SCHEMA_VERSION,
            'summary'       => $outline['summary'],
            'kit'           => (string) $kit,
            'site'          => $site,
            'pages'         => $list,
        ];

        // The outline's projects decision and sample projects, for /ai/generate.
        if (isset($outline['projects']) && is_array($outline['projects'])) {
            $plan['projects'] = $outline['projects'];
        }

        return $plan;
    }

    /**
     * Which page a validation path points at ("pages.3.sections..." => 3).
     *
     * @param string $path Validation path.
     *
     * @return int|null
     */
    public static function pageIndexOf($path)
    {
        return preg_match('/^pages\.(\d+)(\.|$)/', (string) $path, $m) ? (int) $m[1] : null;
    }
}
