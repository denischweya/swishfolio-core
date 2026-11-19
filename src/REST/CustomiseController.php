<?php
/**
 * REST route for the onboarding wizard's AI customisation.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\REST;

use SwishfolioCore\AI\ModelRouter;
use SwishfolioCore\AI\Onboarding\SampleProjects;
use SwishfolioCore\AI\Onboarding\TemplateCustomiser;
use SwishfolioCore\AI\Onboarding\TemplateProjects;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\AI\UsageLogger;
use SwishfolioCore\Jobs\BackgroundJobs;
use SwishfolioCore\Jobs\JobProgress;
use SwishfolioCore\Tools\ConfirmToken;
use SwishfolioCore\Tools\Journal\JournalEntry;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\Journal\WpdbJournalRepository;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;
use SwishfolioCore\Tools\Write\RewriteCopyTool;
use SwishfolioCore\Tools\Write\SetDesignTokensTool;
use SwishfolioCore\Tools\Write\TrustedMarkup;
use SwishfolioCore\Tools\Write\UpdateContentTool;

/**
 * POST /ai/onboarding/customise, in three stages.
 *
 * preview  Lists what a template customisation would rewrite, pins the model
 *          and a run ID, and issues a ConfirmToken over exactly that scope.
 *          No provider call.
 * run      One target of an approved scope: a page / template part, or the
 *          design. The wizard starts one per target, in parallel, all under
 *          the scope's run ID, so the whole customisation is one undo. The
 *          token is the owner's approval; the writes run confirmed.
 * media    Blank / AI-built sites: place the owner's uploaded images on one
 *          draft page a generation run created, through the builder, or set
 *          their logo (target "logo"). Drafts only, so no token is needed.
 *
 * A new controller rather than more routes on OnboardingController or
 * BuilderController, both shared with other work.
 */
class CustomiseController
{
    const NAMESPACE_V1 = 'swishfolio/v1';
    const KIND         = ModelRouter::KIND_TEMPLATE_CUSTOMISE;

    /** The owner's own description of their business or bio (business.brief): generous on purpose. */
    const BRIEF_MAX = 6000;

    /** @var Settings */
    private $settings;

    /** @var ProviderRegistry */
    private $providers;

    /** @var UsageLogger */
    private $logger;

    /** @var BackgroundJobs|null */
    private $jobs;

    /** @var BuilderController|null */
    private $builder;

    public function __construct(Settings $settings, ProviderRegistry $providers, UsageLogger $logger, BackgroundJobs $jobs = null, BuilderController $builder = null)
    {
        $this->settings  = $settings;
        $this->providers = $providers;
        $this->logger    = $logger;
        $this->jobs      = $jobs;
        $this->builder   = $builder;

        TrustedMarkup::register();

        if ($jobs !== null) {
            $jobs->register('onboarding-customise', [$this, 'runTarget']);
            $jobs->register('onboarding-media', [$this, 'runMedia']);
        }
    }

    /**
     * @return void
     */
    public function registerRoutes()
    {
        register_rest_route(self::NAMESPACE_V1, '/ai/onboarding/customise', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => [$this, 'canCustomise'],
            'args'                => [
                'stage'        => ['type' => 'string', 'required' => true, 'enum' => ['preview', 'run', 'media']],
                'brief'        => ['type' => 'object', 'required' => false],
                'scope'        => ['type' => 'object', 'required' => false],
                'confirmToken' => ['type' => 'string', 'required' => false],
                'target'       => ['type' => 'string', 'required' => false],
                'runId'        => ['type' => 'string', 'required' => false],
                'async'        => ['type' => 'boolean', 'required' => false],
            ],
        ]);
    }

    /**
     * Owner-only, like the rest of onboarding, and only with AI switched on.
     *
     * @param \WP_REST_Request $request Request.
     *
     * @return true|\WP_Error
     */
    public function canCustomise($request)
    {
        if (! is_user_logged_in()) {
            return new \WP_Error('sfcore_ai_forbidden', __('You must be signed in.', 'swishfolio-core'), ['status' => 401]);
        }

        if (! current_user_can('manage_options') || ! current_user_can(BuilderController::CAPABILITY)) {
            return new \WP_Error('sfcore_ai_forbidden', __('You cannot set this site up.', 'swishfolio-core'), ['status' => 403]);
        }

        if (! $this->settings->get()['enabled']) {
            return new \WP_Error('sfcore_ai_disabled', __('AI features are turned off for this site.', 'swishfolio-core'), ['status' => 403]);
        }

        return true;
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle($request)
    {
        try {
            $this->providers->configured();
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_not_configured', $e->getMessage(), ['status' => 409]);
        }

        $stage = (string) $request->get_param('stage');
        $brief = $this->brief((array) $request->get_param('brief'));

        // The template's AI pass rewrites pages from the owner's description, so it needs one.
        if (($stage === 'preview' || $stage === 'run') && ($missing = self::requireBrief($brief)) !== null) {
            return $missing;
        }

        if ($stage === 'preview') {
            return rest_ensure_response($this->preview($brief));
        }

        if ($stage === 'run') {
            $scope = $this->scope((array) $request->get_param('scope'));

            if (! ConfirmToken::verify((string) $request->get_param('confirmToken'), self::calls($scope), get_current_user_id())) {
                return new \WP_Error('sfcore_confirm_invalid', __('That approval has expired. Review the changes and approve them again.', 'swishfolio-core'), ['status' => 409]);
            }

            $target = (string) $request->get_param('target');

            if (! ($target === 'design' && $scope['design']) && ! ($target === 'projects' && $scope['projects'] !== '') && ! in_array((int) $target, $scope['posts'], true)) {
                return new \WP_Error('sfcore_ai_bad_request', __('That is not part of the approved changes.', 'swishfolio-core'), ['status' => 400]);
            }

            return $this->dispatch($request, 'onboarding-customise', ['scope' => $scope, 'target' => $target, 'brief' => $brief, 'userId' => get_current_user_id()]);
        }

        $runId  = (string) $request->get_param('runId');
        $target = (string) $request->get_param('target');

        if (! preg_match('/^[a-f0-9]{32}$/', $runId)) {
            return new \WP_Error('sfcore_ai_bad_request', __('That build could not be found.', 'swishfolio-core'), ['status' => 400]);
        }

        if ($target !== 'logo') {
            $postId = (int) $target;

            if (! in_array($postId, self::createdPages($runId), true) || 'draft' !== get_post_status($postId) || ! current_user_can('edit_post', $postId)) {
                return new \WP_Error('sfcore_ai_bad_request', __('Only draft pages from this build can be changed here.', 'swishfolio-core'), ['status' => 400]);
            }
        }

        return $this->dispatch($request, 'onboarding-media', ['runId' => $runId, 'target' => $target, 'brief' => $brief, 'userId' => get_current_user_id()]);
    }

    /**
     * @param \WP_REST_Request $request Request.
     * @param string           $kind    Job kind.
     * @param array            $payload Job payload.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    private function dispatch($request, $kind, array $payload)
    {
        $label = $kind === 'onboarding-media' ? __('Adding your images', 'swishfolio-core') : __('Personalising your site', 'swishfolio-core');

        if ($request->get_param('async') && $this->jobs !== null) {
            $job = $this->jobs->queue($kind, $label, $payload, get_current_user_id());

            return rest_ensure_response(array_merge($job->toStatusArray(), ['queued' => true]));
        }

        $result = $kind === 'onboarding-media' ? $this->runMedia($payload, JobProgress::silent()) : $this->runTarget($payload, JobProgress::silent());

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    /**
     * @param array $brief Owner facts.
     *
     * @return array
     */
    private function preview(array $brief)
    {
        $targets  = TemplateCustomiser::targets();
        $design   = trim($brief['prompt']) !== '' || ($brief['useLogoColors'] && $this->logoId($brief) > 0);
        $projects = $this->projectsPlan($brief, $targets);

        $scope = [
            'posts'  => array_values(array_map(static function ($t) {
                return (int) $t['postId'];
            }, $projects['targets'])),
            'design' => $design && current_user_can('edit_theme_options'),
            'runId'  => ToolContext::newRunId(),
            // Pinned here and signed into the token, so every parallel job of
            // one customisation runs on the same model whatever finishes first.
            'model'  => (new ModelRouter($this->settings))->modelFor(self::KIND),
            // Decided before any rewrite (TemplateProjects): "keep", "remove" or ''.
            'projects'  => $projects['mode'],
            'dropPages' => $projects['drop'],
        ];

        $targets = $projects['targets'];

        if ($projects['mode'] !== '') {
            $targets[] = $projects['mode'] === TemplateProjects::KEEP
                ? ['postId' => 'projects', 'title' => __('Sample projects written for your business', 'swishfolio-core'), 'type' => 'projects', 'label' => __('Writing sample projects for your business', 'swishfolio-core')]
                : ['postId' => 'projects', 'title' => __('The projects section, which your business does not need', 'swishfolio-core'), 'type' => 'projects', 'label' => __('Removing the projects section', 'swishfolio-core')];
        }

        return [
            'targets'      => $targets,
            'scope'        => $scope,
            'confirmToken' => ConfirmToken::issue(self::calls($scope), get_current_user_id()),
        ];
    }

    /**
     * Whether this template's projects stay (rewritten for the owner) or go,
     * decided before any page is rewritten. See TemplateProjects.
     *
     * @param array   $brief   Owner facts.
     * @param array[] $targets Rewrite targets.
     *
     * @return array {mode: "keep"|"remove"|'', drop: int[], targets: array[]}
     */
    private function projectsPlan(array $brief, array $targets)
    {
        $none = ['mode' => '', 'drop' => [], 'targets' => $targets];

        if (! current_user_can('edit_posts')) {
            return $none;
        }

        $survey = TemplateProjects::survey($targets);

        if ($survey['listed'] === []) {
            // Nothing lists projects: a template's leftover demo projects just go.
            return $survey['demo'] ? ['mode' => TemplateProjects::REMOVE, 'drop' => [], 'targets' => $targets] : $none;
        }

        try {
            $provider   = $this->providers->configured();
            $prompts    = new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt');
            $journal    = new MutationJournal(new WpdbJournalRepository());
            $customiser = new TemplateCustomiser($provider, $prompts, new ToolRunner(new ToolRegistry([]), $journal));
            $keep       = TemplateProjects::decide($provider, $prompts, $customiser->facts($brief), (new ModelRouter($this->settings))->modelFor(ModelRouter::KIND_TEMPLATE_MATCH));
        } catch (ProviderException $e) {
            // Undecided: keep the section and give it projects that fit.
            $keep = true;
        }

        if ($keep) {
            return $survey['real'] ? $none : ['mode' => TemplateProjects::KEEP, 'drop' => [], 'targets' => $targets];
        }

        $drop = TemplateProjects::framePages($survey['listed']);

        return [
            'mode'    => TemplateProjects::REMOVE,
            'drop'    => $drop,
            'targets' => array_values(array_filter($targets, static function ($t) use ($drop) {
                return ! in_array((int) $t['postId'], $drop, true);
            })),
        ];
    }

    /**
     * Job: one target of an approved customisation.
     *
     * @param array       $payload  scope, target, brief.
     * @param JobProgress $progress Progress.
     *
     * @return array|\WP_Error
     */
    public function runTarget(array $payload, JobProgress $progress)
    {
        $scope  = $this->scope((array) $payload['scope']);
        $target = (string) $payload['target'];
        $brief  = $this->brief((array) $payload['brief']);

        try {
            $provider = $this->providers->configured();
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_not_configured', $e->getMessage(), ['status' => 409]);
        }

        $journal = new MutationJournal(new WpdbJournalRepository());
        $runner  = new ToolRunner(new ToolRegistry([new RewriteCopyTool($journal), new SetDesignTokensTool($journal)]), $journal);
        $context = ToolContext::forUser(get_current_user_id(), $scope['runId']);
        $journal->resume($context);
        // The owner approved this scope in the wizard; ConfirmToken checked it.
        $context->setConfirmed(true);

        $customiser = new TemplateCustomiser($provider, new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'), $runner);

        $this->longRequest();
        $startedAt = microtime(true);

        try {
            if ($target === 'design') {
                $progress->step('design', __('Adjusting colours and fonts', 'swishfolio-core'));
                if ($brief['useLogoColors'] && $this->logoId($brief) > 0) {
                    $brief['logoColors'] = TemplateCustomiser::logoColors($this->logoId($brief));
                }
                $result = $customiser->customiseDesign($brief, $scope['model'], $context);
                $progress->done('design', $result['applied'] ? implode(', ', $result['changes']) : __('No design changes needed', 'swishfolio-core'));
            } elseif ($target === 'projects') {
                $progress->step('projects', $scope['projects'] === TemplateProjects::KEEP ? __('Writing sample projects', 'swishfolio-core') : __('Removing the projects section', 'swishfolio-core'));
                $written = $scope['projects'] === TemplateProjects::KEEP
                    ? TemplateProjects::write($provider, new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'), $customiser->facts($brief), $scope['model'], count(SampleProjects::classify()['demo']))
                    : ['items' => [], 'response' => null];
                $result             = TemplateProjects::finish($scope['projects'], $scope['dropPages'], $written['items'], $journal, $context);
                $result['response'] = $written['response'];
                $result['changes']  = [];
                $progress->done('projects', $result['summary']);
            } else {
                // Projects sections (and links to pages that went with them) go before the rewrite reads the page.
                if ($scope['projects'] === TemplateProjects::REMOVE) {
                    TemplateProjects::strip((int) $target, $scope['dropPages'], $journal, $context);
                    $brief['noProjects'] = true;
                }
                $title = (string) get_the_title((int) $target);
                $progress->step('copy', sprintf(/* translators: %s: page title. */ __('Rewriting %s', 'swishfolio-core'), $title));
                $result = $customiser->customisePost((int) $target, $brief, $scope['model'], $context);
                $progress->done('copy', $result['summary']);
            }
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_provider_error', __("AI couldn't finish this part. Nothing was changed on it.", 'swishfolio-core'), ['status' => 502, 'detail' => $e->getMessage()]);
        } catch (ToolException $e) {
            return new \WP_Error('sfcore_customise_failed', $e->getMessage(), ['status' => 409, 'runId' => $scope['runId']]);
        } finally {
            remove_filter('swishfolio_ai_http_timeout', [$this, 'timeout']);
        }

        $response = isset($result['response']) ? $result['response'] : null;
        unset($result['response']);

        if ($response !== null) {
            $this->logger->record($response, isset($result['counts']['texts']) ? (int) $result['counts']['texts'] : count($result['changes']), (int) round((microtime(true) - $startedAt) * 1000), 'customise');
        }

        (new ModelRouter($this->settings))->markSucceeded(self::KIND);

        $result['target'] = $target;
        $result['runId']  = $scope['runId'];
        $result['usage']  = TemplateCustomiser::usage($response);

        return $result;
    }

    /**
     * Job: place uploaded images on one draft page through the builder, or set the logo.
     *
     * @param array       $payload  runId, target, brief.
     * @param JobProgress $progress Progress.
     *
     * @return array|\WP_Error
     */
    public function runMedia(array $payload, JobProgress $progress)
    {
        $runId   = (string) $payload['runId'];
        $brief   = $this->brief((array) $payload['brief']);
        $journal = new MutationJournal(new WpdbJournalRepository());
        $context = ToolContext::forUser(get_current_user_id(), $runId);
        $journal->resume($context);

        if ((string) $payload['target'] === 'logo') {
            return $this->setLogo($brief, $journal, $context);
        }

        if ($this->builder === null) {
            return new \WP_Error('sfcore_ai_unavailable', __('The builder is not available.', 'swishfolio-core'), ['status' => 501]);
        }

        $postId = (int) $payload['target'];
        $post   = get_post($postId);

        $customiser = new TemplateCustomiser($this->providers->configured(), new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'), new ToolRunner(new ToolRegistry([]), $journal));
        $images     = array_values(array_filter($customiser->images($brief), static function ($image) {
            return $image['kind'] !== 'logo';
        }));

        if (! $images || ! $post) {
            return ['postId' => $postId, 'updated' => false, 'summary' => ''];
        }

        $progress->step('media', sprintf(/* translators: %s: page title. */ __('Adding your images to %s', 'swishfolio-core'), $post->post_title));

        $sections = [];
        foreach (parse_blocks((string) $post->post_content) as $block) {
            if (! empty($block['blockName'])) {
                $sections[] = serialize_block($block);
            }
        }

        $list = [];
        foreach ($images as $image) {
            $list[] = sprintf('- id %d, url %s: %s%s (%s)', $image['id'], wp_get_attachment_image_url($image['id'], 'large'), $image['role'], $image['note'] !== '' ? ', "' . $image['note'] . '"' : '', $image['size']);
        }

        $instruction = "Place the site owner's own photos on this page where they fit. Their photos:\n" . implode("\n", $list)
            . "\nUse each photo at most once on this page and only where it suits the section (a hero, an about section, a gallery or portfolio section). "
            . 'Use core/image blocks (or core/cover / core/media-text) with the exact attachment id and url above, size large, and a short literal alt text. '
            . 'Keep every existing word of copy exactly as it is. Prefer replacing one or two sections that would benefit from an image, or inserting one small gallery section; '
            . 'return as few operations as possible. Do not remove sections.';

        $result = $this->builder->execute([
            'postId'      => $postId,
            'instruction' => $instruction,
            'scope'       => 'page',
            'sections'    => $sections,
            'reference'   => '',
            'title'       => $post->post_title,
        ], JobProgress::silent());

        if (is_wp_error($result)) {
            return $result;
        }

        $content = self::applyOperations($sections, (array) $result['operations']);

        if ($content === implode("\n\n", $sections)) {
            $progress->done('media', __('No good place for a photo on this page', 'swishfolio-core'));
            return ['postId' => $postId, 'updated' => false, 'summary' => (string) $result['summary']];
        }

        $runner = new ToolRunner(new ToolRegistry([new UpdateContentTool($journal)]), $journal);

        try {
            $runner->run('swishfolio/update-content', ['postId' => $postId, 'content' => $content], $context);
        } catch (ToolException $e) {
            return new \WP_Error('sfcore_customise_failed', $e->getMessage(), ['status' => 409]);
        }

        $progress->done('media', (string) $result['summary']);

        return ['postId' => $postId, 'updated' => true, 'summary' => (string) $result['summary'], 'runId' => $runId];
    }

    /**
     * Builder operations applied to top-level sections, server side.
     *
     * @param string[] $sections Serialized top-level blocks.
     * @param array    $ops      Validated builder operations.
     *
     * @return string
     */
    public static function applyOperations(array $sections, array $ops)
    {
        $before = array_fill(0, count($sections), []);
        $after  = array_fill(0, count($sections), []);
        $out    = $sections;
        $tail   = [];

        foreach ($ops as $op) {
            $index = isset($op['index']) ? (int) $op['index'] : -1;
            $type  = isset($op['op']) ? (string) $op['op'] : '';
            $markup = isset($op['markup']) ? (string) $op['markup'] : '';

            if ($type === 'append') {
                $tail[] = $markup;
            } elseif ($index >= 0 && $index < count($sections)) {
                if ($type === 'replace') {
                    $out[$index] = $markup;
                } elseif ($type === 'remove') {
                    $out[$index] = '';
                } elseif ($type === 'insert_before') {
                    $before[$index][] = $markup;
                } elseif ($type === 'insert_after') {
                    $after[$index][] = $markup;
                }
            }
        }

        $parts = [];
        foreach ($out as $i => $markup) {
            $parts = array_merge($parts, $before[$i], $markup === '' ? [] : [$markup], $after[$i]);
        }

        return implode("\n\n", array_merge($parts, $tail));
    }

    /**
     * The owner's logo becomes the site logo; a theme header that only shows
     * the site title also gets the logo beside it.
     *
     * @param array           $brief   Brief.
     * @param MutationJournal $journal Journal.
     * @param ToolContext     $context Run.
     *
     * @return array|\WP_Error
     */
    private function setLogo(array $brief, MutationJournal $journal, ToolContext $context)
    {
        $logo = $this->logoId($brief);

        if ($logo <= 0 || ! current_user_can('edit_theme_options')) {
            return ['updated' => false];
        }

        $journal->updateOption($context, 'swishfolio/set-logo', 'site_logo', $logo, __('Set your logo as the site logo', 'swishfolio-core'));

        return ['updated' => true, 'logo' => $logo, 'runId' => $context->getRunId()];
    }

    /**
     * Pages a generation run created.
     *
     * @param string $runId Run.
     *
     * @return int[]
     */
    public static function createdPages($runId)
    {
        $ids = [];

        foreach ((new WpdbJournalRepository())->findRun($runId) as $entry) {
            if ($entry->isReverted() || JournalEntry::TARGET_POST !== $entry->getTargetType() || 'swishfolio/create-content' !== $entry->getTool()) {
                continue;
            }
            $prior = $entry->getPrior();
            if (! empty($prior['created'])) {
                $ids[] = (int) $prior['created'];
            }
        }

        return $ids;
    }

    /**
     * @param array $scope Scope.
     *
     * @return array The calls the token signs.
     */
    private static function calls(array $scope)
    {
        return [['name' => 'swishfolio/customise-template', 'input' => $scope]];
    }

    /**
     * @param array $raw Scope from the client.
     *
     * @return array Canonical scope.
     */
    private function scope(array $raw)
    {
        $posts = array_values(array_map('intval', isset($raw['posts']) ? (array) $raw['posts'] : []));

        return [
            'posts'  => $posts,
            'design' => ! empty($raw['design']),
            'runId'  => isset($raw['runId']) && preg_match('/^[a-f0-9]{32}$/', (string) $raw['runId']) ? (string) $raw['runId'] : '',
            'model'  => isset($raw['model']) ? (string) $raw['model'] : '',
            'projects'  => isset($raw['projects']) && in_array($raw['projects'], [TemplateProjects::KEEP, TemplateProjects::REMOVE], true) ? (string) $raw['projects'] : '',
            'dropPages' => array_values(array_map('intval', isset($raw['dropPages']) ? (array) $raw['dropPages'] : [])),
        ];
    }

    /**
     * The owner's brief, cleaned the same way for every onboarding route.
     *
     * @param array $raw Brief from the client.
     *
     * @return array Clean brief.
     */
    public static function cleanBrief(array $raw)
    {
        return self::brief($raw);
    }

    /**
     * Routes that build a site from the owner's description refuse to run without it.
     *
     * @param array $brief Clean brief.
     *
     * @return \WP_Error|null Error when business.brief is empty, else null.
     */
    public static function requireBrief(array $brief)
    {
        $text = isset($brief['business']['brief']) && is_scalar($brief['business']['brief']) ? trim((string) $brief['business']['brief']) : '';

        if ($text !== '') {
            return null;
        }

        return new \WP_Error(
            'sfcore_brief_required',
            __('Describe your business or bio first. We build your site from it.', 'swishfolio-core'),
            ['status' => 400]
        );
    }

    /**
     * A brief's business group: each value capped at $max, the owner's description at BRIEF_MAX.
     *
     * @param array    $group Clean business values.
     * @param mixed    $raw   Raw business group from the client.
     * @param callable $text  Cleaner: ($value, $max) => string.
     *
     * @return array
     */
    public static function withLongBrief(array $group, $raw, callable $text)
    {
        $raw = (array) $raw;
        if (isset($group['brief'], $raw['brief']) && is_scalar($raw['brief'])) {
            $group['brief'] = $text($raw['brief'], self::BRIEF_MAX);
        }
        return $group;
    }

    /**
     * @param array $raw Brief from the client.
     *
     * @return array Clean brief.
     */
    private static function brief(array $raw)
    {
        $text = static function ($value, $max) {
            return mb_substr(sanitize_textarea_field((string) $value), 0, $max);
        };

        $group = static function ($value, $max) use ($text) {
            $out = [];
            foreach ((array) $value as $key => $v) {
                if (is_scalar($v) && preg_match('/^[a-zA-Z]{1,20}$/', (string) $key)) {
                    $out[$key] = $text($v, $max);
                }
            }
            return $out;
        };

        $images = [];
        foreach (array_slice(isset($raw['images']) ? (array) $raw['images'] : [], 0, TemplateCustomiser::MAX_IMAGES) as $image) {
            if (is_array($image) && ! empty($image['id'])) {
                $images[] = [
                    'id'   => (int) $image['id'],
                    'role' => isset($image['role']) ? sanitize_key((string) $image['role']) : 'other',
                    'note' => isset($image['note']) ? $text($image['note'], 120) : '',
                ];
            }
        }

        $profile = isset($raw['profile']) && is_array($raw['profile']) ? map_deep($raw['profile'], 'sanitize_textarea_field') : [];

        return [
            'business'      => self::withLongBrief($group(isset($raw['business']) ? $raw['business'] : [], 3000), isset($raw['business']) ? $raw['business'] : [], $text),
            'contact'       => $group(isset($raw['contact']) ? $raw['contact'] : [], 300),
            'prompt'        => $text(isset($raw['prompt']) ? $raw['prompt'] : '', 3000),
            'documents'     => $text(isset($raw['documents']) ? $raw['documents'] : '', TemplateCustomiser::MAX_DOC_CHARS),
            'profile'       => $profile,
            'images'        => $images,
            'useLogoColors' => ! empty($raw['useLogoColors']),
        ];
    }

    /**
     * @param array $brief Brief.
     *
     * @return int The logo's attachment ID, or 0.
     */
    private function logoId(array $brief)
    {
        foreach ($brief['images'] as $image) {
            if ($image['role'] === 'logo' && wp_attachment_is_image($image['id']) && current_user_can('edit_post', $image['id'])) {
                return (int) $image['id'];
            }
        }

        return 0;
    }

    /**
     * @return void
     */
    private function longRequest()
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(320); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }
        add_filter('swishfolio_ai_http_timeout', [$this, 'timeout']);
    }

    /**
     * @return int
     */
    public function timeout()
    {
        return 300;
    }
}
