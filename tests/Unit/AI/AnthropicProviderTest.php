<?php

namespace SwishfolioCore\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\ProviderException;

class AnthropicProviderTest extends TestCase
{
    private function request()
    {
        return new AIRequest('SYSTEM', 'USER', ['type' => 'object'], 'claude-opus-5', 2048);
    }

    private function provider($response)
    {
        $provider = new SpyAnthropicProvider();
        $provider->setCredentials(['api_key' => 'sk-ant-test']);
        $provider->nextResponse = $response;

        return $provider;
    }

    /**
     * Adaptive thinking is on by default for Opus 5, so a thinking block can
     * precede the text block. The JSON lives in the first TEXT block.
     */
    private function successBody(array $payload, $withThinking = false)
    {
        $content = [];

        if ($withThinking) {
            $content[] = ['type' => 'thinking', 'thinking' => '', 'signature' => 'abc'];
        }

        $content[] = ['type' => 'text', 'text' => json_encode($payload)];

        return [
            'response' => ['code' => 200],
            'body'     => json_encode([
                'content'     => $content,
                'model'       => 'claude-opus-5',
                'stop_reason' => 'end_turn',
                'usage'       => ['input_tokens' => 1500, 'output_tokens' => 120],
            ]),
        ];
    }

    public function test_it_posts_to_the_messages_endpoint()
    {
        $provider = $this->provider($this->successBody(['schemaVersion' => '1.0', 'actions' => []]));
        $provider->generate($this->request());

        $this->assertSame('https://api.anthropic.com/v1/messages', $provider->lastUrl);
    }

    public function test_it_sends_the_api_key_and_version_headers_not_a_bearer_token()
    {
        $provider = $this->provider($this->successBody(['schemaVersion' => '1.0', 'actions' => []]));
        $provider->generate($this->request());

        $this->assertSame('sk-ant-test', $provider->lastHeaders['x-api-key']);
        $this->assertSame('2023-06-01', $provider->lastHeaders['anthropic-version']);
        $this->assertArrayNotHasKey('Authorization', $provider->lastHeaders);
    }

    public function test_the_system_prompt_is_a_top_level_field_not_a_message()
    {
        $provider = $this->provider($this->successBody(['schemaVersion' => '1.0', 'actions' => []]));
        $provider->generate($this->request());

        $this->assertSame('SYSTEM', $provider->lastBody['system']);
        $this->assertCount(1, $provider->lastBody['messages']);
        $this->assertSame('user', $provider->lastBody['messages'][0]['role']);
        $this->assertSame('USER', $provider->lastBody['messages'][0]['content']);
    }

    public function test_the_schema_is_sent_through_output_config_format()
    {
        $provider = $this->provider($this->successBody(['schemaVersion' => '1.0', 'actions' => []]));
        $provider->generate($this->request());

        $format = $provider->lastBody['output_config']['format'];

        $this->assertSame('json_schema', $format['type']);
        $this->assertSame(['type' => 'object'], $format['schema']);
    }

    public function test_it_does_not_send_a_deprecated_response_format_key()
    {
        $provider = $this->provider($this->successBody(['schemaVersion' => '1.0', 'actions' => []]));
        $provider->generate($this->request());

        $this->assertArrayNotHasKey('response_format', $provider->lastBody);
        $this->assertArrayNotHasKey('output_format', $provider->lastBody);
    }

    public function test_it_reads_json_from_the_first_text_block_even_after_a_thinking_block()
    {
        $provider = $this->provider($this->successBody(['schemaVersion' => '1.0', 'actions' => []], true));
        $response = $provider->generate($this->request());

        $this->assertSame('1.0', $response->getDecoded()['schemaVersion']);
    }

    public function test_it_maps_anthropic_token_field_names()
    {
        $provider = $this->provider($this->successBody(['schemaVersion' => '1.0', 'actions' => []]));
        $response = $provider->generate($this->request());

        $this->assertSame(1500, $response->getInputTokens());
        $this->assertSame(120, $response->getOutputTokens());
        $this->assertSame('anthropic', $response->getProviderSlug());
        $this->assertSame('claude-opus-5', $response->getModel());
    }

    public function test_a_refusal_stop_reason_raises_rather_than_returning_empty_actions()
    {
        $provider = $this->provider([
            'response' => ['code' => 200],
            'body'     => json_encode([
                'content'      => [['type' => 'text', 'text' => '']],
                'model'        => 'claude-opus-5',
                'stop_reason'  => 'refusal',
                'stop_details' => ['type' => 'refusal', 'category' => 'cyber'],
                'usage'        => ['input_tokens' => 10, 'output_tokens' => 0],
            ]),
        ]);

        try {
            $provider->generate($this->request());
            $this->fail('Expected ProviderException');
        } catch (ProviderException $e) {
            $this->assertStringContainsString('declined', strtolower($e->getMessage()));
        }
    }

    public function test_an_invalid_key_raises_with_the_provider_message()
    {
        $provider = $this->provider([
            'response' => ['code' => 401],
            'body'     => json_encode(['error' => ['message' => 'invalid x-api-key']]),
        ]);

        try {
            $provider->generate($this->request());
            $this->fail('Expected ProviderException');
        } catch (ProviderException $e) {
            $this->assertSame(401, $e->getStatusCode());
            $this->assertStringContainsString('invalid x-api-key', $provider->getLastError());
        }
    }

    public function test_non_json_text_raises_rather_than_returning_a_partial_response()
    {
        $provider = $this->provider([
            'response' => ['code' => 200],
            'body'     => json_encode([
                'content' => [['type' => 'text', 'text' => 'Certainly! Here you go.']],
                'usage'   => ['input_tokens' => 1, 'output_tokens' => 1],
            ]),
        ]);

        $this->expectException(ProviderException::class);
        $provider->generate($this->request());
    }

    public function test_effort_is_sent_for_models_that_accept_it()
    {
        $provider = $this->provider($this->successBody(['schemaVersion' => '1.0', 'actions' => []]));
        $provider->generate($this->request());

        $this->assertSame('medium', $provider->lastBody['output_config']['effort']);
    }

    /**
     * Haiku 4.5 rejects output_config.effort, and it is the obvious cheap model
     * to test with — sending effort unconditionally would break first contact.
     */
    public function test_effort_is_omitted_for_models_that_reject_it()
    {
        $provider = $this->provider($this->successBody(['schemaVersion' => '1.0', 'actions' => []]));
        $provider->generate(new AIRequest('S', 'U', ['type' => 'object'], 'claude-haiku-4-5', 1024));

        $this->assertArrayNotHasKey('effort', $provider->lastBody['output_config']);
        $this->assertSame('json_schema', $provider->lastBody['output_config']['format']['type']);
    }

    public function test_models_are_offered_and_default_to_opus_5()
    {
        $provider = new SpyAnthropicProvider();

        $this->assertArrayHasKey('claude-opus-5', $provider->getModels());
        $this->assertSame('claude-opus-5', array_key_first($provider->getModels()));
        $this->assertSame(['api_key'], $provider->getRequiredFields());
        $this->assertSame('anthropic', $provider->getSlug());
    }
}
