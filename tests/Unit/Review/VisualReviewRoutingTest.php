<?php

namespace SwishfolioCore\Tests\Unit\Review;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\Credentials\WordPressCredentialStore;
use SwishfolioCore\AI\ModelRouter;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\Tests\Unit\AI\FakeEncryptionService;
use SwishfolioCore\Tests\Unit\AI\SpyAnthropicProvider;

/**
 * The visual review's model route and its screenshots on the wire.
 */
class VisualReviewRoutingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sfcore_test_options'] = [];
        $GLOBALS['sfcore_test_filters'] = [
            'swishfolio_ai_shared_credential' => function () {
                return ['provider' => 'anthropic', 'api_key' => 'sk-network', 'model' => 'claude-opus-5', 'followup_model' => 'claude-sonnet-5-5'];
            },
        ];
    }

    protected function tearDown(): void
    {
        $GLOBALS['sfcore_test_filters'] = [];
        parent::tearDown();
    }

    public function test_visual_review_uses_the_strong_model_even_after_the_first_design()
    {
        $router = new ModelRouter(new Settings(new WordPressCredentialStore(new FakeEncryptionService())));
        update_option(ModelRouter::FIRST_DESIGN_OPTION, time());

        $this->assertSame('claude-sonnet-5-5', $router->modelFor(ModelRouter::KIND_SITE_DESIGN));
        $this->assertSame('claude-opus-5', $router->modelFor(ModelRouter::KIND_VISUAL_REVIEW));
        $this->assertSame('claude-sonnet-5-5', $router->modelFor(ModelRouter::KIND_VISUAL_REVIEW_COPY));
    }

    public function test_a_data_uri_screenshot_is_sent_as_a_base64_image_block()
    {
        $provider = new SpyAnthropicProvider();
        $provider->setCredentials(['api_key' => 'sk-ant-test']);
        $provider->nextResponse = [
            'response' => ['code' => 200],
            'body'     => json_encode(['content' => [['type' => 'text', 'text' => '{"verdict":"good","issues":[],"fixes":[]}']], 'model' => 'claude-opus-5', 'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]]),
        ];

        $request = (new AIRequest('S', 'U', ['type' => 'object', 'properties' => ['verdict' => ['type' => 'string']]], 'claude-opus-5', 100))
            ->withImages(['data:image/jpeg;base64,/9j/AAA=', 'https://example.com/a.jpg']);
        $provider->generate($request);

        $content = $provider->lastBody['messages'][0]['content'];
        $this->assertSame(['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => '/9j/AAA=']], $content[0]);
        $this->assertSame('url', $content[1]['source']['type']);
        $this->assertSame(['type' => 'text', 'text' => 'U'], $content[2]);
    }
}
