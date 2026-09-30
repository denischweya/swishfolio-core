<?php
/**
 * Anthropic Messages API provider.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Providers;

use SwishfolioCore\AI\AIRequest;
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
class AnthropicProvider extends AbstractAIProvider
{
    const BASE_URL    = 'https://api.anthropic.com/v1';
    const API_VERSION = '2023-06-01';

    /**
     * Models that accept output_config.effort.
     *
     * It is not universal: Haiku 4.5 and the other pre-4.6 models REJECT it,
     * and Haiku is the obvious cheap model to test with, so sending effort
     * unconditionally would break the first thing anyone tries.
     */
    const EFFORT_MODELS = [
        'claude-opus-5',
        'claude-sonnet-5',
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
        $body = [
            'model'      => $request->getModel(),
            'max_tokens' => $request->getMaxTokens(),
            'system'     => $request->getSystemPrompt(),
            'messages'   => [
                ['role' => 'user', 'content' => $request->getUserPrompt()],
            ],
            'output_config' => [
                'format' => [
                    'type'   => 'json_schema',
                    'schema' => $request->getSchema(),
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

        if (is_string($effort) && $effort !== '' && $this->supportsEffort($request->getModel())) {
            $body['output_config']['effort'] = $effort;
        }

        $decoded = $this->request(self::BASE_URL . '/messages', $body, $this->authHeaders());

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
            $this->lastError = __('The AI returned a reply that was not valid JSON.', 'swishfolio-core');

            throw new ProviderException($this->lastError, 200);
        }

        return new AIResponse(
            $payload,
            isset($decoded['usage']['input_tokens']) ? $decoded['usage']['input_tokens'] : 0,
            isset($decoded['usage']['output_tokens']) ? $decoded['usage']['output_tokens'] : 0,
            isset($decoded['model']) ? $decoded['model'] : $request->getModel(),
            $this->getSlug()
        );
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
}
