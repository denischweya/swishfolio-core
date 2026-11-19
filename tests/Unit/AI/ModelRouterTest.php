<?php

namespace SwishfolioCore\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Credentials\WordPressCredentialStore;
use SwishfolioCore\AI\ModelRouter;
use SwishfolioCore\AI\Settings;

class ModelRouterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sfcore_test_options'] = [];
        $GLOBALS['sfcore_test_filters'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['sfcore_test_filters'] = [];
        parent::tearDown();
    }

    private function shared($followup)
    {
        $GLOBALS['sfcore_test_filters']['swishfolio_ai_shared_credential'] = function () use ($followup) {
            return [
                'provider'       => 'anthropic',
                'api_key'        => 'sk-network',
                'model'          => 'claude-opus-5',
                'followup_model' => $followup,
            ];
        };
    }

    private function router()
    {
        return new ModelRouter(new Settings(new WordPressCredentialStore(new FakeEncryptionService())));
    }

    public function test_first_design_heavy_request_gets_the_strong_model()
    {
        $this->shared('claude-sonnet-5-5');
        $router = $this->router();

        foreach (ModelRouter::DESIGN_KINDS as $kind) {
            $this->assertSame('claude-opus-5', $router->modelFor($kind), $kind);
        }
    }

    public function test_non_design_requests_always_get_the_followup_model()
    {
        $this->shared('claude-sonnet-5-5');
        $router = $this->router();

        foreach ([ModelRouter::KIND_EDIT, ModelRouter::KIND_AGENT, ModelRouter::KIND_EXTRACT, ModelRouter::KIND_BUILDER_SELECTION] as $kind) {
            $this->assertSame('claude-sonnet-5-5', $router->modelFor($kind), $kind);
        }
    }

    public function test_after_a_successful_design_request_everything_uses_the_followup_model()
    {
        $this->shared('claude-sonnet-5-5');
        $router = $this->router();

        $router->markSucceeded(ModelRouter::KIND_SITE_DESIGN);

        $this->assertTrue($router->firstDesignDone());
        $this->assertSame('claude-sonnet-5-5', $router->modelFor(ModelRouter::KIND_SITE_DESIGN));
        $this->assertSame('claude-sonnet-5-5', $router->modelFor(ModelRouter::KIND_BUILDER_PAGE));
    }

    public function test_a_non_design_success_does_not_mark_the_site()
    {
        $this->shared('claude-sonnet-5-5');
        $router = $this->router();

        $router->markSucceeded(ModelRouter::KIND_EDIT);
        $router->markSucceeded(ModelRouter::KIND_AGENT);

        $this->assertFalse($router->firstDesignDone());
        $this->assertSame('claude-opus-5', $router->modelFor(ModelRouter::KIND_PAGE_DESIGN));
    }

    public function test_a_blank_followup_model_means_no_downgrade()
    {
        $this->shared('');
        $router = $this->router();
        $router->markSucceeded(ModelRouter::KIND_SITE_DESIGN);

        $this->assertSame('claude-opus-5', $router->modelFor(ModelRouter::KIND_EDIT));
        $this->assertSame('claude-opus-5', $router->modelFor(ModelRouter::KIND_SITE_DESIGN));
    }

    public function test_a_sites_own_key_never_downgrades()
    {
        $settings = new Settings(new WordPressCredentialStore(new FakeEncryptionService()));
        $settings->connect('anthropic', 'sk-own', 'claude-opus-5');
        $router = new ModelRouter($settings);

        $this->assertSame('', $settings->getFollowupModel());
        $this->assertSame('claude-opus-5', $router->modelFor(ModelRouter::KIND_AGENT));
    }

    public function test_a_new_endpoint_can_be_tagged_design_heavy_by_filter()
    {
        $this->shared('claude-sonnet-5-5');
        $GLOBALS['sfcore_test_filters']['swishfolio_ai_design_heavy_kinds'] = function ($kinds) {
            $kinds[] = 'new-design-endpoint';

            return $kinds;
        };
        $router = $this->router();

        $this->assertSame('claude-opus-5', $router->modelFor('new-design-endpoint'));
        $this->assertSame('claude-sonnet-5-5', $router->modelFor('something-else'));
    }
}
