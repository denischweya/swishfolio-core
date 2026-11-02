<?php
/**
 * REST routes for the tool layer.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\REST;

use SwishfolioCore\AI\Settings;
use SwishfolioCore\Tools\Journal\JournalEntry;
use SwishfolioCore\Tools\Journal\JournalRepositoryInterface;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolRunner;

/**
 * Three routes for the tools and two for the audit log, all on the existing
 * swishfolio/v1 namespace rather than a second one.
 *
 * The preview/run split is the approval gate: preview validates and authorises
 * the plan, describes it, and returns a token keyed to it; run refuses any
 * published or destructive call whose token does not match the plan in front of
 * it. A client that skips preview can still run reads and drafts, which is what
 * makes the gate proportionate rather than a prompt on everything.
 */
class ToolsController
{
    const NAMESPACE_V1 = 'swishfolio/v1';
    const RATE_LIMIT   = 60;
    const RATE_WINDOW  = 3600;
    const MAX_CALLS    = 25;

    /** @var ToolRunner */
    private $runner;

    /** @var JournalRepositoryInterface */
    private $journal;

    /** @var Settings */
    private $settings;

    /**
     * @param ToolRunner                 $runner   Tool pipeline.
     * @param JournalRepositoryInterface $journal  Audit storage.
     * @param Settings                   $settings AI settings, for the off switch.
     */
    public function __construct(ToolRunner $runner, JournalRepositoryInterface $journal, Settings $settings)
    {
        $this->runner   = $runner;
        $this->journal  = $journal;
        $this->settings = $settings;
    }

    /**
     * @return void
     */
    public function registerRoutes()
    {
        register_rest_route(
            self::NAMESPACE_V1,
            '/ai/tools',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'listTools'],
                'permission_callback' => [$this, 'canUseTools'],
            ]
        );

        register_rest_route(
            self::NAMESPACE_V1,
            '/ai/tools/preview',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'preview'],
                'permission_callback' => [$this, 'canUseTools'],
                'args'                => [
                    'calls' => ['type' => 'array', 'required' => true],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE_V1,
            '/ai/tools/run',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'run'],
                'permission_callback' => [$this, 'canUseTools'],
                'args'                => [
                    'calls'        => ['type' => 'array', 'required' => true],
                    'confirmToken' => ['type' => 'string', 'required' => false],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE_V1,
            '/ai/audit',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'runs'],
                'permission_callback' => [$this, 'canUseTools'],
            ]
        );

        register_rest_route(
            self::NAMESPACE_V1,
            '/ai/audit/(?P<runId>[A-Za-z0-9]{1,32})',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'runDetail'],
                'permission_callback' => [$this, 'canUseTools'],
            ]
        );
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return true|\WP_Error
     */
    public function canUseTools($request)
    {
        if (! is_user_logged_in()) {
            return new \WP_Error('sfcore_ai_forbidden', __('You must be signed in.', 'swishfolio-core'), ['status' => 401]);
        }

        if (! current_user_can(AIController::CAPABILITY)) {
            return new \WP_Error('sfcore_ai_forbidden', __('You cannot use AI features.', 'swishfolio-core'), ['status' => 403]);
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

        return true;
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response
     */
    public function listTools($request)
    {
        return rest_ensure_response(['tools' => $this->runner->registry()->describe()]);
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function preview($request)
    {
        $calls = $this->callsFrom($request);

        if (is_wp_error($calls)) {
            return $calls;
        }

        try {
            return rest_ensure_response($this->runner->preview($calls, ToolContext::current()));
        } catch (ToolException $e) {
            return $this->error($e);
        }
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

        $calls = $this->callsFrom($request);

        if (is_wp_error($calls)) {
            return $calls;
        }

        $context = ToolContext::current();

        try {
            $results = $this->runner->runPlan($calls, $context, (string) $request->get_param('confirmToken'));
        } catch (ToolException $e) {
            return $this->error($e, ['runId' => $context->getRunId()]);
        }

        return rest_ensure_response(
            [
                'runId'   => $context->getRunId(),
                'results' => $results,
            ]
        );
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response
     */
    public function runs($request)
    {
        $limit = (int) $request->get_param('limit');

        return rest_ensure_response(
            ['runs' => $this->journal->recentRuns($limit > 0 ? min($limit, 50) : 20)]
        );
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response
     */
    public function runDetail($request)
    {
        $entries = [];

        foreach ($this->journal->findRun((string) $request->get_param('runId')) as $entry) {
            $entries[] = [
                'seq'         => $entry->getSeq(),
                'tool'        => $entry->getTool(),
                'summary'     => $entry->getSummary(),
                'targetType'  => $entry->getTargetType(),
                'targetId'    => $entry->getTargetId(),
                'result'      => $entry->getResult(),
                'errorCode'   => $entry->getErrorCode(),
                'reverted'    => $entry->isReverted(),
                'revertError' => $entry->getRevertError(),
                'createdAt'   => $entry->get('created_at'),
                'reversible'  => JournalEntry::TARGET_CALL !== $entry->getTargetType(),
            ];
        }

        return rest_ensure_response(['entries' => $entries]);
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return array|\WP_Error
     */
    private function callsFrom($request)
    {
        $calls = $request->get_param('calls');

        if (! is_array($calls) || $calls === []) {
            return new \WP_Error(
                'sfcore_ai_bad_request',
                __('Send at least one tool call.', 'swishfolio-core'),
                ['status' => 400]
            );
        }

        $max = (int) apply_filters('swishfolio_ai_max_tool_calls', self::MAX_CALLS);

        if (count($calls) > $max) {
            return new \WP_Error(
                'sfcore_ai_bad_request',
                sprintf(
                    /* translators: %d: maximum number of calls. */
                    __('Send no more than %d tool calls at once.', 'swishfolio-core'),
                    $max
                ),
                ['status' => 400]
            );
        }

        $clean = [];

        foreach ($calls as $call) {
            $clean[] = [
                'name'  => isset($call['name']) ? (string) $call['name'] : '',
                'input' => isset($call['input']) && is_array($call['input']) ? $call['input'] : [],
            ];
        }

        return $clean;
    }

    /**
     * @param ToolException $e     Failure.
     * @param array         $extra Extra response data.
     *
     * @return \WP_Error
     */
    private function error(ToolException $e, array $extra = [])
    {
        $status = [
            ToolError::FORBIDDEN        => 403,
            ToolError::TOOL_NOT_FOUND   => 404,
            ToolError::TARGET_MISSING   => 404,
            ToolError::SCHEMA_INVALID   => 400,
            ToolError::CONFIRM_REQUIRED => 409,
            ToolError::RATE_LIMITED     => 429,
        ];

        $code = $e->getError()->getCode();

        return new \WP_Error(
            'sfcore_tool_failed',
            $e->getMessage(),
            array_merge(
                [
                    'status' => isset($status[$code]) ? $status[$code] : 500,
                    'tool'   => $e->getToolName(),
                ],
                $e->getError()->toArray(),
                $extra
            )
        );
    }

    /**
     * Tools make no provider calls, so this is not about token spend: it caps an
     * agent loop that keeps writing. The limit is per user and shares the
     * existing filter so a site tuning one tunes both.
     *
     * @return \WP_Error|null
     */
    private function checkRateLimit()
    {
        $limit = (int) apply_filters('swishfolio_ai_tool_rate_limit', self::RATE_LIMIT);

        if ($limit <= 0) {
            return null;
        }

        $key   = 'sfcore_tool_rate_' . get_current_user_id();
        $count = (int) get_transient($key);

        if ($count >= $limit) {
            return new \WP_Error(
                'sfcore_ai_rate_limited',
                __('Too many tool calls. Try again later.', 'swishfolio-core'),
                ['status' => 429, 'code' => ToolError::RATE_LIMITED]
            );
        }

        set_transient($key, $count + 1, self::RATE_WINDOW);

        return null;
    }
}
