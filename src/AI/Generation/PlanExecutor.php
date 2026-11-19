<?php
/**
 * Executes an approved site plan.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

use SwishfolioCore\Actions\ActionExecutor;
use SwishfolioCore\Actions\Handlers\ExecutionContext;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Actions\Handlers\ExecutionException;
use SwishfolioCore\Tools\Write\TrustedMarkup;

/**
 * Ordering is forced by one fact: a plan refers to pages by key, because real
 * post IDs do not exist until the pages are created.
 *
 *  1. every page is created as a draft, which is what turns keys into IDs;
 *  2. pages whose copy contained an internal page reference are rewritten, now
 *     that the pages they point at can be resolved to permalinks;
 *  3. navigation and the front page run last, because each of them needs IDs
 *     from step 1.
 *
 * Kit sections carry inline SVG, forms and the odd script, which kses would
 * strip for an owner without unfiltered_html. Every body this run writes is
 * kit markup plus sanitised copy, so it is vouched for (TrustedMarkup::vouch())
 * just before it is written, and only that exact body passes unfiltered.
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

    /** @var callable|null */
    private $onProgress;

    public function __construct(BlockComposer $composer, ActionExecutor $executor)
    {
        $this->composer = $composer;
        $this->executor = $executor;
    }

    /**
     * @param callable $listener Called with (key, label, detail) as each step starts.
     *
     * @return void
     */
    public function onProgress(callable $listener)
    {
        $this->onProgress = $listener;
    }

    /**
     * @param array $plan   Validated plan.
     * @param int   $userId User the run is attributed to.
     *
     * @throws ExecutionException When any action is refused or fails.
     *
     * @return array Report of what was written.
     */
    public function execute(array $plan, $userId, ToolContext $context = null)
    {
        // A ToolContext, not a bare ExecutionContext: every write in the run is
        // journalled under its run ID, and the three site-level actions are
        // write_published, so the caller's confirmation has to travel with it.
        $context = $context instanceof ToolContext ? $context : ToolContext::forUser($userId);

        // Pages the plan chose to leave alone already have IDs; seeding them now
        // is what lets navigation and the front page point at them.
        foreach ($plan['pages'] as $page) {
            if (! empty($page['existingPostId'])) {
                $context->setPageId($page['key'], (int) $page['existingPostId']);
            }
        }

        $composed = [];

        $this->report(
            'layout',
            __('Laying out your pages', 'swishfolio-core'),
            /* translators: %d: number of pages. */
            sprintf(_n('%d page', '%d pages', count($plan['pages']), 'swishfolio-core'), count($plan['pages']))
        );

        $links = $this->links($plan);

        foreach ($plan['pages'] as $index => $page) {
            $composed[$index] = $this->composer->compose($page['sections'], $links);
        }

        TrustedMarkup::register();

        // One action at a time (the executor runs a batch sequentially anyway)
        // so each page and site setting is its own progress step.
        try {
            foreach ($this->createActions($plan, $composed) as $action) {
                /* translators: %s: page title. */
                $this->report('create-' . $action['key'], sprintf(__('Creating %s', 'swishfolio-core'), $action['title']));
                $this->executor->execute([$action], $context);
            }

            $relink = $this->relinkActions($plan, $composed, $context);

            if ($relink !== []) {
                $this->report('relink', __('Linking pages together', 'swishfolio-core'));
                $this->executor->execute($relink, $context);
            }

            foreach ($this->siteActions($plan, $context) as $action) {
                $this->report('site-' . $action['type'], $this->siteActionLabel($action));
                $this->executor->execute([$action], $context);

                // The front page is only switched once the page is published.
                $results = $context->getResults();
                $last    = end($results);

                if (is_array($last) && ! empty($last['deferred'])) {
                    $this->report(
                        'site-' . $action['type'],
                        __('Home page chosen', 'swishfolio-core'),
                        __('It becomes your front page when you publish it', 'swishfolio-core')
                    );
                }
            }
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

            $content = $this->placeholderLinks($composed[$index]);
            TrustedMarkup::vouch($content);

            // The kit's sections are laid out for the template its own pages use.
            $template = isset($page['template']) && $page['template'] !== null && $page['template'] !== ''
                ? $page['template']
                : $this->composer->getKit()->getPageTemplate();

            $actions[] = [
                'type'     => 'create_page',
                'key'      => $page['key'],
                'title'    => $page['title'],
                'slug'     => $page['slug'],
                'template' => $template === '' ? null : $template,
                'content'  => $content,
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

            $content = $this->resolveLinks($composed[$index], $context);
            TrustedMarkup::vouch($content);

            $actions[] = [
                'type'    => 'update_page',
                'postId'  => $postId,
                'content' => $content,
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

        return $actions;
    }

    /**
     * @param string $key    Step key.
     * @param string $label  Label.
     * @param string $detail Detail.
     *
     * @return void
     */
    private function report($key, $label, $detail = '')
    {
        if ($this->onProgress !== null) {
            call_user_func($this->onProgress, $key, $label, $detail);
        }
    }

    /**
     * @param array $action Site action.
     *
     * @return string
     */
    private function siteActionLabel(array $action)
    {
        switch ($action['type']) {
            case 'set_navigation':
                return __('Setting up the menu', 'swishfolio-core');
            case 'set_front_page':
                return __('Setting your home page', 'swishfolio-core');
        }

        return __('Updating site settings', 'swishfolio-core');
    }

    /**
     * Where the kit's own links ({{link:<template page key>}}) point.
     *
     * A template page key the plan also uses links to that page. Otherwise the
     * template's front page links to the plan's front page, and any other page
     * (in practice a contact, booking or quote page) to the last page in the
     * plan's menu. A single-page plan links to an existing page with the
     * template page's slug when the site has one, and to "#" when it has not.
     *
     * @param array $plan Validated plan.
     *
     * @return array Template page key => "{{page:key}}" or a URL.
     */
    private function links(array $plan)
    {
        $kit   = $this->composer->getKit();
        $keys  = [];
        $links = [];

        foreach ($plan['pages'] as $page) {
            $keys[$page['key']] = true;
        }

        $site     = isset($plan['site']) ? $plan['site'] : null;
        $nav      = $site !== null ? $site['navigation'] : [];
        $fallback = $nav !== [] ? end($nav) : null;

        foreach ($kit->getPages() as $templatePage) {
            $key = (string) $templatePage['key'];

            if (isset($keys[$key])) {
                $links[$key] = '{{page:' . $key . '}}';
            } elseif ($site !== null && $key === $kit->getFrontPage()) {
                $links[$key] = '{{page:' . $site['frontPage'] . '}}';
            } elseif ($site !== null && $fallback !== null) {
                $links[$key] = '{{page:' . $fallback . '}}';
            } else {
                $links[$key] = $this->existingPageUrl(isset($templatePage['slug']) ? (string) $templatePage['slug'] : $key);
            }
        }

        return $links;
    }

    /**
     * @param string $slug Page slug.
     *
     * @return string Permalink of a page with that slug on this site, or "#".
     */
    private function existingPageUrl($slug)
    {
        if (! function_exists('get_page_by_path')) {
            return '#';
        }

        $page = get_page_by_path($slug, OBJECT, 'page');

        return $page ? $this->permalink((int) $page->ID) : '#';
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
