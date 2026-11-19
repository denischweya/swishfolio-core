<?php
/**
 * Anthropic Messages API provider.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Providers;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\Contracts\ParallelRequestsInterface;
use SwishfolioCore\AI\Conversation\ConversationReply;
use SwishfolioCore\AI\Conversation\ConversationRequest;
use SwishfolioCore\AI\Conversation\Message;
use SwishfolioCore\AI\Conversation\ToolCall;
use SwishfolioCore\AI\Conversation\ToolDefinition;
use SwishfolioCore\AI\Conversation\ToolNameMap;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\ProviderException;

/**
 * Talks to the Anthropic Messages API over wp_remote_post().
 *
 * Three things differ from the OpenAI shape and are easy to get wrong:
 *
 * 1. Auth is `x-api-key` plus `anthropic-version`, not a bearer token.
 * 2. The system prompt is a TOP-LEVEL field, not a message with role "system".
 *    Keeping it out of `messages` is also what makes the cache prefix stable.
 * 3. Structured output goes through `output_config.format`. The older
 *    `output_format` key is deprecated and `response_format` is OpenAI's.
 *
 * Adaptive thinking is on by default for the current models, so the response
 * may open with a thinking block — the JSON is in the first TEXT block.
 */
class AnthropicProvider extends AbstractAIProvider implements ParallelRequestsInterface
{
    const BASE_URL    = 'https://api.anthropic.com/v1';
    const API_VERSION = '2023-06-01';

    /**
     * Models that accept output_config.effort.
     *
     * It is not universal: Haiku 4.5 and the other pre-4.6 models REJECT it,
     * and Haiku is the obvious cheap model to test with, so sending effort
     * unconditionally would break the first thing anyone tries.
     *
     * The 5.5 models are listed because they accept it and because leaving
     * one out silently changes cost: Sonnet 5.5 defaults to "high" effort
     * when none is sent. Nothing else in these requests is model-specific:
     * no `thinking` field (adaptive by default; Sonnet 5.5 400s on
     * "disabled"), no sampling params, and no forced tool_choice (400 on the
     * 5.5 models), so the same body is valid on Opus 5 and Sonnet 5.5.
     */
    const EFFORT_MODELS = [
        'claude-opus-5',
        'claude-opus-5-5',
        'claude-sonnet-5',
        'claude-sonnet-5-5',
        'claude-haiku-5-5',
    ];

    public function getSlug()
    {
        return 'anthropic';
    }

    public function getLabel()
    {
        return __('Anthropic (Claude)', 'swishfolio-core');
    }

    public function getRequiredFields()
    {
        return ['api_key'];
    }

    public function getModels()
    {
        return [
            'claude-opus-5'   => 'Claude Opus 5',
            'claude-sonnet-5-5' => 'Claude Sonnet 5.5',
            'claude-sonnet-5' => 'Claude Sonnet 5',
            'claude-haiku-4-5' => 'Claude Haiku 4.5',
        ];
    }

    public function testConnection()
    {
        try {
            $this->request(
                self::BASE_URL . '/messages',
                [
                    'model'      => 'claude-haiku-4-5',
                    'max_tokens' => 4,
                    'messages'   => [['role' => 'user', 'content' => 'ping']],
                ],
                $this->authHeaders()
            );

            return true;
        } catch (ProviderException $e) {
            return false;
        }
    }

    public function generate(AIRequest $request)
    {
        $body = $this->messagesBody($request);

        $decoded = $request->getProgress() !== null
            ? $this->streamRequest(self::BASE_URL . '/messages', $body, $request->getProgress())
            : $this->request(self::BASE_URL . '/messages', $body, $this->authHeaders());

        return $this->httpResponse($request, $decoded);
    }

    /**
     * The Messages API body for a structured-output request.
     *
     * @param AIRequest $request Request.
     *
     * @return array
     */
    private function messagesBody(AIRequest $request)
    {
        $system = $request->getSystemPrompt();

        // A shared prefix (the kit catalogue and the owner's brand) sent by
        // several calls in one build: mark it so later calls read it from
        // the cache instead of paying for it again.
        if ($request->cachesSystem()) {
            $system = [['type' => 'text', 'text' => $system, 'cache_control' => ['type' => 'ephemeral']]];
        }

        $body = [
            'model'      => $request->getModel(),
            'max_tokens' => $request->getMaxTokens(),
            'system'     => $system,
            'messages'   => [
                ['role' => 'user', 'content' => self::userContent($request)],
            ],
            'output_config' => [
                'format' => [
                    'type'   => 'json_schema',
                    'schema' => self::structuredSchema($request->getSchema()),
                ],
            ],
        ];

        /**
         * Filters the reasoning effort for AI block editing.
         *
         * Default "medium": this is an interactive editor call with a user
         * waiting, and choosing attributes from a constrained manifest is not
         * a hard reasoning problem. Raise it to "high" or "max" for better
         * judgement at the cost of latency.
         *
         * @param string $effort One of low|medium|high|xhigh|max.
         */
        $effort = apply_filters('swishfolio_ai_effort', 'medium');

        // A request that names its own effort (a small vision check) keeps it.
        if ($request->getEffort() !== '') {
            $effort = $request->getEffort();
        }

        if (is_string($effort) && $effort !== '' && $this->supportsEffort($request->getModel())) {
            $body['output_config']['effort'] = $effort;
        }

        return $body;
    }

    /**
     * The user turn: the prompt as a plain string, or, when the request
     * carries images, URL image blocks followed by the prompt as a text block.
     *
     * @param AIRequest $request Request.
     *
     * @return string|array
     */
    private static function userContent(AIRequest $request)
    {
        if ($request->getImages() === []) {
            return $request->getUserPrompt();
        }

        $blocks = [];
        foreach ($request->getImages() as $url) {
            // A data: URI (a screenshot that has no public URL) goes inline as base64.
            if (preg_match('#^data:(image/(?:jpeg|png|gif|webp));base64,([A-Za-z0-9+/=]+)$#', $url, $m)) {
                $blocks[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $m[1], 'data' => $m[2]]];
                continue;
            }
            $blocks[] = ['type' => 'image', 'source' => ['type' => 'url', 'url' => $url]];
        }
        $blocks[] = ['type' => 'text', 'text' => $request->getUserPrompt()];

        return $blocks;
    }

    public function httpRequest(AIRequest $request)
    {
        return [
            'url'     => self::BASE_URL . '/messages',
            'headers' => array_merge(['Content-Type' => 'application/json'], $this->authHeaders()),
            'body'    => wp_json_encode_compat($this->messagesBody($request)),
        ];
    }

    public function httpError($raw, $status)
    {
        return $this->extractError((string) $raw, (int) $status);
    }

    public function httpResponse(AIRequest $request, array $decoded)
    {
        $stopReason = isset($decoded['stop_reason']) ? $decoded['stop_reason'] : '';

        if ($stopReason === 'refusal') {
            $category = isset($decoded['stop_details']['category'])
                ? (string) $decoded['stop_details']['category']
                : '';

            $this->lastError = $category === ''
                ? __('The AI declined this request.', 'swishfolio-core')
                : sprintf(
                    /* translators: %s: refusal category reported by the provider. */
                    __('The AI declined this request (%s).', 'swishfolio-core'),
                    $category
                );

            throw new ProviderException($this->lastError, 200);
        }

        $payload = json_decode((string) $this->firstTextBlock($decoded), true);

        if (! is_array($payload)) {
            $this->lastError = $stopReason === 'max_tokens'
                ? __('The AI reply ran past its length limit and was cut off.', 'swishfolio-core')
                : __('The AI returned a reply that was not valid JSON.', 'swishfolio-core');

            throw new ProviderException($this->lastError, 200);
        }

        $payload = self::restoreOpenObjects($request->getSchema(), $payload);

        return new AIResponse(
            $payload,
            isset($decoded['usage']['input_tokens']) ? $decoded['usage']['input_tokens'] : 0,
            isset($decoded['usage']['output_tokens']) ? $decoded['usage']['output_tokens'] : 0,
            isset($decoded['model']) ? $decoded['model'] : $request->getModel(),
            $this->getSlug()
        );
    }

    /**
     * The same request as request(), streamed, so a listener hears about the
     * reply while it is being written. WordPress's HTTP layer exposes each body
     * chunk through the requests-request.progress action; the full body is
     * still collected, and is parsed again at the end in case the transport
     * never fired the action.
     *
     * @param string   $url      Endpoint.
     * @param array    $body     Payload.
     * @param callable $listener Progress listener.
     *
     * @throws ProviderException On unrecoverable failure.
     *
     * @return array Decoded body in the non-streamed shape.
     */
    protected function streamRequest($url, array $body, callable $listener)
    {
        $body['stream']  = true;
        $this->lastError = null;
        $lastStatus      = 0;

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            if ($attempt > 0) {
                call_user_func($listener, ['type' => 'restart']);
            }

            $stream   = new AnthropicStream($listener);
            $received = false;
            $hook     = function ($data) use ($stream, &$received) {
                $received = true;
                $stream->feed($data);
            };

            add_action('requests-request.progress', $hook, 10, 1);

            $response = $this->post($url, [
                'timeout' => (int) apply_filters('swishfolio_ai_http_timeout', self::TIMEOUT),
                'headers' => array_merge(['Content-Type' => 'application/json'], $this->authHeaders()),
                'body'    => wp_json_encode_compat($body),
            ]);

            remove_action('requests-request.progress', $hook, 10);

            if (is_wp_error($response)) {
                $this->lastError = $response->get_error_message();
                $lastStatus      = 0;

                // Timed out mid-reply: another try would time out the same way.
                if (self::isReplyTimeout($this->lastError)) {
                    throw new ProviderException($this->lastError, 0);
                }

                $this->backoff($attempt);
                continue;
            }

            $status = wp_remote_retrieve_response_code($response);
            $raw    = wp_remote_retrieve_body($response);

            if ($status < 200 || $status >= 300) {
                $this->lastError = $this->extractError($raw, $status);
                $lastStatus      = $status;

                if ($status !== 429 && $status < 500) {
                    throw new ProviderException($this->lastError, $status);
                }

                $this->backoff($attempt);
                continue;
            }

            if (! $received || ! $stream->sawEvent()) {
                $stream = new AnthropicStream(null);
                $stream->feed($raw);
            }

            $stream->finish();

            if ($stream->error() !== null) {
                // Overloaded and similar errors arrive mid-stream with HTTP 200.
                $error           = $stream->error();
                $this->lastError = isset($error['message']) ? (string) $error['message'] : 'The AI provider stopped mid-reply.';
                $lastStatus      = 529;
                $this->backoff($attempt);
                continue;
            }

            if (! $stream->sawEvent()) {
                $this->lastError = 'Provider returned an empty stream.';

                throw new ProviderException($this->lastError, $status);
            }

            $decoded = $stream->toDecoded();

            /** This action is documented in AbstractAIProvider::request(). */
            do_action('swishfolio_ai_response', $decoded, $this->getSlug());

            return $decoded;
        }

        throw new ProviderException(
            $this->lastError === null ? 'The AI provider did not respond.' : $this->lastError,
            $lastStatus
        );
    }

    /**
     * Strips the JSON Schema keywords Anthropic's structured outputs refuse
     * (length, count and numeric bounds; minItems above 1). The request fails
     * with HTTP 400 otherwise. Nothing is lost: every bound is enforced again
     * by the validators that read the reply.
     *
     * @param array $schema JSON Schema.
     *
     * @return array
     */
    public static function structuredSchema(array $schema)
    {
        // Every object must be closed (additionalProperties: false), so an open
        // map such as a section's "content" slots is asked for as a JSON string
        // instead and turned back into an object by restoreOpenObjects().
        if (self::isOpenObject($schema)) {
            $description = isset($schema['description']) ? trim((string) $schema['description']) . ' ' : '';

            return [
                'type'        => 'string',
                'description' => $description . 'Write this as a JSON object encoded in a string.',
            ];
        }

        if (isset($schema['type']) && $schema['type'] === 'object') {
            $schema['additionalProperties'] = false;
        }

        foreach (['maxItems', 'minLength', 'maxLength', 'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'multipleOf'] as $keyword) {
            unset($schema[$keyword]);
        }

        if (isset($schema['minItems']) && (int) $schema['minItems'] > 1) {
            unset($schema['minItems']);
        }

        foreach (['properties', '$defs', 'definitions'] as $map) {
            if (isset($schema[$map]) && is_array($schema[$map])) {
                foreach ($schema[$map] as $name => $sub) {
                    if (is_array($sub)) {
                        $schema[$map][$name] = self::structuredSchema($sub);
                    }
                }
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = self::structuredSchema($schema['items']);
        }

        foreach (['anyOf', 'allOf', 'oneOf'] as $key) {
            if (isset($schema[$key]) && is_array($schema[$key])) {
                $schema[$key] = array_map(function ($sub) {
                    return is_array($sub) ? self::structuredSchema($sub) : $sub;
                }, $schema[$key]);
            }
        }

        return $schema;
    }

    /**
     * @param array $schema JSON Schema.
     *
     * @return bool An object schema that allows arbitrary keys.
     */
    private static function isOpenObject(array $schema)
    {
        return isset($schema['type']) && $schema['type'] === 'object'
            && empty($schema['properties'])
            && (! isset($schema['additionalProperties']) || $schema['additionalProperties'] !== false);
    }

    /**
     * Undo structuredSchema()'s open-object encoding on a decoded reply.
     *
     * @param array $schema Original JSON Schema.
     * @param mixed $value  Decoded value.
     *
     * @return mixed
     */
    public static function restoreOpenObjects(array $schema, $value)
    {
        if (self::isOpenObject($schema)) {
            if (is_string($value)) {
                $decoded = json_decode($value, true);

                return is_array($decoded) ? $decoded : [];
            }

            return $value;
        }

        if (is_array($value) && isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $name => $sub) {
                if (array_key_exists($name, $value) && is_array($sub)) {
                    $value[$name] = self::restoreOpenObjects($sub, $value[$name]);
                }
            }
        }

        if (is_array($value) && isset($schema['items']) && is_array($schema['items']) && array_values($value) === $value) {
            foreach ($value as $i => $item) {
                $value[$i] = self::restoreOpenObjects($schema['items'], $item);
            }
        }

        return $value;
    }

    /**
     * Whether this model accepts output_config.effort.
     *
     * @param string $model Model identifier.
     *
     * @return bool
     */
    private function supportsEffort($model)
    {
        return in_array($model, self::EFFORT_MODELS, true);
    }

    /**
     * The first text block's content.
     *
     * Adaptive thinking means content[0] is often a thinking block, so indexing
     * position zero would read an empty string and look like a malformed reply.
     *
     * @param array $decoded Decoded response body.
     *
     * @return string
     */
    private function firstTextBlock(array $decoded)
    {
        $content = isset($decoded['content']) ? (array) $decoded['content'] : [];

        foreach ($content as $block) {
            if (is_array($block) && isset($block['type']) && $block['type'] === 'text') {
                return isset($block['text']) ? (string) $block['text'] : '';
            }
        }

        return '';
    }

    /**
     * @return array
     */
    protected function authHeaders()
    {
        return [
            'x-api-key'         => $this->credential('api_key'),
            'anthropic-version' => self::API_VERSION,
        ];
    }

    public function supportsTools()
    {
        return true;
    }

    /**
     * One turn of a tool-using conversation.
     *
     * @param ConversationRequest $request Conversation and tools.
     *
     * @throws ProviderException On transport or provider failure.
     *
     * @return ConversationReply
     */
    public function converse(ConversationRequest $request)
    {
        $body = [
            'model'      => $request->getModel(),
            'max_tokens' => $request->getMaxTokens(),
            'system'     => $request->getSystemPrompt(),
            'messages'   => $this->mapMessages($request->getMessages()),
            'tools'      => $this->mapTools($request->getTools()),
        ];

        $effort = apply_filters('swishfolio_ai_agent_effort', 'medium');

        if (is_string($effort) && $effort !== '' && $this->supportsEffort($request->getModel())) {
            $body['output_config'] = ['effort' => $effort];
        }

        $decoded = $this->request(self::BASE_URL . '/messages', $body, $this->authHeaders());

        $stopReason = isset($decoded['stop_reason']) ? (string) $decoded['stop_reason'] : '';

        // A refusal arrives as HTTP 200, so stop_reason has to be read before
        // the content blocks or a declined request looks like an empty reply.
        if ($stopReason === 'refusal') {
            $category = isset($decoded['stop_details']['category'])
                ? (string) $decoded['stop_details']['category']
                : '';

            $this->lastError = $category === ''
                ? __('The AI declined this request.', 'swishfolio-core')
                : sprintf(
                    /* translators: %s: refusal category reported by the provider. */
                    __('The AI declined this request (%s).', 'swishfolio-core'),
                    $category
                );

            throw new ProviderException($this->lastError, 200);
        }

        $text  = '';
        $calls = [];

        foreach ((array) (isset($decoded['content']) ? $decoded['content'] : []) as $block) {
            if (! is_array($block) || ! isset($block['type'])) {
                continue;
            }

            if ($block['type'] === 'text') {
                $text .= isset($block['text']) ? (string) $block['text'] : '';

                continue;
            }

            if ($block['type'] === 'tool_use') {
                $calls[] = new ToolCall(
                    isset($block['id']) ? (string) $block['id'] : '',
                    ToolNameMap::fromProvider(isset($block['name']) ? $block['name'] : ''),
                    isset($block['input']) && is_array($block['input']) ? $block['input'] : []
                );
            }
        }

        return new ConversationReply(
            $text,
            $calls,
            $stopReason,
            isset($decoded['usage']['input_tokens']) ? $decoded['usage']['input_tokens'] : 0,
            isset($decoded['usage']['output_tokens']) ? $decoded['usage']['output_tokens'] : 0,
            isset($decoded['model']) ? $decoded['model'] : $request->getModel(),
            $this->getSlug()
        );
    }

    /**
     * @param ToolDefinition[] $tools Tools to advertise.
     *
     * @return array
     */
    private function mapTools(array $tools)
    {
        $mapped = [];

        foreach ($tools as $tool) {
            $mapped[] = [
                'name'         => ToolNameMap::toProvider($tool->getName()),
                'description'  => $tool->getDescription(),
                'input_schema' => $tool->getInputSchema(),
            ];
        }

        return $mapped;
    }

    /**
     * Maps the conversation to Anthropic's content blocks.
     *
     * Consecutive tool results are merged into one user message carrying several
     * tool_result blocks. That is not a tidiness choice: splitting the results
     * of one parallel batch across several user messages teaches the model to
     * stop issuing parallel calls, so the merge preserves a capability rather
     * than saving bytes.
     *
     * @param Message[] $messages Conversation.
     *
     * @return array
     */
    private function mapMessages(array $messages)
    {
        $out     = [];
        $pending = [];

        foreach ($messages as $message) {
            if ($message->getRole() === Message::ROLE_TOOL) {
                $pending[] = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $message->getToolCallId(),
                    'content'     => $message->getText(),
                    'is_error'    => $message->isError(),
                ];

                continue;
            }

            if ($pending !== []) {
                $out[]   = ['role' => 'user', 'content' => $pending];
                $pending = [];
            }

            if ($message->getRole() === Message::ROLE_USER) {
                $out[] = ['role' => 'user', 'content' => $message->getText()];

                continue;
            }

            $blocks = [];

            if ($message->getText() !== '') {
                $blocks[] = ['type' => 'text', 'text' => $message->getText()];
            }

            foreach ($message->getToolCalls() as $call) {
                $blocks[] = [
                    'type'  => 'tool_use',
                    'id'    => $call->getId(),
                    'name'  => ToolNameMap::toProvider($call->getName()),
                    'input' => $call->getInput() === [] ? new \stdClass() : $call->getInput(),
                ];
            }

            if ($blocks !== []) {
                $out[] = ['role' => 'assistant', 'content' => $blocks];
            }
        }

        if ($pending !== []) {
            $out[] = ['role' => 'user', 'content' => $pending];
        }

        return $out;
    }
}
