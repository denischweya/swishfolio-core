<?php
/**
 * REST routes for page and site generation.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\REST;

use SwishfolioCore\Actions\ActionExecutor;
use SwishfolioCore\Actions\Handlers\ExecutionException;
use SwishfolioCore\Actions\ValidationException;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\AI\Generation\BlockComposer;
use SwishfolioCore\AI\Generation\PatternLibrary;
use SwishfolioCore\AI\Generation\PlanAssembler;
use SwishfolioCore\AI\Generation\PlanContext;
use SwishfolioCore\AI\Generation\PlanExecutor;
use SwishfolioCore\AI\Generation\PlanSchema;
use SwishfolioCore\AI\Generation\PlanValidator;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\AI\UsageLogger;
use SwishfolioCore\Blocks\BlockRegistry;

/**
 * Two routes, deliberately split.
 *
 * /ai/plan asks a model for a plan, validates it, and writes nothing at all. The
 * site owner reads it, edits it, and decides.
 *
 * /ai/generate executes an approved plan. The plan arriving here has been through
 * a browser, so it is untrusted in exactly the way a model's output is: it goes
 * through the whole six-stage pipeline again from scratch before a single row is
 * written, and then every action re-checks its own capability against the object
 * it is about to touch.
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

    public function __construct(
        BlockRegistry $registry,
        PatternLibrary $library,
        Settings $settings,
        ProviderRegistry $providers,
        UsageLogger $logger
    ) {
        $this->registry  = $registry;
        $this->library   = $library;
        $this->settings  = $settings;
        $this->providers = $providers;
        $this->logger    = $logger;
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
            ],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/ai/generate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'generate'],
            'permission_callback' => [$this, 'canGenerate'],
            'args'                => [
                'plan'  => ['type' => 'object', 'required' => true],
                'scope' => ['type' => 'string', 'required' => false],
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

        $scope = $this->scope($request);

        try {
            $provider = $this->providers->configured();
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_not_configured', $e->getMessage(), ['status' => 409]);
        }

        $facts     = $this->siteFacts();
        $assembler = new PlanAssembler(
            $this->library,
            $this->registry,
            TokenResolver::fromTheme(),
            new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt')
        );

        $brief = [
            'instruction'     => (string) $request->get_param('instruction'),
            'siteTitle'       => (string) get_bloginfo('name'),
            'styleVariation'  => $facts['activeVariation'],
            'styleVariations' => $facts['variations'],
            'customTemplates' => $facts['templates'],
            'existingSlugs'   => array_keys($facts['slugs']),
            'page'            => (array) $request->get_param('page'),
        ];

        $model     = $this->settings->get()['model'];
        $startedAt = microtime(true);

        try {
            $aiRequest = $scope === 'page'
                ? $assembler->forPagePlan($brief, $model)
                : $assembler->forSitePlan($brief, $model);

            $response = $provider->generate($aiRequest);
        } catch (ProviderException $e) {
            return new \WP_Error(
                'sfcore_ai_provider_error',
                __('AI could not plan this. Nothing was changed.', 'swishfolio-core'),
                ['status' => 502, 'detail' => $e->getMessage()]
            );
        }

        $duration = (int) round((microtime(true) - $startedAt) * 1000);

        try {
            $plan = PlanValidator::withDefaultStages()->validate(
                $response->getDecoded(),
                $this->planContext($scope, $facts)
            );
        } catch (ValidationException $e) {
            $this->logger->recordFailure(
                $e->getStage(),
                $response->getProviderSlug(),
                $response->getModel(),
                $duration
            );

            return $this->validationError($e);
        }

        $this->logger->record($response, count($plan['pages']), $duration, 'planned');

        return rest_ensure_response([
            'scope' => $scope,
            'plan'  => $plan,
        ]);
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

        $scope = $this->scope($request);
        $facts = $this->siteFacts();

        try {
            $plan = PlanValidator::withDefaultStages()->validate(
                (array) $request->get_param('plan'),
                $this->planContext($scope, $facts)
            );
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        $executor = new PlanExecutor(
            new BlockComposer($this->library),
            ActionExecutor::withDefaultHandlers()
        );

        try {
            $report = $executor->execute($plan, get_current_user_id());
        } catch (ExecutionException $e) {
            return new \WP_Error(
                'sfcore_ai_generation_failed',
                __('Generation stopped before it finished.', 'swishfolio-core'),
                [
                    'status'     => $e->getReason() === 'forbidden' ? 403 : 500,
                    'action'     => $e->getActionType(),
                    'reason'     => $e->getReason(),
                    'detail'     => $e->getMessage(),
                    'createdIds' => $e->getCreatedIds(),
                ]
            );
        } catch (\RuntimeException $e) {
            return new \WP_Error(
                'sfcore_ai_generation_failed',
                __('Generation stopped before it finished.', 'swishfolio-core'),
                ['status' => 500, 'detail' => $e->getMessage()]
            );
        }

        $this->logger->record(
            new AIResponse([], 0, 0, '', 'generation'),
            count($report['actions']),
            0,
            'generated'
        );

        return rest_ensure_response($report);
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
     *
     * @return PlanContext
     */
    private function planContext($scope, array $facts)
    {
        $context = new PlanContext(
            $this->library,
            $this->registry,
            TokenResolver::fromTheme(),
            $scope
        );

        return $context
            ->withStyleVariations($facts['variations'])
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
            'variations'      => $this->variationTitles(),
            'activeVariation' => $this->activeVariationTitle(),
            'templates'       => $this->customTemplateNames(),
            'slugs'           => $this->existingPageSlugs(),
        ];
    }

    /**
     * @return string[]
     */
    private function variationTitles()
    {
        if (! class_exists('\WP_Theme_JSON_Resolver')) {
            return [];
        }

        $titles = [];

        foreach ((array) \WP_Theme_JSON_Resolver::get_style_variations() as $variation) {
            if (is_array($variation) && isset($variation['title'])) {
                $titles[] = (string) $variation['title'];
            }
        }

        return $titles;
    }

    /**
     * The theme records the active variation as a --style-mode custom property
     * and exposes the matching slug through its own helper. Where that helper is
     * absent there is no reliable way to name the active variation, and the
     * planner is simply told nothing rather than told something wrong.
     *
     * @return string
     */
    private function activeVariationTitle()
    {
        if (! function_exists('swishfolio_get_active_style_variation_slug')) {
            return '';
        }

        $slug = (string) swishfolio_get_active_style_variation_slug();

        if ($slug === '') {
            return '';
        }

        foreach ($this->variationTitles() as $title) {
            if (sanitize_title($title) === $slug) {
                return $title;
            }
        }

        return '';
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
