<?php
/**
 * OpenAI chat-completions provider.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Providers;

use SwishfolioCore\AI\AIRequest;
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
                    'model'      => key($this->getModels()),
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
}
