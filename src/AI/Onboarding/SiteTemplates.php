<?php
/**
 * The network's site templates ("kits") a brief can be matched to and whose
 * design can be applied to a site.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

/**
 * The list comes from the `swishfolio_site_templates` filter (swishfolio-extra
 * fills it from Ultimate Multisite's templates and the marketing catalog). Each
 * entry is re-checked here: only a blog Ultimate Multisite itself registers as a
 * `site_template` ever counts, so neither a filter nor a request can point the
 * kit code at a customer's site, the main site or the current blog.
 */
class SiteTemplates
{
    /**
     * @return array[] slug => entry, see normalise().
     */
    public static function all()
    {
        /**
         * Site templates the onboarding can match and apply.
         *
         * @param array[] $templates Entries: slug, blogId, name, title, industry, description, look,
         *                           keywords (string[]), thumb, demo, default (bool).
         */
        $raw = (array) apply_filters('swishfolio_site_templates', []);
        $out = [];

        foreach ($raw as $entry) {
            $entry = is_array($entry) ? self::normalise($entry) : null;

            if ($entry === null || isset($out[$entry['slug']]) || ! self::isSiteTemplate($entry['blogId'])) {
                continue;
            }

            $out[$entry['slug']] = $entry;
        }

        return $out;
    }

    /**
     * @param string $slug Template slug.
     *
     * @return array|null
     */
    public static function get($slug)
    {
        $all  = self::all();
        $slug = (string) $slug;

        return isset($all[$slug]) ? $all[$slug] : null;
    }

    /**
     * Whether a blog is one of the network's registered UM site templates.
     *
     * @param int $blogId Blog.
     *
     * @return bool
     */
    public static function isSiteTemplate($blogId)
    {
        $blogId = (int) $blogId;

        if ($blogId <= 0 || ! is_multisite() || $blogId === (int) get_current_blog_id() || is_main_site($blogId) || ! get_site($blogId)) {
            return false;
        }

        if (! function_exists('wu_get_site')) {
            return false;
        }

        $site = wu_get_site($blogId);

        return $site && 'site_template' === $site->get_type();
    }

    /**
     * Pure: one entry with every field present and typed.
     *
     * @param array $entry Raw entry.
     *
     * @return array|null Null when it has no usable slug or blog.
     */
    public static function normalise(array $entry)
    {
        $slug = isset($entry['slug']) ? strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $entry['slug'])) : '';

        if ($slug === '' || empty($entry['blogId']) || (int) $entry['blogId'] <= 0) {
            return null;
        }

        $text = static function ($key, $max = 300) use ($entry) {
            return isset($entry[$key]) && is_scalar($entry[$key]) ? mb_substr(trim((string) $entry[$key]), 0, $max) : '';
        };

        $keywords = [];
        foreach (isset($entry['keywords']) ? (array) $entry['keywords'] : [] as $word) {
            $word = strtolower(trim((string) $word));
            if ($word !== '') {
                $keywords[] = $word;
            }
        }

        return [
            'slug'        => $slug,
            'blogId'      => (int) $entry['blogId'],
            'name'        => $text('name', 80),
            'title'       => $text('title', 80),
            'industry'    => $text('industry', 40),
            'description' => $text('description'),
            'look'        => $text('look'),
            'keywords'    => array_values(array_unique($keywords)),
            'thumb'       => $text('thumb', 500),
            'demo'        => $text('demo', 500),
            'default'     => ! empty($entry['default']),
        ];
    }
}
