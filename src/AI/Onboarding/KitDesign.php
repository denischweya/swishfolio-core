<?php
/**
 * Applies a site template's design (not its pages) to the current site.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\Write\SetDesignTokensTool;
use SwishfolioCore\Tools\Write\TrustedMarkup;

/**
 * What a "kit" design is, on every template site: the user global styles
 * (palette, fonts with their @font-face files, custom CSS incl. the theme
 * variation's --style-mode), the header and footer template parts, any
 * database templates, and the navigation menus those parts reference.
 *
 * apply() reads all of that from the template blog (only a registered UM
 * site_template, see SiteTemplates), copies the upload files it references
 * (fonts, images) into this site's uploads under swish-kit/<slug>/, rewrites
 * the template's URLs to this site's, and writes it here through the mutation
 * journal under the caller's run ID, so it is one undo in the audit log.
 *
 * It never touches pages, posts, attachments, users or any option other than
 * its own record (swish_kit), so the owner's wizard state, business details,
 * site title, uploads, UM data and credits stay as they are. Re-running it
 * (the same or another kit) updates the same header, footer, styles and menu
 * instead of adding more.
 *
 * Menu links: a template's menu links to its own pages. They are kept as
 * custom links with this site's URL and the same paths (/menu/, /story/), and
 * returned in "links", so whatever builds this site's pages can create pages
 * at those paths or reset the menu.
 *
 * The template-authored markup (inline SVG logos, icon HTML) would not survive
 * kses for an owner without unfiltered_html, so each body written is first
 * recorded with TrustedMarkup: only that exact body, by hash, on that post.
 */
class KitDesign
{
    const TOOL       = 'swishfolio/apply-kit';
    const OPTION     = 'swish_kit';
    const DIR        = 'swish-kit';
    const NAV_META   = '_swish_kit_nav';
    const FILE_TYPES = ['woff2', 'woff', 'ttf', 'otf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];
    const MAX_FILES  = 60;

    /** @var MutationJournal */
    private $journal;

    /**
     * @param MutationJournal $journal Journal for every write.
     */
    public function __construct(MutationJournal $journal)
    {
        $this->journal = $journal;
        TrustedMarkup::register();
    }

    /**
     * @param string      $slug    Template slug (SiteTemplates).
     * @param ToolContext $context Run the writes are journalled under.
     *
     * @throws ToolException When the template is not available or a write fails.
     *
     * @return array {slug, globalStyles, parts, templates, navigation, files, missingFiles, links}
     */
    public function apply($slug, ToolContext $context)
    {
        $template = SiteTemplates::get((string) $slug);

        if ($template === null) {
            throw ToolException::make(self::TOOL, ToolError::TARGET_MISSING, __('That design is not available.', 'swishfolio-core'));
        }

        if (! current_user_can('edit_theme_options')) {
            throw ToolException::make(self::TOOL, ToolError::FORBIDDEN, __('You cannot change the site design.', 'swishfolio-core'));
        }

        $source = self::read($template['blogId']);

        if ($source['stylesheet'] !== get_stylesheet()) {
            throw ToolException::make(self::TOOL, ToolError::WRITE_FAILED, __('That design needs the Swishfolio theme.', 'swishfolio-core'));
        }

        $uploads = wp_upload_dir(null, false);
        $kitBase = trailingslashit($uploads['baseurl']) . self::DIR . '/' . $template['slug'];
        $kitDir  = trailingslashit($uploads['basedir']) . self::DIR . '/' . $template['slug'];

        // Files first: every upload URL the design uses, copied into this site.
        $bodies = array_merge([$source['styles']], wp_list_pluck($source['parts'], 'content'), wp_list_pluck($source['templates'], 'content'), wp_list_pluck($source['navigation'], 'content'));
        $paths  = [];
        foreach ($bodies as $body) {
            $paths = array_merge($paths, self::uploadPaths((string) $body, $source['uploadsUrl']));
        }
        $paths   = array_slice(array_values(array_unique($paths)), 0, self::MAX_FILES);
        $missing = $this->copyFiles($paths, $source['uploadsDir'], $kitDir);

        $map = self::urlMap($source['uploadsUrl'], $kitBase, $source['home'], home_url());

        // Menus the parts reference, as this site's own wp_navigation posts.
        $refs  = [];
        $links = [];
        foreach ($source['navigation'] as $oldId => $nav) {
            $content = self::customLinks(self::rewrite($nav['content'], $map));
            $links   = array_merge($links, self::linkPaths($content));
            $refs[(int) $oldId] = $this->writeNavigation($template['slug'] . ':' . (int) $oldId, $nav['title'], $content, $context);
        }

        $gs = $this->writeGlobalStyles(\SwishfolioCore\AI\Design\HeadingSafety::addTo(self::rewrite($source['styles'], $map)), $context);

        $parts = [];
        foreach ($source['parts'] as $part) {
            $parts[$part['slug']] = $this->writeThemePost('wp_template_part', $part, self::remapNavigationRefs(self::rewrite($part['content'], $map), $refs), $context);
        }

        $templates = [];
        foreach ($source['templates'] as $tpl) {
            $templates[$tpl['slug']] = $this->writeThemePost('wp_template', $tpl, self::remapNavigationRefs(self::rewrite($tpl['content'], $map), $refs), $context);
        }

        $this->journal->updateOption($context, self::TOOL, self::OPTION, [
            'slug'      => $template['slug'],
            'blogId'    => $template['blogId'],
            'appliedAt' => time(),
            'runId'     => $context->getRunId(),
        ], sprintf(/* translators: %s: design name. */ __('Applied the %s design', 'swishfolio-core'), $template['name'] !== '' ? $template['name'] : $template['slug']));

        if (function_exists('wp_clean_theme_json_cache')) {
            wp_clean_theme_json_cache();
        }

        return [
            'slug'         => $template['slug'],
            'globalStyles' => $gs,
            'parts'        => $parts,
            'templates'    => $templates,
            'navigation'   => $refs,
            'files'        => count($paths) - count($missing),
            'missingFiles' => $missing,
            'links'        => array_values(array_unique($links, SORT_REGULAR)),
            'runId'        => $context->getRunId(),
        ];
    }

    /**
     * Everything the design is made of, read on the template blog.
     *
     * @param int $blogId Verified template blog.
     *
     * @return array
     */
    private static function read($blogId)
    {
        switch_to_blog((int) $blogId);

        try {
            $stylesheet = get_stylesheet();
            $uploads    = wp_upload_dir(null, false);
            $themeTax   = [[
                'taxonomy' => 'wp_theme',
                'field'    => 'name',
                'terms'    => $stylesheet,
            ]];

            $styles = get_posts([
                'post_type'      => 'wp_global_styles',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'no_found_rows'  => true,
                'tax_query'      => $themeTax,
            ]);

            $read = static function ($type) use ($themeTax) {
                $out = [];
                foreach (get_posts(['post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => 50, 'no_found_rows' => true, 'tax_query' => $themeTax]) as $post) {
                    $area  = 'wp_template_part' === $type ? wp_get_object_terms($post->ID, 'wp_template_part_area', ['fields' => 'names']) : [];
                    $out[] = [
                        'slug'    => $post->post_name,
                        'title'   => $post->post_title,
                        'content' => (string) $post->post_content,
                        'area'    => is_array($area) && $area ? (string) $area[0] : '',
                    ];
                }
                return $out;
            };

            $parts     = $read('wp_template_part');
            $templates = $read('wp_template');

            $navigation = [];
            foreach (array_merge($parts, $templates) as $item) {
                foreach (self::navigationRefs($item['content']) as $ref) {
                    $nav = get_post($ref);
                    if ($nav && 'wp_navigation' === $nav->post_type && 'publish' === $nav->post_status) {
                        $navigation[$ref] = ['title' => $nav->post_title, 'content' => (string) $nav->post_content];
                    }
                }
            }

            // After switch_to_blog() WordPress builds the upload URL from the
            // request's content URL (this site's host). The template's markup
            // holds its own host, so rebuild the URL on the template's siteurl.
            $uploadsUrl = (string) $uploads['baseurl'];
            $contentUrl = untrailingslashit(content_url());
            if (strpos($uploadsUrl, $contentUrl . '/') === 0) {
                $uploadsUrl = untrailingslashit(site_url()) . '/' . basename(WP_CONTENT_DIR) . substr($uploadsUrl, strlen($contentUrl));
            }

            return [
                'stylesheet' => $stylesheet,
                'home'       => home_url(),
                'uploadsUrl' => $uploadsUrl,
                'uploadsDir' => (string) $uploads['basedir'],
                'styles'     => $styles ? (string) $styles[0]->post_content : '',
                'parts'      => $parts,
                'templates'  => $templates,
                'navigation' => $navigation,
            ];
        } finally {
            restore_current_blog();
        }
    }

    /**
     * @param string      $content Rewritten global styles JSON.
     * @param ToolContext $context Run.
     *
     * @return bool Whether it changed.
     */
    private function writeGlobalStyles($content, ToolContext $context)
    {
        if ($content === '' || ! is_array(json_decode($content, true))) {
            return false;
        }

        $postId = SetDesignTokensTool::globalStylesPostId();

        if ($postId <= 0 || ! current_user_can('edit_post', $postId)) {
            throw ToolException::make(self::TOOL, ToolError::FORBIDDEN, __('You cannot edit this site\'s global styles.', 'swishfolio-core'));
        }

        $prior = (string) get_post_field('post_content', $postId);

        if ($prior === $content) {
            return false;
        }

        TrustedMarkup::allow($postId, $prior);
        TrustedMarkup::allow($postId, $content);

        $this->journal->updatePost($context, self::TOOL, wp_slash(['ID' => $postId, 'post_content' => $content]), __('Applied the design\'s colours, fonts and styles', 'swishfolio-core'));

        return true;
    }

    /**
     * A template part or template: update this site's one with that slug, or create it.
     *
     * @param string      $type    wp_template_part|wp_template.
     * @param array       $item    slug, title, area.
     * @param string      $content Rewritten markup.
     * @param ToolContext $context Run.
     *
     * @return int Post ID.
     */
    private function writeThemePost($type, array $item, $content, ToolContext $context)
    {
        $label    = 'wp_template_part' === $type ? sprintf(/* translators: %s: part name. */ __('Applied the design\'s %s', 'swishfolio-core'), $item['slug']) : sprintf(/* translators: %s: template name. */ __('Applied the design\'s %s template', 'swishfolio-core'), $item['slug']);
        $existing = get_posts([
            'post_type'      => $type,
            'post_status'    => ['publish', 'draft'],
            'name'           => $item['slug'],
            'posts_per_page' => 1,
            'no_found_rows'  => true,
            'tax_query'      => [[
                'taxonomy' => 'wp_theme',
                'field'    => 'name',
                'terms'    => get_stylesheet(),
            ]],
        ]);

        if ($existing) {
            $postId = (int) $existing[0]->ID;

            if (! current_user_can('edit_post', $postId)) {
                throw ToolException::make(self::TOOL, ToolError::FORBIDDEN, __('You cannot edit the site header or footer.', 'swishfolio-core'));
            }
            if ((string) $existing[0]->post_content === $content && 'publish' === $existing[0]->post_status) {
                return $postId;
            }

            TrustedMarkup::allow($postId, (string) $existing[0]->post_content);
        } else {
            // Created empty, then filled: the trusted body is keyed by post ID.
            $postId = $this->journal->createPost($context, self::TOOL, wp_slash([
                'post_type'   => $type,
                'post_status' => 'publish',
                'post_name'   => $item['slug'],
                'post_title'  => $item['title'] !== '' ? $item['title'] : $item['slug'],
            ]), $label);

            wp_set_object_terms($postId, get_stylesheet(), 'wp_theme');
            if ('wp_template_part' === $type) {
                wp_set_object_terms($postId, $item['area'] !== '' ? $item['area'] : 'uncategorized', 'wp_template_part_area');
            }
        }

        TrustedMarkup::allow($postId, $content);

        $this->journal->updatePost($context, self::TOOL, wp_slash([
            'ID'           => $postId,
            'post_content' => $content,
            'post_status'  => 'publish',
        ]), $label);

        return $postId;
    }

    /**
     * The template's menu as this site's wp_navigation post (reused on re-runs).
     *
     * @param string      $key     slug:oldId.
     * @param string      $title   Menu title.
     * @param string      $content Links, already rewritten.
     * @param ToolContext $context Run.
     *
     * @return int Post ID.
     */
    private function writeNavigation($key, $title, $content, ToolContext $context)
    {
        $existing = get_posts([
            'post_type'      => 'wp_navigation',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
            'meta_key'       => self::NAV_META, // phpcs:ignore WordPress.DB.SlowDBQuery
            'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
        ]);

        if ($existing) {
            $postId = (int) $existing[0]->ID;
            if ((string) $existing[0]->post_content !== $content) {
                $this->journal->updatePost($context, self::TOOL, wp_slash(['ID' => $postId, 'post_content' => $content]), __('Updated the menu', 'swishfolio-core'));
            }

            return $postId;
        }

        $postId = $this->journal->createPost($context, self::TOOL, wp_slash([
            'post_type'    => 'wp_navigation',
            'post_status'  => 'publish',
            'post_title'   => $title !== '' ? $title : __('Menu', 'swishfolio-core'),
            'post_content' => $content,
        ]), __('Added the design\'s menu', 'swishfolio-core'));

        // A post the run created: undo trashes it, meta and all.
        update_post_meta($postId, self::NAV_META, $key);

        return $postId;
    }

    /**
     * Copy template upload files into this site's kit folder.
     *
     * @param string[] $paths     Paths relative to the template's uploads folder.
     * @param string   $sourceDir Template uploads folder.
     * @param string   $targetDir This site's kit folder.
     *
     * @return string[] Paths that could not be copied.
     */
    private function copyFiles(array $paths, $sourceDir, $targetDir)
    {
        $missing = [];
        $root    = realpath($sourceDir);

        foreach ($paths as $path) {
            $from = $root ? realpath($root . '/' . $path) : false;

            if (! $from || strpos($from, $root . DIRECTORY_SEPARATOR) !== 0 || ! is_file($from)) {
                $missing[] = $path;
                continue;
            }

            $to = $targetDir . '/' . $path;

            if (is_file($to) && filesize($to) === filesize($from)) {
                continue;
            }

            if (! wp_mkdir_p(dirname($to)) || ! @copy($from, $to)) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
                $missing[] = $path;
            }
        }

        return $missing;
    }

    /**
     * Pure: upload-relative paths of the files a body references, both in
     * plain and JSON-escaped URLs, http or https. Only safe file types and
     * paths without traversal.
     *
     * @param string $body       Markup or JSON.
     * @param string $uploadsUrl Template uploads base URL.
     *
     * @return string[]
     */
    public static function uploadPaths($body, $uploadsUrl)
    {
        $out  = [];
        $base = preg_replace('#^https?://#', '', rtrim((string) $uploadsUrl, '/'));

        if ($base === '') {
            return $out;
        }

        $plain   = preg_quote($base, '#');
        $escaped = preg_quote(str_replace('/', '\/', $base), '#');

        if (preg_match_all('#https?:(?://|\\\\/\\\\/)(?:' . $plain . '|' . $escaped . ')((?:/|\\\\/)[A-Za-z0-9._~%\-/\\\\]+)#', (string) $body, $m)) {
            foreach ($m[1] as $path) {
                // A JSON-escaped quote after the URL (url(\"...\")) leaves a trailing backslash.
                $path = ltrim(rtrim(str_replace('\/', '/', $path), '\\'), '/');
                $path = rawurldecode($path);
                $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                if ($path === '' || strpos($path, '..') !== false || strpos($path, '\\') !== false || ! in_array($ext, self::FILE_TYPES, true)) {
                    continue;
                }
                $out[] = $path;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Pure: replacement pairs, uploads before home (the uploads URL is under
     * home), each in http and https and JSON-escaped form.
     *
     * @param string $sourceUploads Template uploads URL.
     * @param string $targetUploads This site's kit folder URL.
     * @param string $sourceHome    Template home URL.
     * @param string $targetHome    This site's home URL.
     *
     * @return array from => to
     */
    public static function urlMap($sourceUploads, $targetUploads, $sourceHome, $targetHome)
    {
        $map = [];

        foreach ([[$sourceUploads, $targetUploads], [$sourceHome, $targetHome]] as $pair) {
            $from = preg_replace('#^https?://#', '', rtrim((string) $pair[0], '/'));
            $to   = rtrim((string) $pair[1], '/');
            if ($from === '') {
                continue;
            }
            foreach (['https://', 'http://'] as $scheme) {
                $map[$scheme . $from] = $to;
                $map[str_replace('/', '\/', $scheme . $from)] = str_replace('/', '\/', $to);
            }
        }

        return $map;
    }

    /**
     * Pure.
     *
     * @param string $body Markup or JSON.
     * @param array  $map  urlMap().
     *
     * @return string
     */
    public static function rewrite($body, array $map)
    {
        return strtr((string) $body, $map);
    }

    /**
     * Pure: navigation post ids referenced by core/navigation blocks.
     *
     * @param string $content Markup.
     *
     * @return int[]
     */
    public static function navigationRefs($content)
    {
        $out = [];

        if (preg_match_all('#<!-- wp:navigation (\{.*?\}) /?-->#s', (string) $content, $m)) {
            foreach ($m[1] as $json) {
                $attrs = json_decode($json, true);
                if (is_array($attrs) && ! empty($attrs['ref'])) {
                    $out[] = (int) $attrs['ref'];
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Pure: point core/navigation refs at this site's menus; a ref with no
     * menu here is dropped, so the block falls back instead of showing nothing.
     *
     * @param string $content Markup.
     * @param int[]  $map     Template menu id => this site's menu id.
     *
     * @return string
     */
    public static function remapNavigationRefs($content, array $map)
    {
        return (string) preg_replace_callback('#<!-- wp:navigation (\{.*?\}) (/?)-->#s', static function ($m) use ($map) {
            $attrs = json_decode($m[1], true);

            if (! is_array($attrs) || ! isset($attrs['ref'])) {
                return $m[0];
            }

            if (! empty($map[(int) $attrs['ref']])) {
                $attrs['ref'] = (int) $map[(int) $attrs['ref']];
            } else {
                unset($attrs['ref']);
            }

            return '<!-- wp:navigation ' . ($attrs ? self::blockJson($attrs) . ' ' : '') . $m[2] . '-->';
        }, (string) $content);
    }

    /**
     * Pure: navigation links to the template's pages become custom links, so
     * they never point at a post id that means something else here.
     *
     * @param string $content Navigation markup.
     *
     * @return string
     */
    public static function customLinks($content)
    {
        return (string) preg_replace_callback('#<!-- wp:(navigation-link|navigation-submenu) (\{.*?\}) (/?)-->#s', static function ($m) {
            $attrs = json_decode($m[2], true);

            if (! is_array($attrs)) {
                return $m[0];
            }

            unset($attrs['id'], $attrs['type']);
            $attrs['kind'] = 'custom';

            return '<!-- wp:' . $m[1] . ' ' . self::blockJson($attrs) . ' ' . $m[3] . '-->';
        }, (string) $content);
    }

    /**
     * Pure: label and site path of each menu link.
     *
     * @param string $content Navigation markup.
     *
     * @return array[] {label, path}
     */
    public static function linkPaths($content)
    {
        $out = [];

        if (preg_match_all('#<!-- wp:navigation-(?:link|submenu) (\{.*?\}) /?-->#s', (string) $content, $m)) {
            foreach ($m[1] as $json) {
                $attrs = json_decode($json, true);
                if (is_array($attrs) && ! empty($attrs['url'])) {
                    $path  = (string) parse_url((string) $attrs['url'], PHP_URL_PATH);
                    $out[] = ['label' => isset($attrs['label']) ? (string) $attrs['label'] : '', 'path' => $path !== '' ? $path : '/'];
                }
            }
        }

        return $out;
    }

    /**
     * Block comment JSON the way the editor writes it.
     *
     * @param array $attrs Attributes.
     *
     * @return string
     */
    private static function blockJson(array $attrs)
    {
        if (function_exists('serialize_block_attributes')) {
            return serialize_block_attributes($attrs);
        }

        $json = (string) json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // As serialize_block_attributes(): no "--" or "<" ">" "&" inside a comment.
        return str_replace(['--', '<', '>', '&', '\\"'], ['\\u002d\\u002d', '\\u003c', '\\u003e', '\\u0026', '\\u0022'], $json);
    }
}
