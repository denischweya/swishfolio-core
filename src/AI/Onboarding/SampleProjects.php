<?php
/**
 * Sample projects for a new site, and clearing out a template's demo ones.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

use SwishfolioCore\AI\Generation\Kit;
use SwishfolioCore\AI\Generation\PatternLibrary;
use SwishfolioCore\AI\Generation\ProjectsPolicy;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolContext;

/**
 * A site's projects fall into three groups:
 *
 *  real    the owner's: never touched;
 *  sample  written by a build, marked with META so they can be found and
 *          replaced later;
 *  demo    a cloned template's projects (the demo business's work): unchanged
 *          since the site was created, or titled like a kit's demo project.
 *
 * ensure() gives a site that lists projects a set of sample ones, unless it
 * has real ones: demo and earlier sample projects are rewritten in place
 * (keeping their cover, which already suits the template), extra ones are
 * created with the kit's project covers, leftovers go to the bin. retire()
 * bins demo and sample projects when the site shows none. Every write goes
 * through the run's journal, so the build's undo takes them back too.
 */
class SampleProjects
{
    const POST_TYPE = 'sfcore_project';
    const META      = '_swish_sample';
    const ASSET     = '_swish_kit_asset';
    const TOOL      = 'swishfolio/sample-projects';

    /** @var MutationJournal */
    private $journal;

    /** @var ToolContext */
    private $context;

    public function __construct(MutationJournal $journal, ToolContext $context)
    {
        $this->journal = $journal;
        $this->context = $context;
    }

    /**
     * @param array[]  $items Sample projects (ProjectsPolicy::items()); [] for placeholders.
     * @param Kit|null $kit   Kit whose covers new projects get.
     *
     * @return array {action, written, created, removed}
     */
    public function ensure(array $items, Kit $kit = null)
    {
        $groups = self::classify();

        if ($groups['real'] !== []) {
            return ['action' => 'kept-real', 'written' => 0, 'created' => 0, 'removed' => 0];
        }

        $pool  = array_merge($groups['sample'], $groups['demo']);
        $items = ProjectsPolicy::items($items);

        if ($items === []) {
            // Sample projects already written for this site stay as they are.
            if ($groups['demo'] === [] && $groups['sample'] !== []) {
                return ['action' => 'kept-sample', 'written' => 0, 'created' => 0, 'removed' => 0];
            }
            $items = ProjectsPolicy::placeholders(count($pool) ?: ProjectsPolicy::MIN_ITEMS);
        }

        $created = 0;

        foreach ($items as $i => $item) {
            if (isset($pool[$i])) {
                $this->write((int) $pool[$i], $item, $i, $kit);
                continue;
            }
            $this->write(0, $item, $i, $kit);
            $created++;
        }

        $removed = $this->bin(array_slice($pool, count($items)));

        return ['action' => 'written', 'written' => count($items), 'created' => $created, 'removed' => $removed];
    }

    /**
     * Bins demo and sample projects (never real ones).
     *
     * @return int How many.
     */
    public function retire()
    {
        $groups = self::classify();

        return $this->bin(array_merge($groups['sample'], $groups['demo']));
    }

    /**
     * @return array {real: int[], sample: int[], demo: int[]} Post IDs, in menu order.
     */
    public static function classify()
    {
        $posts  = get_posts([
            'post_type'        => self::POST_TYPE,
            'post_status'      => ['publish', 'draft', 'pending', 'future', 'private'],
            'posts_per_page'   => 200,
            'orderby'          => 'menu_order date',
            'order'            => 'ASC',
            'no_found_rows'    => true,
            'suppress_filters' => true,
        ]);
        $groups = ['real' => [], 'sample' => [], 'demo' => []];
        $born   = self::siteCreated();
        $titles = self::demoTitles();

        foreach ($posts as $post) {
            if (get_post_meta($post->ID, self::META, true)) {
                $groups['sample'][] = (int) $post->ID;
            } elseif (($born > 0 && strtotime($post->post_modified_gmt . ' UTC') <= $born) || isset($titles[strtolower(trim($post->post_title))])) {
                $groups['demo'][] = (int) $post->ID;
            } else {
                $groups['real'][] = (int) $post->ID;
            }
        }

        return $groups;
    }

    /**
     * When this site was created (a template clone's demo content is older), or 0.
     *
     * @return int
     */
    private static function siteCreated()
    {
        if (! is_multisite() || is_main_site()) {
            return 0;
        }

        $site = get_site();

        return $site && $site->registered && $site->registered !== '0000-00-00 00:00:00' ? (int) strtotime($site->registered . ' UTC') : 0;
    }

    /**
     * @return array lowercased title => true, for every kit's demo projects.
     */
    private static function demoTitles()
    {
        $titles = [];

        foreach (PatternLibrary::shipped()->kits() as $kit) {
            foreach ($kit->getDemoProjectTitles() as $title) {
                $titles[strtolower(trim($title))] = true;
            }
        }

        return $titles;
    }

    /**
     * Writes one sample project, over an existing demo or sample one or as a new post.
     *
     * @param int      $postId Existing post, or 0.
     * @param array    $item   Project.
     * @param int      $order  Menu order.
     * @param Kit|null $kit    Kit for a cover.
     *
     * @return int Post ID.
     */
    private function write($postId, array $item, $order, Kit $kit = null)
    {
        $content = '';
        foreach ($item['body'] as $para) {
            $content .= "<!-- wp:paragraph -->\n<p>" . esc_html($para) . "</p>\n<!-- /wp:paragraph -->\n\n";
        }

        $post = [
            'post_type'    => self::POST_TYPE,
            'post_status'  => 'publish',
            'post_title'   => $item['title'],
            'post_name'    => sanitize_title($item['title']),
            'post_excerpt' => $item['excerpt'],
            'post_content' => trim($content),
            'menu_order'   => (int) $order,
        ];

        if ($postId > 0) {
            $post['ID'] = $postId;
            $this->journal->updatePost($this->context, self::TOOL, wp_slash($post), __('Wrote a sample project', 'swishfolio-core'));
        } else {
            $postId = $this->journal->createPost($this->context, self::TOOL, wp_slash($post), __('Added a sample project', 'swishfolio-core'));
        }

        // The demo business's details go; the mark lets the owner find and replace it.
        $meta = [
            self::META                => '1',
            '_sfcore_project_status'  => 'completed',
            '_sfcore_client_name'     => '',
            '_sfcore_project_url'     => '',
            '_sfcore_completion_date' => '',
            '_sfcore_skills'          => [],
        ];
        foreach ($meta as $key => $value) {
            if (get_post_meta($postId, $key, true) !== $value) {
                $this->journal->updateMeta($this->context, self::TOOL, $postId, $key, $value);
            }
        }

        $terms = [];
        if ($item['category'] !== '') {
            $term = term_exists($item['category'], 'sfcore_project_cat');
            $term = $term ? $term : wp_insert_term($item['category'], 'sfcore_project_cat');
            if (! is_wp_error($term) && isset($term['term_id'])) {
                $terms[] = (int) $term['term_id'];
            }
        }
        $this->journal->setTerms($this->context, self::TOOL, $postId, 'sfcore_project_cat', $terms);
        $this->journal->setTerms($this->context, self::TOOL, $postId, 'sfcore_project_type', []);

        if (! get_post_thumbnail_id($postId) && $kit !== null) {
            $cover = $this->cover($kit, (int) $order);
            if ($cover > 0) {
                $this->journal->updateMeta($this->context, self::TOOL, $postId, '_thumbnail_id', $cover);
            }
        }

        return (int) $postId;
    }

    /**
     * One of the kit's project covers in the media library, imported once.
     *
     * @param Kit $kit   Kit.
     * @param int $index Project index.
     *
     * @return int Attachment ID, or 0.
     */
    private function cover(Kit $kit, $index)
    {
        $names = $kit->getProjectCovers();

        if ($names === []) {
            // A kit without demo projects: its own photos, which still suit its look.
            $names = array_keys($kit->getAssets());
        }

        if ($names === []) {
            return 0;
        }

        $name = $names[$index % count($names)];
        $key  = $kit->getSlug() . '/' . $name;

        $found = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => self::ASSET, // phpcs:ignore WordPress.DB.SlowDBQuery
            'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
            'no_found_rows'  => true,
        ]);

        if ($found) {
            return (int) $found[0];
        }

        $path = $kit->assetPath($name);

        if ($path === null || ! is_readable($path)) {
            return 0;
        }

        $upload = wp_upload_bits(basename($path), null, (string) file_get_contents($path));

        if (! empty($upload['error'])) {
            return 0;
        }

        $type = wp_check_filetype($upload['file']);
        $id   = wp_insert_attachment([
            'post_mime_type' => $type['type'] ? $type['type'] : 'image/jpeg',
            'post_title'     => __('Sample project image', 'swishfolio-core'),
            'post_status'    => 'inherit',
        ], $upload['file']);

        if (is_wp_error($id) || ! $id) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));
        update_post_meta($id, '_wp_attachment_image_alt', __('Sample project image', 'swishfolio-core'));
        update_post_meta($id, self::ASSET, $key);
        update_post_meta($id, self::META, '1');
        $this->journal->recordCreated($this->context, self::TOOL, (int) $id, __('Added a sample project image', 'swishfolio-core'));

        return (int) $id;
    }

    /**
     * @param int[] $ids Projects to move to the bin.
     *
     * @return int How many.
     */
    private function bin(array $ids)
    {
        foreach ($ids as $id) {
            $this->journal->updatePost($this->context, self::TOOL, ['ID' => (int) $id, 'post_status' => 'trash'], __('Removed a template project', 'swishfolio-core'));
        }

        return count($ids);
    }
}
