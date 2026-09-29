<?php
/**
 * Executes an approved site plan.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

use SwishfolioCore\Actions\ActionExecutor;
use SwishfolioCore\Actions\Handlers\ExecutionContext;
use SwishfolioCore\Actions\Handlers\ExecutionException;

/**
 * Ordering is forced by one fact: a plan refers to pages by key, because real
 * post IDs do not exist until the pages are created.
 *
 *  1. every page is created as a draft, which is what turns keys into IDs;
 *  2. pages whose copy contained an internal page reference are rewritten, now
 *     that the pages they point at can be resolved to permalinks;
 *  3. navigation, front page and style variation run last, because each of them
 *     needs IDs from step 1.
 *
 * The IDs a run created are kept in a transient before anything else is reported,
 * so "undo this generation" is always possible even when a later step failed.
 */
class PlanExecutor
{
    const UNDO_TRANSIENT_PREFIX = 'sfcore_ai_gen_run_';
    const UNDO_TTL              = 86400;

    /** @var BlockComposer */
    private $composer;

    /** @var ActionExecutor */
    private $executor;

    public function __construct(BlockComposer $composer, ActionExecutor $executor)
    {
        $this->composer = $composer;
        $this->executor = $executor;
    }

    /**
     * @param array $plan   Validated plan.
     * @param int   $userId User the run is attributed to.
     *
     * @throws ExecutionException When any action is refused or fails.
     *
     * @return array Report of what was written.
     */
    public function execute(array $plan, $userId)
    {
        $context = new ExecutionContext($userId);

        // Pages the plan chose to leave alone already have IDs; seeding them now
        // is what lets navigation and the front page point at them.
        foreach ($plan['pages'] as $page) {
            if (! empty($page['existingPostId'])) {
                $context->setPageId($page['key'], (int) $page['existingPostId']);
            }
        }

        $composed = [];

        foreach ($plan['pages'] as $index => $page) {
            $composed[$index] = $this->composer->compose($page['sections']);
        }

        try {
            $this->executor->execute($this->createActions($plan, $composed), $context);
            $this->executor->execute($this->relinkActions($plan, $composed, $context), $context);
            $this->executor->execute($this->siteActions($plan, $context), $context);
        } catch (ExecutionException $e) {
            $this->rememberRun($userId, $e->getCreatedIds());

            throw $e;
        }

        $this->rememberRun($userId, $context->getCreatedIds());

        return [
            'summary'    => isset($plan['summary']) ? (string) $plan['summary'] : '',
            'pages'      => $this->describePages($plan, $context),
            'createdIds' => $context->getCreatedIds(),
            'actions'    => $context->getResults(),
        ];
    }

    /**
     * @param array $plan     Validated plan.
     * @param array $composed Composed content per page index.
     *
     * @return array
     */
    private function createActions(array $plan, array $composed)
    {
        $actions = [];

        foreach ($plan['pages'] as $index => $page) {
            if (! empty($page['existingPostId'])) {
                continue;
            }

            $actions[] = [
                'type'     => 'create_page',
                'key'      => $page['key'],
                'title'    => $page['title'],
                'slug'     => $page['slug'],
                'template' => isset($page['template']) ? $page['template'] : null,
                'content'  => $this->placeholderLinks($composed[$index]),
            ];
        }

        return $actions;
    }

    /**
     * Second pass: rewrite only the pages that referred to another page.
     *
     * @param array            $plan     Validated plan.
     * @param array            $composed Composed content per page index.
     * @param ExecutionContext $context  Run state.
     *
     * @return array
     */
    private function relinkActions(array $plan, array $composed, ExecutionContext $context)
    {
        $actions = [];

        foreach ($plan['pages'] as $index => $page) {
            // A page carrying existingPostId was matched by PlanCollisionStage
            // with onConflict "skip" — the owner asked us to leave it alone. We
            // never created its content, so we must never write over it either.
            // Mirrors the same guard in createActions().
            if (! empty($page['existingPostId'])) {
                continue;
            }

            if (strpos($composed[$index], '{{page:') === false) {
                continue;
            }

            $postId = $context->getPageId($page['key']);

            if ($postId === null) {
                continue;
            }

            $actions[] = [
                'type'    => 'update_page',
                'postId'  => $postId,
                'content' => $this->resolveLinks($composed[$index], $context),
            ];
        }

        return $actions;
    }

    /**
     * @param array            $plan    Validated plan.
     * @param ExecutionContext $context Run state.
     *
     * @return array
     */
    private function siteActions(array $plan, ExecutionContext $context)
    {
        if (! isset($plan['site'])) {
            return [];
        }

        $site    = $plan['site'];
        $actions = [];
        $items   = [];

        foreach ($site['navigation'] as $key) {
            $postId = $context->getPageId($key);

            if ($postId === null) {
                continue;
            }

            $items[] = [
                'label'  => $this->pageTitle($plan, $key),
                'url'    => $this->permalink($postId),
                'postId' => $postId,
            ];
        }

        if ($items !== []) {
            $actions[] = [
                'type'  => 'set_navigation',
                'title' => isset($site['title']) && $site['title'] !== ''
                    ? $site['title']
                    : __('Main Navigation', 'swishfolio-core'),
                'items' => $items,
            ];
        }

        $frontPageId = $context->getPageId($site['frontPage']);

        if ($frontPageId !== null) {
            $blogPageId = isset($site['blogPage']) && $site['blogPage'] !== ''
                ? $context->getPageId($site['blogPage'])
                : null;

            $actions[] = [
                'type'       => 'set_front_page',
                'postId'     => $frontPageId,
                'blogPageId' => $blogPageId === null ? 0 : $blogPageId,
            ];
        }

        if (isset($site['styleVariation']) && (string) $site['styleVariation'] !== '') {
            $actions[] = [
                'type'  => 'set_style_variation',
                'title' => (string) $site['styleVariation'],
            ];
        }

        return $actions;
    }

    /**
     * An unresolvable internal link becomes "#" rather than visible braces.
     *
     * @param string $content Composed markup.
     *
     * @return string
     */
    private function placeholderLinks($content)
    {
        return preg_replace('/\{\{page:[a-z0-9-]+\}\}/', '#', $content);
    }

    /**
     * @param string           $content Composed markup.
     * @param ExecutionContext $context Run state.
     *
     * @return string
     */
    private function resolveLinks($content, ExecutionContext $context)
    {
        $pageIds = $context->getPageIds();
        $search  = [];
        $replace = [];

        foreach ($pageIds as $key => $postId) {
            $search[]  = '{{page:' . $key . '}}';
            $replace[] = $this->permalink($postId);
        }

        $content = $search === [] ? $content : str_replace($search, $replace, $content);

        return $this->placeholderLinks($content);
    }

    /**
     * The permalink the page will have once published, not the draft preview URL,
     * because this string is written into content and never revisited.
     *
     * @param int $postId Page ID.
     *
     * @return string
     */
    private function permalink($postId)
    {
        if (function_exists('get_page_link')) {
            return (string) get_page_link($postId, false, true);
        }

        return (string) get_permalink($postId);
    }

    /**
     * @param array  $plan Validated plan.
     * @param string $key  Page key.
     *
     * @return string
     */
    private function pageTitle(array $plan, $key)
    {
        foreach ($plan['pages'] as $page) {
            if ($page['key'] === $key) {
                return (string) $page['title'];
            }
        }

        return (string) $key;
    }

    /**
     * @param array            $plan    Validated plan.
     * @param ExecutionContext $context Run state.
     *
     * @return array
     */
    private function describePages(array $plan, ExecutionContext $context)
    {
        $created  = $context->getCreatedIds();
        $described = [];

        foreach ($plan['pages'] as $page) {
            $postId = $context->getPageId($page['key']);

            $described[] = [
                'key'      => $page['key'],
                'title'    => $page['title'],
                'postId'   => $postId,
                'created'  => $postId !== null && in_array($postId, $created, true),
                'reused'   => ! empty($page['existingPostId']),
                'editUrl'  => $postId === null || ! function_exists('get_edit_post_link')
                    ? null
                    : get_edit_post_link($postId, 'raw'),
                'previewUrl' => $postId === null ? null : $this->permalink($postId),
            ];
        }

        return $described;
    }

    /**
     * @param int   $userId User the run belongs to.
     * @param int[] $ids    Posts the run created.
     *
     * @return void
     */
    private function rememberRun($userId, array $ids)
    {
        set_transient(
            self::UNDO_TRANSIENT_PREFIX . (int) $userId,
            ['ids' => $ids, 'at' => time()],
            self::UNDO_TTL
        );
    }
}
