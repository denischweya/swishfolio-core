<?php
/**
 * Projects on the template path: keep them (rewritten for the owner) or drop them.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\Generation\PatternLibrary;
use SwishfolioCore\AI\Generation\ProjectsPolicy;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\Write\TrustedMarkup;

/**
 * A cloned template carries the demo business's projects and the sections
 * listing them. The customisation's targets run in parallel, each rewriting
 * one post against a hash of its content, so the decision has to come first:
 *
 * preview  decide() (one short call, cached per brief) says whether this
 *          business shows projects. "remove" also names the pages that only
 *          framed the projects list, which leave the rewrite targets.
 * page or  strip() takes projects sections out of that one post, and links to
 * part     the dropped pages, before its rewrite reads it.
 * projects One extra target: "keep" writes sample projects for the business
 *          over the demo ones (SampleProjects); "remove" bins the demo
 *          projects and the dropped pages and takes them out of the menus.
 */
class TemplateProjects
{
    const KEEP   = 'keep';
    const REMOVE = 'remove';
    const TOOL   = 'swishfolio/sample-projects';

    /** Transient prefix for the decision, per site and brief. */
    const CACHE = 'sfcore_projfit_';

    /**
     * What the projects part of a customisation would be, before any AI.
     *
     * @param array[] $targets TemplateCustomiser::targets().
     *
     * @return array {listed: int[] pages with a projects section, demo: bool}
     */
    public static function survey(array $targets)
    {
        $listed = [];

        foreach ($targets as $target) {
            if ($target['type'] === 'page' && ProjectsPolicy::markupShowsProjects((string) get_post_field('post_content', (int) $target['postId']))) {
                $listed[] = (int) $target['postId'];
            }
        }

        $groups = SampleProjects::classify();

        return ['listed' => $listed, 'demo' => $groups['demo'] !== [] || $groups['sample'] !== [], 'real' => $groups['real'] !== []];
    }

    /**
     * Pages that only framed a projects list: what is left once the list goes
     * is the page header and a call to action. Never the front page.
     *
     * @param int[] $listed Pages with a projects section.
     *
     * @return int[]
     */
    public static function framePages(array $listed)
    {
        $front = (int) get_option('page_on_front');
        $drop  = [];

        foreach ($listed as $id) {
            if ((int) $id === $front) {
                continue;
            }
            $strip = ProjectsPolicy::stripMarkup((string) get_post_field('post_content', (int) $id));
            if ($strip['remaining'] <= ProjectsPolicy::SHELL_SECTIONS) {
                $drop[] = (int) $id;
            }
        }

        return $drop;
    }

    /**
     * Whether the business shows its past work: one short call, cached per brief.
     *
     * @param AIProviderInterface $provider Provider.
     * @param PromptManager       $prompts  Prompts.
     * @param string              $facts    TemplateCustomiser::facts().
     * @param string              $model    Model.
     *
     * @return bool
     */
    public static function decide(AIProviderInterface $provider, PromptManager $prompts, $facts, $model)
    {
        $key    = self::CACHE . md5(get_current_blog_id() . '|' . $facts);
        $cached = get_transient($key);

        if ($cached === self::KEEP || $cached === self::REMOVE) {
            return $cached === self::KEEP;
        }

        $schema = [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['showsProjects', 'reason'],
            'properties'           => [
                'showsProjects' => ['type' => 'boolean'],
                'reason'        => ['type' => 'string'],
            ],
        ];

        $response = $provider->generate(new AIRequest(
            str_replace('%%FACTS%%', (string) $facts, $prompts->task('projects-fit', ['facts' => '%%FACTS%%'])),
            'Decide now.',
            $schema,
            $model,
            300
        ));
        $decoded = $response->getDecoded();
        $keep    = ! isset($decoded['showsProjects']) || ! empty($decoded['showsProjects']);

        set_transient($key, $keep ? self::KEEP : self::REMOVE, DAY_IN_SECONDS);

        return $keep;
    }

    /**
     * Sample projects written for the business.
     *
     * @param AIProviderInterface $provider Provider.
     * @param PromptManager       $prompts  Prompts.
     * @param string              $facts    TemplateCustomiser::facts().
     * @param string              $model    Model.
     * @param int                 $count    How many.
     *
     * @return array {items, response}
     */
    public static function write(AIProviderInterface $provider, PromptManager $prompts, $facts, $model, $count)
    {
        $count  = max(ProjectsPolicy::MIN_ITEMS, min(ProjectsPolicy::MAX_ITEMS, (int) $count));
        $schema = ProjectsPolicy::outlineSchema();
        $schema = [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['items'],
            'properties'           => ['items' => $schema['properties']['items']],
        ];

        $response = $provider->generate(new AIRequest(
            str_replace('%%FACTS%%', (string) $facts, $prompts->task('customise-projects', ['facts' => '%%FACTS%%', 'count' => $count])),
            sprintf('Write %d sample projects.', $count),
            $schema,
            $model,
            4000
        ));
        $decoded = $response->getDecoded();

        return [
            'items'    => array_slice(ProjectsPolicy::items(isset($decoded['items']) ? $decoded['items'] : []), 0, $count),
            'response' => $response,
        ];
    }

    /**
     * Takes projects sections, and links to dropped pages, out of one page or part.
     *
     * @param int             $postId  Target.
     * @param int[]           $drop    Dropped pages.
     * @param MutationJournal $journal Journal.
     * @param ToolContext     $context Run.
     *
     * @return bool Whether it changed.
     */
    public static function strip($postId, array $drop, MutationJournal $journal, ToolContext $context)
    {
        $before = (string) get_post_field('post_content', (int) $postId);
        $after  = 'page' === get_post_type((int) $postId) ? ProjectsPolicy::stripMarkup($before)['markup'] : $before;
        $after  = self::unlink($after, $drop);

        if ($after === $before) {
            return false;
        }

        TrustedMarkup::allow((int) $postId, $before);
        TrustedMarkup::allow((int) $postId, $after);
        $journal->updatePost($context, self::TOOL, wp_slash(['ID' => (int) $postId, 'post_content' => $after]), __('Removed the projects section', 'swishfolio-core'));

        return true;
    }

    /**
     * The "projects" target.
     *
     * @param string          $mode    KEEP or REMOVE.
     * @param int[]           $drop    Pages to drop (REMOVE).
     * @param array[]         $items   Sample projects (KEEP).
     * @param MutationJournal $journal Journal.
     * @param ToolContext     $context Run.
     *
     * @return array Result for the wizard.
     */
    public static function finish($mode, array $drop, array $items, MutationJournal $journal, ToolContext $context)
    {
        $samples = new SampleProjects($journal, $context);

        if ($mode === self::KEEP) {
            $kit    = PatternLibrary::shipped()->kit((string) PatternLibrary::shipped()->current());
            $result = $samples->ensure($items, $kit);

            return [
                'updated' => $result['action'] === 'written',
                'summary' => $result['action'] === 'written'
                    ? sprintf(/* translators: %d: number of projects. */ __('%d sample projects written for your business', 'swishfolio-core'), $result['written'])
                    : __('Your own projects were kept', 'swishfolio-core'),
                'projects' => $result,
            ];
        }

        $removed = $samples->retire();

        foreach ($drop as $id) {
            if ('page' === get_post_type((int) $id) && current_user_can('delete_post', (int) $id)) {
                $journal->updatePost($context, self::TOOL, ['ID' => (int) $id, 'post_status' => 'trash'], __('Removed the projects page', 'swishfolio-core'));
            }
        }

        // The menus: header and footer parts are rewrite targets and strip their own links.
        foreach (get_posts(['post_type' => 'wp_navigation', 'post_status' => 'publish', 'posts_per_page' => 20, 'no_found_rows' => true]) as $nav) {
            if (current_user_can('edit_post', $nav->ID)) {
                self::strip((int) $nav->ID, $drop, $journal, $context);
            }
        }

        return [
            'updated' => $removed > 0 || $drop !== [],
            'summary' => __('Removed the projects section and the template\'s projects', 'swishfolio-core'),
            'removed' => $removed,
            'pages'   => $drop,
        ];
    }

    /**
     * @param string $markup Markup.
     * @param int[]  $drop   Dropped pages.
     *
     * @return string
     */
    private static function unlink($markup, array $drop)
    {
        if ($drop === []) {
            return $markup;
        }

        $urls = [];
        foreach ($drop as $id) {
            $urls[] = (string) get_permalink((int) $id);
            $urls[] = '/' . trim((string) get_post_field('post_name', (int) $id), '/') . '/';
        }

        $contact  = get_page_by_path('contact');
        $fallback = $contact && ! in_array((int) $contact->ID, $drop, true) ? (string) get_permalink($contact) : home_url('/');

        return ProjectsPolicy::unlink($markup, $urls, array_map('intval', $drop), $fallback);
    }
}
