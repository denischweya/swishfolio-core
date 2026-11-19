<?php
/**
 * REST route for conversational site management.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\REST;

use SwishfolioCore\AI\Agent\AgentLoop;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\Conversation\Message;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\ModelRouter;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\AI\UsageLogger;
use SwishfolioCore\Jobs\AgentJobRunner;
use SwishfolioCore\Jobs\BackgroundJobs;
use SwishfolioCore\Jobs\JobStoreInterface;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolRunner;

/**
 * One route, because a turn is one request: the loop runs to completion, to an
 * approval prompt, or to the iteration cap, and the client renders whichever.
 *
 * History comes from the client rather than from server-side session state. That
 * is a deliberate trade: it keeps the plugin stateless and multisite-safe, and
 * it is only safe because nothing in the history is trusted — every tool call
 * replayed from it is re-validated, re-authorised against the current user, and
 * re-checked against the confirmation token before it runs.
 */
class AgentController
{
    const NAMESPACE_V1 = 'swishfolio/v1';
    const RATE_LIMIT   = 30;
    const RATE_WINDOW  = 3600;
    /** Upper bound on the serialised history the client may send back. */
    const MAX_HISTORY_BYTES = 200000;

    const MAX_HISTORY  = 60;

    /** @var ToolRunner */
    private $runner;

    /** @var Settings */
    private $settings;

    /** @var ProviderRegistry */
    private $providers;

    /** @var UsageLogger */
    private $logger;

    /** @var AgentJobRunner|null */
    private $jobs;

    /** @var JobStoreInterface|null */
    private $jobStore;

    /**
     * @param ToolRunner       $runner    Tool pipeline.
     * @param Settings         $settings  AI settings.
     * @param ProviderRegistry $providers Provider registry.
     * @param UsageLogger            $logger   Usage counters.
     * @param AgentJobRunner|null    $jobs     Background runner; when absent only synchronous turns are served.
     * @param JobStoreInterface|null $jobStore Progress storage for polling.
     */
    public function __construct(
        ToolRunner $runner,
        Settings $settings,
        ProviderRegistry $providers,
        UsageLogger $logger,
        AgentJobRunner $jobs = null,
        JobStoreInterface $jobStore = null
    ) {
        $this->runner    = $runner;
        $this->settings  = $settings;
        $this->providers = $providers;
        $this->logger    = $logger;
        $this->jobs      = $jobs;
        $this->jobStore  = $jobStore;
    }

    /**
     * @return void
     */
    public function registerRoutes()
    {
        register_rest_route(
            self::NAMESPACE_V1,
            '/ai/agent',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'run'],
                'permission_callback' => [$this, 'canUseAgent'],
                'args'                => [
                    'instruction'  => ['type' => 'string', 'required' => false],
                    'history'      => ['type' => 'array', 'required' => false],
                    'confirmToken' => ['type' => 'string', 'required' => false],
                    'async'        => ['type' => 'boolean', 'required' => false],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE_V1,
            '/ai/jobs/(?P<jobId>[A-Za-z0-9]{1,32})',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'job'],
                'permission_callback' => [$this, 'canUseAgent'],
            ]
        );
    }

    /**
     * Progress for one queued turn.
     *
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function job($request)
    {
        if (null === $this->jobStore) {
            return new \WP_Error(
                'sfcore_job_unavailable',
                __('Background requests are not available on this site.', 'swishfolio-core'),
                ['status' => 501]
            );
        }

        $job = $this->jobStore->find((string) $request->get_param('jobId'));

        if (null === $job) {
            return new \WP_Error(
                'sfcore_job_not_found',
                __('That request has expired or never existed.', 'swishfolio-core'),
                ['status' => 404]
            );
        }

        // A job carries the identity it will run as, so letting one user poll
        // another's would leak what their AI is doing to their site.
        if ($job->getUserId() !== get_current_user_id()) {
            return new \WP_Error(
                'sfcore_job_forbidden',
                __('That request belongs to someone else.', 'swishfolio-core'),
                ['status' => 403]
            );
        }

        // A worker that died (fatal, killed, cron never ran) leaves the job
        // queued or running forever; settle it so the client can offer Retry.
        $job = BackgroundJobs::reapIfStalled($this->jobStore, $job);

        return rest_ensure_response($job->toStatusArray());
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return true|\WP_Error
     */
    public function canUseAgent($request)
    {
        if (! is_user_logged_in()) {
            return new \WP_Error('sfcore_ai_forbidden', __('You must be signed in.', 'swishfolio-core'), ['status' => 401]);
        }

        if (! current_user_can(AIController::CAPABILITY)) {
            return new \WP_Error('sfcore_ai_forbidden', __('You cannot use AI features.', 'swishfolio-core'), ['status' => 403]);
        }

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
    public function run($request)
    {
        $limited = $this->checkRateLimit();

        if ($limited !== null) {
            return $limited;
        }

        $instruction = (string) $request->get_param('instruction');
        $token       = (string) $request->get_param('confirmToken');
        $history     = $this->historyFrom($request);

        if ('' === trim($instruction) && '' === $token) {
            return new \WP_Error(
                'sfcore_ai_bad_request',
                __('Say what you would like changed.', 'swishfolio-core'),
                ['status' => 400]
            );
        }

        try {
            $provider = $this->providers->configured();
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_not_configured', $e->getMessage(), ['status' => 409]);
        }

        // Queued before the loop runs, not after, so a turn that would outlast
        // PHP's execution limit never starts inside this request.
        if ($request->get_param('async') && null !== $this->jobs) {
            // The capped, parsed history, not the raw param: a queued turn is
            // billed and bounded exactly like a synchronous one.
            $serialised = array_map(
                static function (Message $message) {
                    return $message->toArray();
                },
                $history
            );

            $job = $this->jobs->queue($instruction, $serialised, $token, get_current_user_id());

            return rest_ensure_response(array_merge($job->toStatusArray(), ['queued' => true]));
        }

        $model = (new ModelRouter($this->settings))->modelFor(ModelRouter::KIND_AGENT);
        $loop  = new AgentLoop(
            $provider,
            $this->runner,
            new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'),
            $model,
            $this->brandParagraph()
        );

        $context   = ToolContext::current();
        $startedAt = microtime(true);

        try {
            $run = $loop->run($instruction, $context, $history, $token);
        } catch (ProviderException $e) {
            return new \WP_Error(
                'sfcore_ai_provider_error',
                __('The AI could not answer. Nothing was changed.', 'swishfolio-core'),
                ['status' => 502, 'detail' => $e->getMessage()]
            );
        }

        $this->logger->record(
            new AIResponse([], $run->getInputTokens(), $run->getOutputTokens(), $model, $provider->getSlug()),
            count($run->getSteps()),
            (int) round((microtime(true) - $startedAt) * 1000),
            'agent'
        );

        return rest_ensure_response(array_merge($run->toArray(), ['runId' => $context->getRunId()]));
    }

    /**
     * Rebuilds the conversation the client sent back.
     *
     * Capped, because the client decides how much to return and an unbounded
     * history is an unbounded prompt billed to the owner's own key. The oldest
     * turns are dropped rather than the newest: the recent ones carry the tool
     * calls a confirmation token has to match.
     *
     * @param \WP_REST_Request $request Request.
     *
     * @return Message[]
     */
    private function historyFrom($request)
    {
        $raw = $request->get_param('history');

        if (! is_array($raw)) {
            return [];
        }

        $max = (int) apply_filters('swishfolio_ai_agent_max_history', self::MAX_HISTORY);

        if (count($raw) > $max) {
            $raw = array_slice($raw, -$max);
        }

        // Count alone does not bound cost: one message can be megabytes and is
        // resent on every iteration. Drop the oldest until the rest fits.
        while (count($raw) > 1 && strlen((string) wp_json_encode($raw)) > self::MAX_HISTORY_BYTES) {
            array_shift($raw);
        }

        $messages = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $message = Message::fromArray($entry);

            if ($message !== null) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * The brand context as one paragraph, or '' when the owner set none.
     *
     * @return string
     */
    private function brandParagraph()
    {
        $brand = $this->settings->getBrand();
        $parts = [];

        $labels = [
            'siteName'        => __('The site is called "%s".', 'swishfolio-core'),
            'siteDescription' => __('It is described as: %s', 'swishfolio-core'),
            'designStyle'     => __('The design style is %s.', 'swishfolio-core'),
            'copywritingTone' => __('Write copy in a %s tone.', 'swishfolio-core'),
        ];

        foreach ($labels as $key => $template) {
            if (! empty($brand[$key])) {
                $parts[] = sprintf($template, (string) $brand[$key]);
            }
        }

        return $parts === [] ? '' : implode(' ', $parts);
    }

    /**
     * Shares the editing routes' limit and filter: both spend the owner's own
     * provider credit, and a site tuning one means to tune both.
     *
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
