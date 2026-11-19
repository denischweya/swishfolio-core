<?php
/**
 * OpenAI chat-completions provider.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Providers;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\Conversation\ConversationReply;
use SwishfolioCore\AI\Conversation\ConversationRequest;
use SwishfolioCore\AI\Conversation\Message;
use SwishfolioCore\AI\Conversation\ToolCall;
use SwishfolioCore\AI\Conversation\ToolDefinition;
use SwishfolioCore\AI\Conversation\ToolNameMap;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\ProviderException;

class OpenAIProvider extends AbstractAIProvider
{
    const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

    public function getSlug()
    {
        return 'openai';
    }

    public function getLabel()
    {
        return __('OpenAI', 'swishfolio-core');
    }

    public function getRequiredFields()
    {
        return ['api_key'];
    }

    public function getModels()
    {
        return [
            'gpt-4o'      => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o mini',
        ];
    }

    /**
     * @return string Endpoint base, without a trailing slash.
     */
    protected function getBaseUrl()
    {
        return self::DEFAULT_BASE_URL;
    }

    public function testConnection()
    {
        try {
            $this->request(
                $this->getBaseUrl() . '/chat/completions',
                [
                    // The configured model first: for a custom endpoint
                    // getModels() is empty, so key() was null and the test
                    // could never pass however good the credentials were.
                    'model'      => $this->pingModel(),
                    'max_tokens' => 1,
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
        $decoded = $this->request(
            $this->getBaseUrl() . '/chat/completions',
            [
                'model'           => $request->getModel(),
                'max_tokens'      => $request->getMaxTokens(),
                'messages'        => [
                    ['role' => 'system', 'content' => $request->getSystemPrompt()],
                    ['role' => 'user', 'content' => $request->getUserPrompt()],
                ],
                'response_format' => [
                    'type'        => 'json_schema',
                    'json_schema' => [
                        'name'   => 'swishfolio_actions',
                        'strict' => true,
                        'schema' => $request->getSchema(),
                    ],
                ],
            ],
            $this->authHeaders()
        );

        $content = isset($decoded['choices'][0]['message']['content'])
            ? $decoded['choices'][0]['message']['content']
            : '';

        $payload = json_decode((string) $content, true);

        if (! is_array($payload)) {
            $this->lastError = 'The AI returned a reply that was not valid JSON.';

            throw new ProviderException($this->lastError, 200);
        }

        return new AIResponse(
            $payload,
            isset($decoded['usage']['prompt_tokens']) ? $decoded['usage']['prompt_tokens'] : 0,
            isset($decoded['usage']['completion_tokens']) ? $decoded['usage']['completion_tokens'] : 0,
            isset($decoded['model']) ? $decoded['model'] : $request->getModel(),
            $this->getSlug()
        );
    }

    /**
     * @return array
     */
    protected function authHeaders()
    {
        return ['Authorization' => 'Bearer ' . $this->credential('api_key')];
    }

    /**
     * A model to send a one-token ping as.
     *
     * @return string
     */
    protected function pingModel()
    {
        $configured = $this->credential('model');

        if ('' !== $configured) {
            return $configured;
        }

        $models = $this->getModels();

        return $models === [] ? '' : (string) key($models);
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
        $decoded = $this->request(
            $this->getBaseUrl() . '/chat/completions',
            [
                'model'      => $request->getModel(),
                'max_tokens' => $request->getMaxTokens(),
                'messages'   => $this->mapMessages($request),
                'tools'      => $this->mapTools($request->getTools()),
            ],
            $this->authHeaders()
        );

        $choice  = isset($decoded['choices'][0]) ? $decoded['choices'][0] : [];
        $message = isset($choice['message']) ? $choice['message'] : [];
        $calls   = [];

        foreach ((array) (isset($message['tool_calls']) ? $message['tool_calls'] : []) as $call) {
            if (! is_array($call) || ! isset($call['function']['name'])) {
                continue;
            }

            // Arguments arrive as a JSON string, not an object, and a model
            // occasionally sends "" for a tool that takes none.
            $arguments = isset($call['function']['arguments']) ? (string) $call['function']['arguments'] : '';
            $input     = '' === trim($arguments) ? [] : json_decode($arguments, true);

            $calls[] = new ToolCall(
                isset($call['id']) ? (string) $call['id'] : '',
                ToolNameMap::fromProvider($call['function']['name']),
                is_array($input) ? $input : []
            );
        }

        return new ConversationReply(
            isset($message['content']) ? (string) $message['content'] : '',
            $calls,
            isset($choice['finish_reason']) ? (string) $choice['finish_reason'] : '',
            isset($decoded['usage']['prompt_tokens']) ? $decoded['usage']['prompt_tokens'] : 0,
            isset($decoded['usage']['completion_tokens']) ? $decoded['usage']['completion_tokens'] : 0,
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
                'type'     => 'function',
                'function' => [
                    'name'        => ToolNameMap::toProvider($tool->getName()),
                    'description' => $tool->getDescription(),
                    'parameters'  => $tool->getInputSchema(),
                ],
            ];
        }

        return $mapped;
    }

    /**
     * Maps the conversation to chat-completions messages.
     *
     * Unlike Anthropic, each tool result is its own message addressed by
     * tool_call_id, so there is nothing to merge. And there is no is_error flag:
     * a failure has to be legible in the content itself, or the model reads a
     * rejection as a successful empty result and carries on.
     *
     * @param ConversationRequest $request Conversation.
     *
     * @return array
     */
    private function mapMessages(ConversationRequest $request)
    {
        $out = [['role' => 'system', 'content' => $request->getSystemPrompt()]];

        foreach ($request->getMessages() as $message) {
            if ($message->getRole() === Message::ROLE_USER) {
                $out[] = ['role' => 'user', 'content' => $message->getText()];

                continue;
            }

            if ($message->getRole() === Message::ROLE_TOOL) {
                $out[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $message->getToolCallId(),
                    'content'      => $message->isError()
                        ? 'ERROR: ' . $message->getText()
                        : $message->getText(),
                ];

                continue;
            }

            $assistant = ['role' => 'assistant', 'content' => $message->getText()];
            $calls     = [];

            foreach ($message->getToolCalls() as $call) {
                $calls[] = [
                    'id'       => $call->getId(),
                    'type'     => 'function',
                    'function' => [
                        'name'      => ToolNameMap::toProvider($call->getName()),
                        'arguments' => (string) wp_json_encode($call->getInput()),
                    ],
                ];
            }

            if ($calls !== []) {
                $assistant['tool_calls'] = $calls;
            }

            $out[] = $assistant;
        }

        return $out;
    }
}
