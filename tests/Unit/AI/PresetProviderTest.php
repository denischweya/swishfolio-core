<?php

namespace SwishfolioCore\Tests\Unit\AI;

require_once SFCORE_PLUGIN_DIR . 'tests/Unit/Generation/wp-generation-stubs.php';

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Conversation\ConversationRequest;
use SwishfolioCore\AI\Conversation\Message;
use SwishfolioCore\AI\Conversation\ToolDefinition;
use SwishfolioCore\AI\Providers\GeminiProvider;
use SwishfolioCore\AI\Providers\GroqProvider;
use SwishfolioCore\AI\Providers\MistralProvider;
use SwishfolioCore\AI\Providers\OpenAICompatibleProvider;
use SwishfolioCore\AI\Providers\OpenRouterProvider;

/**
 * The presets add no transport, so what is worth testing is the configuration:
 * that each one reaches its own endpoint with the owner's key, and that
 * OpenRouter sends the attribution headers it asks integrations for.
 */
class PresetProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        sfcore_test_wp_reset();
    }

    /**
     * @param string $class Spy class to build.
     *
     * @return object A spy primed with a plausible chat-completions reply.
     */
    private function spy($class)
    {
        $spy = new $class();

        $spy->setCredentials(['api_key' => 'test-key']);

        return $spy->willAnswer(
            [
                'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
                'model'   => 'test',
                'usage'   => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ]
        );
    }

    private function conversation()
    {
        return new ConversationRequest(
            'You manage a site.',
            [Message::user('hello')],
            [
                new ToolDefinition(
                    'swishfolio/site-info',
                    'Read the site.',
                    ['type' => 'object', 'additionalProperties' => false, 'properties' => []]
                ),
            ],
            'test-model',
            128
        );
    }

    /**
     * @return array[] label => [class, expected host]
     */
    public static function presets()
    {
        return [
            'gemini'     => [SpyGeminiProvider::class, GeminiProvider::class, 'generativelanguage.googleapis.com'],
            'mistral'    => [SpyMistralProvider::class, MistralProvider::class, 'api.mistral.ai'],
            'groq'       => [SpyGroqProvider::class, GroqProvider::class, 'api.groq.com'],
            'openrouter' => [SpyOpenRouterProvider::class, OpenRouterProvider::class, 'openrouter.ai'],
        ];
    }

    /**
     * @dataProvider presets
     *
     * @param string $spyClass  Spy class.
     * @param string $realClass  Provider class.
     * @param string $host       Host it must reach.
     *
     * @return void
     */
    public function test_each_preset_reaches_its_own_endpoint($spyClass, $realClass, $host)
    {
        $spy = $this->spy($spyClass);

        $spy->converse($this->conversation());

        $this->assertStringContainsString($host, $spy->lastUrl);
        $this->assertStringEndsWith('/chat/completions', $spy->lastUrl);
    }

    /**
     * @dataProvider presets
     *
     * @param string $spyClass Spy class.
     *
     * @return void
     */
    public function test_each_preset_sends_the_owners_key_as_a_bearer_token($spyClass)
    {
        $spy = $this->spy($spyClass);

        $spy->converse($this->conversation());

        $this->assertSame('Bearer test-key', $spy->lastHeaders['Authorization']);
    }

    public function test_openrouter_identifies_the_integration_in_the_owners_dashboard()
    {
        $spy = $this->spy(SpyOpenRouterProvider::class);

        $spy->converse($this->conversation());

        $this->assertArrayHasKey('HTTP-Referer', $spy->lastHeaders);
        $this->assertSame('Swishfolio', $spy->lastHeaders['X-Title']);
    }

    public function test_a_preset_does_not_ask_the_owner_for_a_base_url()
    {
        foreach (self::presets() as $case) {
            $provider = new $case[1]();

            $this->assertSame(['api_key'], $provider->getRequiredFields(), $provider->getSlug());
        }
    }

    public function test_the_custom_endpoints_connection_test_uses_the_model_it_was_configured_with()
    {
        // getModels() is empty for a custom endpoint, so this used to send
        // model: null and could never pass however good the credentials were.
        $spy = new SpyOpenAICompatibleProvider();

        $spy->setCredentials(
            [
                'api_key'  => 'test-key',
                'base_url' => 'https://llm.example.test/v1',
                'model'    => 'local-model-7b',
            ]
        );

        $spy->nextResponse = [
            'response' => ['code' => 200],
            'body'     => wp_json_encode(
                ['choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']]]
            ),
        ];

        $this->assertTrue($spy->testConnection());
        $this->assertSame('local-model-7b', $spy->lastBody['model']);
    }
}
