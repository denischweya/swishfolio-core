<?php

namespace SwishfolioCore\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\ProviderException;

class OpenAIProviderTest extends TestCase
{
    private function request()
    {
        return new AIRequest('SYSTEM', 'USER', ['type' => 'object'], 'gpt-test', 1024);
    }

    private function provider($response)
    {
        $provider = new SpyOpenAIProvider();
        $provider->setCredentials(['api_key' => 'sk-test']);
        $provider->nextResponse = $response;

        return $provider;
    }

    private function successBody(array $payload)
    {
        return [
            'response' => ['code' => 200],
            'body'     => json_encode([
                'choices' => [['message' => ['content' => json_encode($payload)]]],
                'usage'   => ['prompt_tokens' => 1200, 'completion_tokens' => 90],
                'model'   => 'gpt-test',
            ]),
        ];
    }

    public function test_request_carries_the_schema_with_strict_enabled()
    {
        $provider = $this->provider($this->successBody(['schemaVersion' => '1.0', 'actions' => []]));
        $provider->generate($this->request());

        $body = $provider->lastBody;

        $this->assertSame('json_schema', $body['response_format']['type']);
        $this->assertTrue($body['response_format']['json_schema']['strict']);
        $this->assertSame(['type' => 'object'], $body['response_format']['json_schema']['schema']);
    }

    public function test_system_and_user_prompts_are_sent_in_cache_stable_order()
    {
        $provider = $this->provider($this->successBody(['schemaVersion' => '1.0', 'actions' => []]));
        $provider->generate($this->request());

        $messages = $provider->lastBody['messages'];

        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame('SYSTEM', $messages[0]['content']);
        $this->assertSame('user', $messages[1]['role']);
        $this->assertSame('USER', $messages[1]['content']);
    }

    public function test_successful_response_is_decoded_with_token_counts()
    {
        $provider = $this->provider($this->successBody(['schemaVersion' => '1.0', 'actions' => []]));
        $response = $provider->generate($this->request());

        $this->assertSame('1.0', $response->getDecoded()['schemaVersion']);
        $this->assertSame(1200, $response->getInputTokens());
        $this->assertSame(90, $response->getOutputTokens());
        $this->assertSame('openai', $response->getProviderSlug());
    }

    public function test_authentication_failure_raises_and_records_the_error()
    {
        $provider = $this->provider([
            'response' => ['code' => 401],
            'body'     => json_encode(['error' => ['message' => 'Invalid API key']]),
        ]);

        try {
            $provider->generate($this->request());
            $this->fail('Expected ProviderException');
        } catch (ProviderException $e) {
            $this->assertSame(401, $e->getStatusCode());
            $this->assertStringContainsString('Invalid API key', $provider->getLastError());
        }
    }

    public function test_non_json_content_raises_rather_than_returning_a_partial_response()
    {
        $provider = $this->provider([
            'response' => ['code' => 200],
            'body'     => json_encode([
                'choices' => [['message' => ['content' => 'Sure! Here is your answer.']]],
                'usage'   => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ]),
        ]);

        $this->expectException(ProviderException::class);
        $provider->generate($this->request());
    }

    public function test_transport_failure_raises()
    {
        $provider = $this->provider(new \WP_Error_Stub('Connection timed out'));

        $this->expectException(ProviderException::class);
        $provider->generate($this->request());
    }

    public function test_rate_limit_is_retried_before_giving_up()
    {
        $provider = new SpyOpenAIProvider();
        $provider->setCredentials(['api_key' => 'sk-test']);
        $provider->queue = [
            ['response' => ['code' => 429], 'body' => '{"error":{"message":"slow down"}}'],
            $this->successBody(['schemaVersion' => '1.0', 'actions' => []]),
        ];

        $response = $provider->generate($this->request());

        $this->assertSame('1.0', $response->getDecoded()['schemaVersion']);
        $this->assertSame(2, $provider->calls);
    }

    public function test_compatible_provider_uses_its_configured_base_url()
    {
        $provider = new SpyOpenAICompatibleProvider();
        $provider->setCredentials(['api_key' => 'k', 'base_url' => 'https://openrouter.ai/api/v1']);
        $provider->nextResponse = $this->successBody(['schemaVersion' => '1.0', 'actions' => []]);
        $provider->generate($this->request());

        $this->assertSame('https://openrouter.ai/api/v1/chat/completions', $provider->lastUrl);
        $this->assertSame('openai-compatible', $provider->getSlug());
    }
}
