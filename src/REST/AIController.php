<?php
/**
 * REST routes for AI block editing.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\REST;

use SwishfolioCore\Actions\ActionValidator;
use SwishfolioCore\Actions\ValidationContext;
use SwishfolioCore\Actions\ValidationException;
use SwishfolioCore\AI\AIManager;
use SwishfolioCore\AI\Context\ContextAssembler;
use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\ModelRouter;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\AI\UsageLogger;
use SwishfolioCore\Blocks\BlockRegistry;

/**
 * These routes read and proxy. Nothing here writes post content: the validated
 * actions go back to the editor, which applies them client-side.
 */
class AIController
{
    const NAMESPACE_V1     = 'swishfolio/v1';
    const RATE_LIMIT       = 30;
    const RATE_WINDOW      = 3600;
    const CAPABILITY       = 'swishfolio_use_ai';

    /** @var BlockRegistry */
    private $registry;

    /** @var Settings */
    private $settings;

    /** @var ProviderRegistry */
    private $providers;

    /** @var UsageLogger */
    private $logger;

    public function __construct(
        BlockRegistry $registry,
        Settings $settings,
        ProviderRegistry $providers,
        UsageLogger $logger
    ) {
        $this->registry  = $registry;
        $this->settings  = $settings;
        $this->providers = $providers;
        $this->logger    = $logger;
    }

    /**
     * @return void
     */
    public function registerRoutes()
    {
        register_rest_route(self::NAMESPACE_V1, '/ai/edit', [
            'methods'             => 'POST',
            'callback'            => [$this, 'edit'],
            'permission_callback' => [$this, 'canEditPost'],
            'args'                => [
                'postId'      => ['type' => 'integer', 'required' => true],
                'instruction' => ['type' => 'string', 'required' => true],
                'selection'   => ['type' => 'object', 'required' => true],
                'blockTree'   => ['type' => 'object', 'required' => true],
            ],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/ai/test-provider', [
            'methods'             => 'POST',
            'callback'            => [$this, 'testProvider'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/ai/applied', [
            'methods'             => 'POST',
            'callback'            => [$this, 'applied'],
            'permission_callback' => [$this, 'canUseAi'],
            'args'                => [
                'count' => ['type' => 'integer', 'required' => true],
            ],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/ai/usage', [
            'methods'             => 'GET',
            'callback'            => [$this, 'usage'],
            'permission_callback' => [$this, 'canManage'],
        ]);
    }

    /**
     * The post ID arrives in the request and is verified here — never trusted.
     *
     * @param \WP_REST_Request $request Request.
     *
     * @return bool|\WP_Error
     */
    public function canEditPost($request)
    {
        if (! is_user_logged_in()) {
            return new \WP_Error('sfcore_ai_forbidden', __('You must be signed in.', 'swishfolio-core'), ['status' => 401]);
        }

        if (! current_user_can(self::CAPABILITY)) {
            return new \WP_Error('sfcore_ai_forbidden', __('You cannot use AI editing.', 'swishfolio-core'), ['status' => 403]);
        }

        // The site owner's off switch. Without this check the setting is
        // decorative: the routes stay live however it is set.
        if (! $this->settings->get()['enabled']) {
            return new \WP_Error(
                'sfcore_ai_disabled',
                __('AI features are turned off for this site.', 'swishfolio-core'),
                ['status' => 403]
            );
        }

        $postId = (int) $request->get_param('postId');

        if ($postId <= 0 || ! current_user_can('edit_post', $postId)) {
            return new \WP_Error('sfcore_ai_forbidden', __('You cannot edit this post.', 'swishfolio-core'), ['status' => 403]);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function canManage()
    {
        return current_user_can('manage_options');
    }

    /**
     * Whether the current user may use AI at all.
     *
     * Lighter than canEditPost(): reporting how many changes were kept needs no
     * particular post, only that the user is entitled to use the feature.
     *
     * @return bool
     */
    public function canUseAi()
    {
        return is_user_logged_in() && current_user_can(self::CAPABILITY);
    }

    /**
     * Record how many proposed changes the user actually applied.
     *
     * Applying happens client-side, so without this the "Changes applied"
     * figure would be permanently zero and the accept rate — the most useful
     * quality signal the plugin has — unmeasurable.
     *
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response
     */
    public function applied($request)
    {
        $count = (int) $request->get_param('count');

        // Bounded by the same cap the AI itself is held to, so a bad client
        // cannot inflate the statistic.
        $max = (int) apply_filters('swishfolio_ai_max_actions', 25);

        if ($count > 0) {
            $this->logger->recordAccepted(min($count, $max));
        }

        return rest_ensure_response(['recorded' => max(0, min($count, $max))]);
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function edit($request)
    {
        $limited = $this->checkRateLimit();

        if ($limited !== null) {
            return $limited;
        }

        $selection = (array) $request->get_param('selection');
        $blockTree = $this->normaliseBlockTree((array) $request->get_param('blockTree'));

        if (empty($selection['blockName']) || empty($selection['clientId'])) {
            return new \WP_Error('sfcore_ai_bad_selection', __('No block was selected.', 'swishfolio-core'), ['status' => 400]);
        }

        try {
            $manager = $this->buildManager();
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_not_configured', $e->getMessage(), ['status' => 409]);
        }

        $context = new ValidationContext(
            $blockTree,
            $selection['clientId'],
            $this->registry,
            TokenResolver::fromTheme(),
            (int) apply_filters('swishfolio_ai_max_actions', 25)
        );

        try {
            $validated = $manager->edit(
                $selection,
                (string) $request->get_param('instruction'),
                $context,
                (new ModelRouter($this->settings))->modelFor(ModelRouter::KIND_EDIT)
            );
        } catch (ValidationException $e) {
            return new \WP_Error(
                'sfcore_ai_invalid_response',
                __('AI could not complete this request. Nothing was changed.', 'swishfolio-core'),
                ['status' => 422, 'stage' => $e->getStage(), 'path' => $e->getPath(), 'detail' => $e->getMessage()]
            );
        } catch (ProviderException $e) {
            return new \WP_Error(
                'sfcore_ai_provider_error',
                $e->getGuidance(),
                [
                    'status'   => 502,
                    'guidance' => $e->getGuidanceCode(),
                    'detail'   => $e->getMessage(),
                ]
            );
        }

        return rest_ensure_response($validated);
    }

    /**
     * @return \WP_REST_Response
     */
    public function testProvider()
    {
        try {
            $provider = $this->providers->configured();
        } catch (ProviderException $e) {
            return rest_ensure_response(['ok' => false, 'message' => $e->getMessage()]);
        }

        $ok = $provider->testConnection();

        return rest_ensure_response([
            'ok'      => $ok,
            'message' => $ok
                ? __('Connection successful.', 'swishfolio-core')
                : (string) $provider->getLastError(),
        ]);
    }

    /**
     * @return \WP_REST_Response
     */
    public function usage()
    {
        return rest_ensure_response($this->logger->getUsage());
    }

    /**
     * @return AIManager
     */
    private function buildManager()
    {
        return new AIManager(
            $this->providers->configured(),
            new ContextAssembler(
                $this->registry,
                TokenResolver::fromTheme(),
                new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'),
                $this->settings->getBrand()
            ),
            ActionValidator::withDefaultStages(),
            $this->logger
        );
    }

    /**
     * Coerce the editor payload into the shape ValidationContext expects.
     *
     * @param array $tree Raw block tree.
     *
     * @return array
     */
    private function normaliseBlockTree(array $tree)
    {
        $normalised = [];

        foreach ($tree as $clientId => $block) {
            if (! is_array($block) || empty($block['name'])) {
                continue;
            }

            $normalised[(string) $clientId] = [
                'name'           => (string) $block['name'],
                'attributes'     => isset($block['attributes']) ? (array) $block['attributes'] : [],
                'parentClientId' => empty($block['parentClientId']) ? null : (string) $block['parentClientId'],
            ];
        }

        return $normalised;
    }

    /**
     * @return \WP_Error|null
     */
    private function checkRateLimit()
    {
        $limit = (int) apply_filters('swishfolio_ai_rate_limit', self::RATE_LIMIT);

        if ($limit <= 0) {
            return null;
        }

        $key   = 'sfcore_ai_rate_' . get_current_user_id();
        $count = (int) get_transient($key);

        if ($count >= $limit) {
            return new \WP_Error(
                'sfcore_ai_rate_limited',
                __('Too many AI requests. Try again later.', 'swishfolio-core'),
                ['status' => 429]
            );
        }

        set_transient($key, $count + 1, self::RATE_WINDOW);

        return null;
    }
}
