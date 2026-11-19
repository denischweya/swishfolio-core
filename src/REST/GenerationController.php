<?php
/**
 * REST routes for page and site generation.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\REST;

use SwishfolioCore\AI\Copy\Dashes;
use SwishfolioCore\Actions\ActionExecutor;
use SwishfolioCore\Tools\ConfirmToken;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Actions\Handlers\ExecutionException;
use SwishfolioCore\Actions\ValidationException;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\AI\Generation\BlockComposer;
use SwishfolioCore\AI\Generation\Kit;
use SwishfolioCore\AI\Generation\PatternLibrary;
use SwishfolioCore\AI\Generation\PlanAssembler;
use SwishfolioCore\AI\Generation\PlanContext;
use SwishfolioCore\AI\Generation\PlanCoverage;
use SwishfolioCore\AI\Generation\PlanExecutor;
use SwishfolioCore\AI\Generation\PlanSchema;
use SwishfolioCore\AI\Generation\PlanValidator;
use SwishfolioCore\AI\Generation\ProjectsPolicy;
use SwishfolioCore\AI\Onboarding\SampleProjects;
use SwishfolioCore\AI\Generation\SocialProof;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\Journal\WpdbJournalRepository;
use SwishfolioCore\AI\Generation\Staged\StagedPlanner;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\ModelRouter;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\AI\UsageLogger;
use SwishfolioCore\Blocks\BlockRegistry;
use SwishfolioCore\AI\Progress\PlanStreamTracker;
use SwishfolioCore\Jobs\BackgroundJobs;
use SwishfolioCore\Jobs\JobProgress;

/**
 * Two routes, deliberately split.
 *
 * /ai/plan asks a model for a plan, validates it, and writes nothing at all. The
 * site owner reads it, edits it, and decides.
 *
 * /ai/generate executes an approved plan. The plan arriving here has been through
 * a browser, so it is untrusted in exactly the way a model's output is: it goes
 * through the whole validation pipeline again from scratch before a single row is
 * written, and then every action re-checks its own capability against the object
 * it is about to touch.
 *
 * Both compose from exactly one kit (one template's sections, see
 * PatternLibrary::resolve()): the request's "kit", else the kit whose design
 * the site already has, else the swishfolio_ai_generation_kit filter's pick.
 * The plan carries the kit's slug, and /ai/generate composes with that kit.
 *
 * The in-editor invariant is untouched. /ai/edit still returns actions for the
 * editor to apply; only these routes write, and only after a human said yes.
 */
class GenerationController
{
    const NAMESPACE_V1 = 'swishfolio/v1';
    const CAPABILITY   = 'swishfolio_use_ai';
    const RATE_LIMIT   = 6;
    const RATE_WINDOW  = 3600;
    const RATE_PREFIX  = 'sfcore_ai_gen_rate_';

    /** Seconds one site or page plan request may take (see runPlan()). */
    const PLAN_TIMEOUT = 480;

    /** @var BlockRegistry */
    private $registry;

    /** @var PatternLibrary */
    private $library;

    /** @var Settings */
    private $settings;

    /** @var ProviderRegistry */
    private $providers;

    /** @var UsageLogger */
    private $logger;

    /** @var BackgroundJobs|null */
    private $jobs;

    public function __construct(
        BlockRegistry $registry,
        PatternLibrary $library,
        Settings $settings,
        ProviderRegistry $providers,
        UsageLogger $logger,
        BackgroundJobs $jobs = null
    ) {
        $this->registry  = $registry;
        $this->library   = $library;
        $this->settings  = $settings;
        $this->providers = $providers;
        $this->logger    = $logger;
        $this->jobs      = $jobs;

        if ($jobs !== null) {
            $jobs->register('plan', [$this, 'runPlan']);
            $jobs->register('generate', [$this, 'runGenerate']);
        }
    }

    /**
     * @return void
     */
    public function registerRoutes()
    {
        register_rest_route(self::NAMESPACE_V1, '/ai/plan', [
            'methods'             => 'POST',
            'callback'            => [$this, 'plan'],
            'permission_callback' => [$this, 'canGenerate'],
            'args'                => [
                'instruction' => ['type' => 'string', 'required' => true],
                'scope'       => ['type' => 'string', 'required' => false],
                'page'        => ['type' => 'object', 'required' => false],
                'kit'         => ['type' => 'string', 'required' => false],
                // Onboarding custom design: the owner's visual brief, for section choice and tone.
                'design'      => ['type' => 'string', 'required' => false],
                // Onboarding: the owner's clients, testimonial and key numbers (see SocialProof).
                'proof'       => ['type' => 'object', 'required' => false],
                'async'       => ['type' => 'boolean', 'required' => false],
                // Plan one page of an earlier staged site plan again: {outline, key}.
                'retryPage'   => ['type' => 'object', 'required' => false],
            ],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/ai/generate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'generate'],
            'permission_callback' => [$this, 'canGenerate'],
            'args'                => [
                'plan'  => ['type' => 'object', 'required' => true],
                'scope' => ['type' => 'string', 'required' => false],
                'async' => ['type' => 'boolean', 'required' => false],
            ],
        ]);
    }

    /**
     * Generation writes, so the gate is stricter than /ai/edit's: the AI
     * capability and the ability to create pages, both checked here and both
     * re-checked per action at execution time.
     *
     * @return bool|\WP_Error
     */
    public function canGenerate()
    {
        if (! is_user_logged_in()) {
            return new \WP_Error('sfcore_ai_forbidden', __('You must be signed in.', 'swishfolio-core'), ['status' => 401]);
        }

        if (! current_user_can(self::CAPABILITY)) {
            return new \WP_Error('sfcore_ai_forbidden', __('You cannot use AI features.', 'swishfolio-core'), ['status' => 403]);
        }

        if (! current_user_can('edit_pages')) {
            return new \WP_Error('sfcore_ai_forbidden', __('You cannot create pages on this site.', 'swishfolio-core'), ['status' => 403]);
        }

        // The site owner's off switch, honoured here as well as on /ai/edit —
        // generation writes real content, so it must not outlive the toggle.
        if (! $this->settings->get()['enabled']) {
            return new \WP_Error(
                'sfcore_ai_disabled',
                __('AI features are turned off for this site.', 'swishfolio-core'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function plan($request)
    {
        $limited = $this->checkRateLimit();

        if ($limited !== null) {
            return $limited;
        }

        try {
            $this->providers->configured();
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_not_configured', $e->getMessage(), ['status' => 409]);
        }

        $payload = [
            'instruction' => (string) $request->get_param('instruction'),
            'scope'       => $this->scope($request),
            'page'        => (array) $request->get_param('page'),
            'kit'         => (string) $request->get_param('kit'),
            'design'      => mb_substr(sanitize_textarea_field((string) $request->get_param('design')), 0, 1500),
            'proof'       => SocialProof::clean(map_deep((array) $request->get_param('proof'), static function ($v) {
                return is_scalar($v) ? sanitize_textarea_field((string) $v) : '';
            })),
        ];

        $retry = (array) $request->get_param('retryPage');

        if (isset($retry['outline'], $retry['key'])) {
            $payload['scope']     = 'page';
            $payload['retryPage'] = [
                'outline' => preg_replace('/[^a-f0-9]/', '', (string) $retry['outline']),
                'key'     => sanitize_key((string) $retry['key']),
            ];
        }

        // Planning a whole site is one long provider call; queued, it runs
        // after this response and the client polls /ai/jobs/<id> for steps.
        if ($request->get_param('async') && $this->jobs !== null) {
            $job = $this->jobs->queue('plan', __('Planning your site', 'swishfolio-core'), $payload, get_current_user_id());

            return rest_ensure_response(array_merge($job->toStatusArray(), ['queued' => true]));
        }

        $result = $this->runPlan($payload, JobProgress::silent());

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    /**
     * @param array       $payload  instruction, scope, page.
     * @param JobProgress $progress Where steps are reported.
     *
     * @return array|\WP_Error {scope, plan, confirmToken}.
     */
    public function runPlan(array $payload, JobProgress $progress)
    {
        $scope = $payload['scope'] === 'page' ? 'page' : 'site';

        if (! empty($payload['retryPage'])) {
            return $this->runPageRetry((array) $payload['retryPage'], $progress);
        }

        $progress->step('read', __('Reading your details', 'swishfolio-core'));

        try {
            $provider = $this->providers->configured();
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_not_configured', $e->getMessage(), ['status' => 409]);
        }

        $facts = $this->siteFacts();
        $brief = [
            'instruction'     => (string) $payload['instruction'],
            'siteTitle'       => (string) get_bloginfo('name'),
            'customTemplates' => $facts['templates'],
            'existingSlugs'   => array_keys($facts['slugs']),
            'page'            => (array) $payload['page'],
            'design'          => isset($payload['design']) ? (string) $payload['design'] : '',
            'proof'           => SocialProof::clean(isset($payload['proof']) ? $payload['proof'] : []),
        ];
        $kit   = $this->library->resolve(isset($payload['kit']) ? (string) $payload['kit'] : '', $brief, $scope);

        if ($kit === null) {
            return new \WP_Error('sfcore_ai_no_kit', __('No design kit is available to build pages from.', 'swishfolio-core'), ['status' => 409]);
        }

        $assembler = new PlanAssembler(
            $kit,
            $this->registry,
            TokenResolver::fromTheme(),
            new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'),
            $this->settings->getBrand()
        );

        $progress->detail(
            'read',
            /* translators: 1: number of section layouts, 2: design name. */
            sprintf(__('%1$d section layouts from the %2$s design', 'swishfolio-core'), count($kit->units('section')), $kit->getName())
        );

        /**
         * Filters whether a whole-site plan is written in stages (an outline,
         * then every page in parallel; see StagedPlanner) rather than in one
         * long provider call. Return false to fall back to the single call.
         *
         * @param bool  $staged  Default true.
         * @param array $payload Plan payload.
         */
        if ($scope === 'site' && apply_filters('swishfolio_ai_staged_site_plan', true, $payload)) {
            return $this->runStagedPlan($brief, $facts, $kit, $assembler, $provider, $progress);
        }

        $model     = (new ModelRouter($this->settings))->modelFor($this->designKind($scope));
        $startedAt = microtime(true);
        $tracker   = new PlanStreamTracker($progress);

        $progress->step('ask', $scope === 'page' ? __('Asking AI to plan the page', 'swishfolio-core') : __('Asking AI to plan your site', 'swishfolio-core'));

        // A site plan regularly runs past the default 120s HTTP timeout, and a
        // large kit on the strong model can think for four minutes before it
        // writes a word. A timed-out plan is not retried (the same request
        // would time out again), so give it one generous window instead.
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::PLAN_TIMEOUT + 20); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }
        $timeout = function () {
            return self::PLAN_TIMEOUT;
        };
        add_filter('swishfolio_ai_http_timeout', $timeout);

        try {
            $aiRequest = $scope === 'page'
                ? $assembler->forPagePlan($brief, $model)
                : $assembler->forSitePlan($brief, $model);

            $response = $provider->generate($aiRequest->withProgress($tracker));
        } catch (ProviderException $e) {
            remove_filter('swishfolio_ai_http_timeout', $timeout);

            return new \WP_Error(
                'sfcore_ai_provider_error',
                __('AI could not plan this. Nothing was changed.', 'swishfolio-core'),
                ['status' => 502, 'detail' => $e->getMessage()]
            );
        }

        remove_filter('swishfolio_ai_http_timeout', $timeout);
        $tracker->finish();

        $duration = (int) round((microtime(true) - $startedAt) * 1000);

        $progress->step('check', __('Checking the plan', 'swishfolio-core'));

        $context = $this->planContext($scope, $facts, $kit);

        try {
            // First without the demo-copy check: gaps get one follow-up call.
            $plan = PlanValidator::withDefaultStages(false)->validate($response->getDecoded(), $context);
            $plan = $this->fillGaps($plan, $kit, $assembler, $brief, $model, $provider);
            $plan = PlanValidator::withDefaultStages()->validate($plan, $context);
            // House style, whatever the model wrote: no em dashes or spaced dashes.
            $plan = Dashes::plan($plan);
        } catch (ValidationException $e) {
            $this->logger->recordFailure(
                $e->getStage(),
                $response->getProviderSlug(),
                $response->getModel(),
                $duration
            );

            return $this->validationError($e);
        }

        $sectionCount = 0;
        foreach ($plan['pages'] as $page) {
            $sectionCount += count($page['sections']);
        }

        $progress->done(
            'check',
            /* translators: 1: number of pages, 2: number of sections. */
            sprintf(__('%1$d pages, %2$d sections', 'swishfolio-core'), count($plan['pages']), $sectionCount)
        );

        $this->logger->record($response, count($plan['pages']), $duration, 'planned');

        // The plan is what the owner reviews, so it is what the token approves.
        // Generation's site-level actions publish a menu, repoint the front page
        // and reskin the site, and the gate will refuse all three without it.
        return [
            'scope'        => $scope,
            'plan'         => $plan,
            'confirmToken' => ConfirmToken::issue([['name' => 'plan', 'input' => $plan]], get_current_user_id()),
        ];
    }

    /** Transient prefix for a staged plan's outline, kept for "retry this page". */
    const STAGED_PREFIX = 'sfcore_staged_';

    /**
     * A whole-site plan in stages (StagedPlanner): outline, pages in
     * parallel, check. Same result shape as the single call, plus "staged"
     * (outline id for retries, pages that could not be written, usage per call).
     *
     * @param array                                         $brief     Brief.
     * @param array                                         $facts     Site facts.
     * @param Kit                                           $kit       Kit.
     * @param PlanAssembler                                 $assembler Assembler.
     * @param \SwishfolioCore\AI\Contracts\AIProviderInterface $provider Provider.
     * @param JobProgress                                   $progress  Steps.
     *
     * @return array|\WP_Error
     */
    private function runStagedPlan(array $brief, array $facts, Kit $kit, PlanAssembler $assembler, $provider, JobProgress $progress)
    {
        $router  = new ModelRouter($this->settings);
        $planner = $this->stagedPlanner($provider, $assembler, $kit, $facts);
        $started = microtime(true);

        // Outline, a wave of pages, one retry wave and a copy fill, each with
        // its own timeout: more than the single call's window in the worst case.
        if (function_exists('set_time_limit')) {
            @set_time_limit(900); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }

        try {
            $result = $planner->plan(
                $brief,
                $router->modelFor(ModelRouter::KIND_SITE_DESIGN),
                $router->modelFor(ModelRouter::KIND_SITE_PAGE),
                $progress
            );
        } catch (ProviderException $e) {
            return new \WP_Error(
                'sfcore_ai_provider_error',
                __('AI could not plan this. Nothing was changed.', 'swishfolio-core'),
                ['status' => 502, 'detail' => $e->getMessage()]
            );
        } catch (ValidationException $e) {
            $this->logger->recordFailure($e->getStage(), $provider->getSlug(), '', (int) round((microtime(true) - $started) * 1000));

            return $this->validationError($e);
        }

        $plan = Dashes::plan($result['plan']);

        $sectionCount = 0;
        foreach ($plan['pages'] as $page) {
            $sectionCount += count($page['sections']);
        }

        $progress->done(
            'check',
            /* translators: 1: number of pages, 2: number of sections. */
            sprintf(__('%1$d pages, %2$d sections', 'swishfolio-core'), count($plan['pages']), $sectionCount)
        );

        $outlineId = ToolContext::newRunId();
        set_transient(self::STAGED_PREFIX . $outlineId, [
            'user'    => get_current_user_id(),
            'kit'     => $kit->getSlug(),
            'brief'   => $brief,
            'outline' => $result['outline'],
            'failed'  => array_column($result['failed'], 'key'),
        ], DAY_IN_SECONDS);

        return [
            'scope'        => 'site',
            'plan'         => $plan,
            'confirmToken' => ConfirmToken::issue([['name' => 'plan', 'input' => $plan]], get_current_user_id()),
            'staged'       => [
                'outlineId' => $outlineId,
                'failed'    => $result['failed'],
                'calls'     => $result['calls'],
                'warnings'  => isset($result['warnings']) ? $result['warnings'] : [],
                'seconds'   => round(microtime(true) - $started, 1),
            ],
        ];
    }

    /**
     * "Retry this page": plan one page of an earlier staged plan again, from
     * its saved outline, as a single-page plan for /ai/generate.
     *
     * @param array       $retry    {outline, key}.
     * @param JobProgress $progress Steps.
     *
     * @return array|\WP_Error
     */
    private function runPageRetry(array $retry, JobProgress $progress)
    {
        $saved = preg_match('/^[a-f0-9]{32}$/', (string) $retry['outline']) ? get_transient(self::STAGED_PREFIX . $retry['outline']) : false;
        $key   = (string) $retry['key'];

        if (! is_array($saved) || (int) $saved['user'] !== get_current_user_id() || ! in_array($key, array_column($saved['outline']['pages'], 'key'), true)) {
            return new \WP_Error('sfcore_ai_retry_unknown', __('That page can no longer be retried. Plan the page again from the editor.', 'swishfolio-core'), ['status' => 404]);
        }

        try {
            $provider = $this->providers->configured();
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_not_configured', $e->getMessage(), ['status' => 409]);
        }

        $kit = $this->library->kit((string) $saved['kit']);

        if ($kit === null) {
            return new \WP_Error('sfcore_ai_no_kit', __('No design kit is available to build pages from.', 'swishfolio-core'), ['status' => 409]);
        }

        $facts     = $this->siteFacts();
        $brief     = array_merge((array) $saved['brief'], ['existingSlugs' => array_keys($facts['slugs'])]);
        $assembler = new PlanAssembler($kit, $this->registry, TokenResolver::fromTheme(), new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'), $this->settings->getBrand());
        $planner   = $this->stagedPlanner($provider, $assembler, $kit, $facts);

        // The slug may have been taken since the build (the owner made the
        // page by hand, or retries twice): add the page beside it, never over it.
        foreach ($saved['outline']['pages'] as $i => $page) {
            if ($page['key'] === $key && isset($facts['slugs'][$page['slug']])) {
                $saved['outline']['pages'][$i]['onConflict'] = 'suffix';
            }
        }

        try {
            $plan = $planner->retryPage($saved['outline'], $key, $brief, (new ModelRouter($this->settings))->modelFor(ModelRouter::KIND_SITE_PAGE), $progress);
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_provider_error', __('AI could not plan this. Nothing was changed.', 'swishfolio-core'), ['status' => 502, 'detail' => $e->getMessage()]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        $plan = Dashes::plan($plan);
        $progress->done('check', __('Page ready', 'swishfolio-core'));

        return [
            'scope'        => 'page',
            'plan'         => $plan,
            'confirmToken' => ConfirmToken::issue([['name' => 'plan', 'input' => $plan]], get_current_user_id()),
            'staged'       => ['calls' => $planner->calls()],
        ];
    }

    /**
     * @param \SwishfolioCore\AI\Contracts\AIProviderInterface $provider Provider.
     * @param PlanAssembler $assembler Assembler.
     * @param Kit           $kit       Kit.
     * @param array         $facts     Site facts.
     *
     * @return StagedPlanner
     */
    private function stagedPlanner($provider, PlanAssembler $assembler, Kit $kit, array $facts)
    {
        return new StagedPlanner(
            $provider,
            $assembler,
            new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'),
            $kit,
            function ($scope) use ($facts, $kit) {
                return $this->planContext($scope, $facts, $kit);
            },
            $this->logger
        );
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function generate($request)
    {
        $limited = $this->checkRateLimit();

        if ($limited !== null) {
            return $limited;
        }

        $scope    = $this->scope($request);
        $facts    = $this->siteFacts();
        $sentPlan = (array) $request->get_param('plan');
        $kit      = $this->library->kit(isset($sentPlan['kit']) ? (string) $sentPlan['kit'] : '');

        if ($kit === null) {
            return new \WP_Error(
                'sfcore_ai_invalid_plan',
                __('That plan could not be used. Nothing was changed.', 'swishfolio-core'),
                ['status' => 422, 'stage' => 'plan-envelope', 'path' => 'kit', 'detail' => 'The plan names no known kit.']
            );
        }

        try {
            $plan = PlanValidator::withDefaultStages()->validate(
                $sentPlan,
                $this->planContext($scope, $facts, $kit)
            );
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        // Verified against the plan exactly as sent, which is the plan /ai/plan
        // returned and the owner approved. An edited plan fails here and has to
        // be previewed again — which is the point: the approval was for the
        // plan they saw, not for whatever arrives next.
        $payload = [
            'plan'      => $plan,
            'scope'     => $scope,
            'confirmed' => ConfirmToken::verify(
                (string) $request->get_param('confirmToken'),
                [['name' => 'plan', 'input' => $sentPlan]],
                get_current_user_id()
            ),
        ];

        if ($request->get_param('async') && $this->jobs !== null) {
            $job = $this->jobs->queue('generate', __('Building your pages', 'swishfolio-core'), $payload, get_current_user_id());

            return rest_ensure_response(array_merge($job->toStatusArray(), ['queued' => true]));
        }

        $result = $this->runGenerate($payload, JobProgress::silent());

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    /**
     * @param array       $payload  Validated plan and whether it was confirmed.
     * @param JobProgress $progress Where steps are reported.
     *
     * @return array|\WP_Error Generation report.
     */
    public function runGenerate(array $payload, JobProgress $progress)
    {
        $plan    = Dashes::plan((array) $payload['plan']);
        $context = ToolContext::current();

        $context->setConfirmed(! empty($payload['confirmed']));

        $kit = $this->library->kit(isset($plan['kit']) ? (string) $plan['kit'] : '');

        if ($kit === null) {
            return new \WP_Error('sfcore_ai_generation_failed', __('Generation stopped before it finished.', 'swishfolio-core'), ['status' => 422, 'detail' => 'Unknown kit.']);
        }

        // Demo forms become the working form block, sending to the owner.
        $composer = (new BlockComposer($kit))->withForms(self::formOptions($context->getRunId()));

        $executor = new PlanExecutor(
            $composer,
            ActionExecutor::withDefaultHandlers()
        );

        $executor->onProgress(function ($key, $label, $detail = '') use ($progress) {
            $progress->step($key, $label, $detail);
        });

        try {
            $report = $executor->execute($plan, get_current_user_id(), $context);
        } catch (ExecutionException $e) {
            return new \WP_Error(
                'sfcore_ai_generation_failed',
                __('Generation stopped before it finished.', 'swishfolio-core'),
                [
                    'status'     => $this->statusForReason($e->getReason()),
                    'action'     => $e->getActionType(),
                    'reason'     => $e->getReason(),
                    'detail'     => $e->getMessage(),
                    'createdIds' => $e->getCreatedIds(),
                    'runId'      => $context->getRunId(),
                ]
            );
        } catch (\RuntimeException $e) {
            return new \WP_Error(
                'sfcore_ai_generation_failed',
                __('Generation stopped before it finished.', 'swishfolio-core'),
                ['status' => 500, 'detail' => $e->getMessage()]
            );
        }

        $report['projects'] = $this->sampleProjects($plan, $kit, $context);

        $progress->done();

        // The build is the design-heavy request that counts: only a finished
        // one moves the site to the follow-up model (see ModelRouter).
        (new ModelRouter($this->settings))->markSucceeded(
            $this->designKind(isset($payload['scope']) ? (string) $payload['scope'] : 'site')
        );

        $this->logger->record(
            new AIResponse([], 0, 0, '', 'generation'),
            count($report['actions']),
            0,
            'generated'
        );

        // Without the run ID the owner has a changed site and no handle on the
        // journal rows that would reverse it.
        $report['runId'] = $context->getRunId();

        // What the onboarding's finishing steps (design, header and footer,
        // menu, photos) need to trust about this run, kept server side.
        self::rememberRunMeta($context->getRunId(), $plan, $report, $kit->getSlug());

        return $report;
    }

    /**
     * Projects for the pages just written (see ProjectsPolicy): sample ones
     * when a page lists projects and the site has none of its own; when a
     * planned site lists none, a template's demo projects (and earlier sample
     * ones) are cleared out. Real projects are never touched. Never fails the build.
     *
     * @param array       $plan    Validated plan.
     * @param Kit         $kit     Its kit.
     * @param ToolContext $context Run.
     *
     * @return array|null What was done, or null when nothing was.
     */
    private function sampleProjects(array $plan, Kit $kit, ToolContext $context)
    {
        $shows   = ProjectsPolicy::planShowsProjects($plan, $kit);
        $decided = isset($plan['projects']) && is_array($plan['projects']);

        // No page lists projects: a planned site keeps no demo or sample ones.
        if (! $shows && ! $decided) {
            return null;
        }

        if (! current_user_can('edit_posts')) {
            return null;
        }

        try {
            $journal = new MutationJournal(new WpdbJournalRepository());
            $journal->resume($context);
            $samples = new SampleProjects($journal, $context);

            if (! $shows) {
                return ['action' => 'retired', 'removed' => $samples->retire()];
            }

            return $samples->ensure($decided ? (array) $plan['projects']['items'] : [], $kit);
        } catch (\Exception $e) {
            return ['action' => 'failed', 'detail' => $e->getMessage()];
        }
    }

    /** Transient prefix for a finished run's plan facts (see runMeta()). */
    const RUN_META_PREFIX = 'sfcore_gen_meta_';

    /**
     * @param string $runId  Run.
     * @param array  $plan   Validated plan.
     * @param array  $report Executor report.
     * @param string $kit    Kit slug.
     *
     * @return void
     */
    private static function rememberRunMeta($runId, array $plan, array $report, $kit)
    {
        $pages = [];
        foreach ((array) $report['pages'] as $i => $page) {
            if (empty($page['postId'])) {
                continue;
            }
            $pages[] = [
                'key'     => (string) $page['key'],
                'title'   => (string) $page['title'],
                'slug'    => isset($plan['pages'][$i]['slug']) ? (string) $plan['pages'][$i]['slug'] : '',
                'postId'  => (int) $page['postId'],
                'created' => ! empty($page['created']),
            ];
        }

        set_transient(self::RUN_META_PREFIX . $runId, [
            'kit'        => (string) $kit,
            'user'       => get_current_user_id(),
            'front'      => isset($plan['site']['frontPage']) ? (string) $plan['site']['frontPage'] : '',
            'navigation' => isset($plan['site']['navigation']) ? array_values((array) $plan['site']['navigation']) : [],
            'pages'      => $pages,
            'at'         => time(),
        ], DAY_IN_SECONDS);
    }

    /**
     * Facts a generation run left for its finishing steps, only for the user who ran it.
     *
     * @param string $runId Run.
     *
     * @return array|null
     */
    public static function runMeta($runId)
    {
        if (! preg_match('/^[a-f0-9]{32}$/', (string) $runId)) {
            return null;
        }

        $meta = get_transient(self::RUN_META_PREFIX . $runId);

        return is_array($meta) && (int) $meta['user'] === get_current_user_id() ? $meta : null;
    }

    /**
     * Where the working forms send: the site's admin email unless a plugin
     * knows the owner's contact address (swishfolio-extra's onboarding does).
     *
     * @param string $runId Run, for unique form ids.
     *
     * @return array KitForms options.
     */
    private static function formOptions($runId)
    {
        $recipient = (string) apply_filters('swishfolio_ai_form_recipient', (string) get_option('admin_email'));

        return [
            'recipient' => is_email($recipient) ? $recipient : '',
            'formId'    => 'swish-form-' . substr((string) $runId, 0, 8),
            'subject'   => sprintf(/* translators: %s: site title. */ __('New enquiry from %s', 'swishfolio-core'), (string) get_bloginfo('name')),
            'success'   => __('Thank you. Your message has been sent and we will reply soon.', 'swishfolio-core'),
        ];
    }

    /**
     * Rewrite the slots a plan left holding demo copy: one follow-up call for
     * just those slots, then, for any still left, removal of the smallest
     * repeatable unit around them. Whatever survives both is refused by the
     * coverage stage.
     *
     * @param array                $plan      Plan validated without coverage.
     * @param Kit                  $kit       Its kit.
     * @param PlanAssembler        $assembler Assembler for the follow-up request.
     * @param array                $brief     Brief.
     * @param string               $model     Model.
     * @param \SwishfolioCore\AI\Contracts\AIProviderInterface $provider Provider.
     *
     * @return array
     */
    private function fillGaps(array $plan, Kit $kit, PlanAssembler $assembler, array $brief, $model, $provider)
    {
        $gaps = PlanCoverage::missing($plan, $kit);

        if ($gaps === []) {
            return $plan;
        }

        try {
            $response = $provider->generate($assembler->forCopyFill($brief, $plan, $gaps, $model));
            $decoded  = $response->getDecoded();
            $plan     = PlanCoverage::fill($plan, $gaps, isset($decoded['fills']) ? (array) $decoded['fills'] : []);
            $this->logger->record($response, 0, 0, 'planned');
        } catch (ProviderException $e) {
            // Fall through to pruning; the coverage stage has the last word.
        }

        $gaps = PlanCoverage::missing($plan, $kit);

        return $gaps === [] ? $plan : PlanCoverage::prune($plan, $gaps, $kit);
    }

    /**
     * @param string $reason ExecutionException reason, now a ToolError code.
     *
     * @return int
     */
    private function statusForReason($reason)
    {
        $statuses = [
            'forbidden'        => 403,
            'confirm_required' => 409,
            'target_missing'   => 404,
            'schema_invalid'   => 400,
            'tool_not_found'   => 400,
        ];

        return isset($statuses[$reason]) ? $statuses[$reason] : 500;
    }

    /**
     * @param string $scope Plan scope.
     *
     * @return string ModelRouter kind.
     */
    private function designKind($scope)
    {
        return $scope === 'page' ? ModelRouter::KIND_PAGE_DESIGN : ModelRouter::KIND_SITE_DESIGN;
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return string
     */
    private function scope($request)
    {
        return $request->get_param('scope') === 'page' ? 'page' : 'site';
    }

    /**
     * @param string $scope Plan scope.
     * @param array  $facts Site facts.
     * @param Kit    $kit   The kit the plan composes with.
     *
     * @return PlanContext
     */
    private function planContext($scope, array $facts, Kit $kit)
    {
        $context = new PlanContext(
            $kit,
            $this->registry,
            TokenResolver::fromTheme(),
            $scope
        );

        return $context
            ->withCustomTemplates($facts['templates'])
            ->withExistingSlugs($facts['slugs'])
            ->withLimits(
                (int) apply_filters('sfcore_generation_max_pages', PlanSchema::MAX_PAGES),
                (int) apply_filters('sfcore_generation_max_sections_per_page', PlanSchema::MAX_SECTIONS_PER_PAGE),
                (int) apply_filters('sfcore_generation_max_sections_total', PlanSchema::MAX_SECTIONS_TOTAL)
            );
    }

    /**
     * Everything the validator needs to know about this particular site.
     *
     * @return array
     */
    private function siteFacts()
    {
        return [
            'templates' => $this->customTemplateNames(),
            'slugs'     => $this->existingPageSlugs(),
        ];
    }

    /**
     * @return string[]
     */
    private function customTemplateNames()
    {
        if (! class_exists('\WP_Theme_JSON_Resolver')) {
            return [];
        }

        $themeData = \WP_Theme_JSON_Resolver::get_theme_data();

        if (! is_object($themeData) || ! method_exists($themeData, 'get_custom_templates')) {
            return [];
        }

        $names = [];

        foreach ((array) $themeData->get_custom_templates() as $name => $template) {
            $postTypes = isset($template['postTypes']) ? (array) $template['postTypes'] : [];

            if ($postTypes === [] || in_array('page', $postTypes, true)) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /**
     * @return array slug => post ID.
     */
    private function existingPageSlugs()
    {
        global $wpdb;

        if (! isset($wpdb)) {
            return [];
        }

        $rows = $wpdb->get_results(
            "SELECT ID, post_name FROM {$wpdb->posts}
             WHERE post_type = 'page' AND post_status NOT IN ('trash', 'auto-draft') AND post_name <> ''"
        );

        $slugs = [];

        foreach ((array) $rows as $row) {
            $slugs[(string) $row->post_name] = (int) $row->ID;
        }

        return $slugs;
    }

    /**
     * @param ValidationException $e Failure.
     *
     * @return \WP_Error
     */
    private function validationError(ValidationException $e)
    {
        return new \WP_Error(
            'sfcore_ai_invalid_plan',
            __('That plan could not be used. Nothing was changed.', 'swishfolio-core'),
            [
                'status' => 422,
                'stage'  => $e->getStage(),
                'path'   => $e->getPath(),
                'detail' => $e->getMessage(),
            ]
        );
    }

    /**
     * Generation is far more expensive than an edit, and it writes, so its limit
     * is separate from and much lower than /ai/edit's.
     *
     * @return \WP_Error|null
     */
    private function checkRateLimit()
    {
        $limit = (int) apply_filters('swishfolio_ai_generate_rate_limit', self::RATE_LIMIT);

        if ($limit <= 0) {
            return null;
        }

        $key   = self::RATE_PREFIX . get_current_user_id();
        $count = (int) get_transient($key);

        if ($count >= $limit) {
            return new \WP_Error(
                'sfcore_ai_rate_limited',
                __('Too many generation requests. Try again later.', 'swishfolio-core'),
                ['status' => 429]
            );
        }

        set_transient($key, $count + 1, self::RATE_WINDOW);

        return null;
    }
}
