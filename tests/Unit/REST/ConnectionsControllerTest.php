<?php

namespace SwishfolioCore\Tests\Unit\REST;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Credentials\WordPressCredentialStore;
use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\REST\ConnectionsController;
use SwishfolioCore\REST\SettingsController;
use SwishfolioCore\Tests\Unit\AI\FakeEncryptionService;
use SwishfolioCore\Tests\Unit\AI\FakeRestRequest;

/**
 * The REST layer had no tests at all, which is how a call to a removed
 * Settings::save() survived into the branch. These cover the contract that
 * matters most: a key goes in and never comes back out.
 */
class ConnectionsControllerTest extends TestCase
{
    /** @var Settings */
    private $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sfcore_test_options'] = [];
        unset($GLOBALS['sfcore_test_wp']['caps']);

        $this->settings = new Settings(new WordPressCredentialStore(new FakeEncryptionService()));
    }

    private function connections()
    {
        return new ConnectionsController($this->settings, new ProviderRegistry($this->settings));
    }

    private function settingsController()
    {
        return new SettingsController($this->settings, new ProviderRegistry($this->settings));
    }

    // --- Permissions --------------------------------------------------------

    public function test_connecting_requires_manage_options()
    {
        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_posts'];

        $this->assertFalse($this->connections()->canManage());
        $this->assertFalse($this->settingsController()->canManage());
    }

    public function test_an_administrator_may_manage_connections()
    {
        $GLOBALS['sfcore_test_wp']['caps'] = ['manage_options'];

        $this->assertTrue($this->connections()->canManage());
    }

    // --- Connect ------------------------------------------------------------

    public function test_connecting_stores_the_key_and_reports_connected()
    {
        $response = $this->connections()->connect(new FakeRestRequest([
            'provider' => 'anthropic',
            'api_key'  => 'sk-ant-secret',
            'model'    => 'claude-opus-5',
        ]));

        $this->assertTrue($response['connected']);
        $this->assertTrue($response['settings']['providers']['anthropic']['connected']);
        $this->assertSame('sk-ant-secret', $this->settings->getKey('anthropic'));
    }

    public function test_no_route_ever_returns_the_api_key()
    {
        $connections = $this->connections();
        $connections->connect(new FakeRestRequest([
            'provider' => 'anthropic',
            'api_key'  => 'sk-ant-secret',
            'model'    => 'claude-opus-5',
        ]));

        $payloads = [
            json_encode($connections->connect(new FakeRestRequest([
                'provider' => 'anthropic', 'api_key' => '', 'model' => 'claude-sonnet-5',
            ]))),
            json_encode($this->settingsController()->read()),
            json_encode($this->settingsController()->write(new FakeRestRequest(['enabled' => true]))),
            json_encode($connections->disconnect(new FakeRestRequest(['provider' => 'anthropic']))),
        ];

        foreach ($payloads as $index => $payload) {
            $this->assertStringNotContainsString(
                'sk-ant-secret',
                $payload,
                'Payload ' . $index . ' leaked the key value.'
            );

            // "api_key" legitimately appears as a requiredFields LABEL, telling
            // the UI which inputs to render. What must never appear is the field
            // carrying a value.
            $this->assertDoesNotMatchRegularExpression(
                '/"api_key"\s*:/',
                $payload,
                'Payload ' . $index . ' exposed an api_key value field.'
            );
        }
    }

    public function test_an_unknown_provider_is_rejected()
    {
        $response = $this->connections()->connect(new FakeRestRequest([
            'provider' => 'skynet',
            'api_key'  => 'x',
        ]));

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertSame(400, $response->get_error_data()['status']);
    }

    public function test_connecting_with_no_key_and_no_stored_key_is_refused()
    {
        $response = $this->connections()->connect(new FakeRestRequest([
            'provider' => 'openai',
            'api_key'  => '   ',
        ]));

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertSame('sfcore_ai_key_required', $response->get_error_code());
    }

    public function test_an_empty_key_on_a_connected_provider_changes_only_the_model()
    {
        $connections = $this->connections();
        $connections->connect(new FakeRestRequest([
            'provider' => 'openai', 'api_key' => 'sk-openai', 'model' => 'gpt-4o',
        ]));

        $connections->connect(new FakeRestRequest([
            'provider' => 'openai', 'api_key' => '', 'model' => 'gpt-4o-mini',
        ]));

        $this->assertSame('sk-openai', $this->settings->getKey('openai'));
        $this->assertSame('gpt-4o-mini', $this->settings->getModel('openai'));
    }

    // --- Disconnect ---------------------------------------------------------

    public function test_disconnecting_deletes_the_key()
    {
        $connections = $this->connections();
        $connections->connect(new FakeRestRequest([
            'provider' => 'anthropic', 'api_key' => 'sk-ant', 'model' => 'claude-opus-5',
        ]));

        $response = $connections->disconnect(new FakeRestRequest(['provider' => 'anthropic']));

        $this->assertTrue($response['disconnected']);
        $this->assertNull($this->settings->getKey('anthropic'));
    }

    // --- Settings write -----------------------------------------------------

    public function test_settings_write_ignores_any_api_key_smuggled_into_it()
    {
        $this->settingsController()->write(new FakeRestRequest([
            'api_key' => 'sk-should-be-ignored',
            'enabled' => false,
        ]));

        $this->assertNull($this->settings->getKey('anthropic'));
        $this->assertStringNotContainsString(
            'sk-should-be-ignored',
            json_encode($GLOBALS['sfcore_test_options'])
        );
    }

    public function test_selecting_an_unconnected_provider_is_refused()
    {
        $response = $this->settingsController()->write(new FakeRestRequest(['active' => 'openai']));

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertSame(409, $response->get_error_data()['status']);
    }

    public function test_enabling_without_a_connection_does_not_turn_ai_on()
    {
        $response = $this->settingsController()->write(new FakeRestRequest(['enabled' => true]));

        $this->assertFalse($response['settings']['enabled']);
    }

    public function test_brand_context_is_saved_through_the_settings_route()
    {
        $response = $this->settingsController()->write(new FakeRestRequest([
            'brand' => ['siteName' => 'Ramanops', 'designStyle' => 'minimalist'],
        ]));

        $this->assertSame('Ramanops', $response['settings']['brand']['siteName']);
        $this->assertSame('minimalist', $response['settings']['brand']['designStyle']);
    }

    public function test_models_are_exposed_per_provider_for_the_dropdown()
    {
        $models = $this->settingsController()->models()['models'];

        $this->assertArrayHasKey('anthropic', $models);
        $this->assertArrayHasKey('claude-opus-5', $models['anthropic']);
        $this->assertArrayHasKey('gpt-4o', $models['openai']);
    }

    public function test_routes_register_under_the_existing_namespace()
    {
        $GLOBALS['sfcore_test_routes'] = [];

        $this->connections()->registerRoutes();
        $this->settingsController()->registerRoutes();

        $registered = array_keys($GLOBALS['sfcore_test_routes']);

        $this->assertContains('swishfolio/v1/ai/connections', $registered);
        $this->assertContains('swishfolio/v1/ai/connections/test', $registered);
        $this->assertContains('swishfolio/v1/ai/connections/(?P<provider>[a-z0-9-]+)', $registered);
        $this->assertContains('swishfolio/v1/ai/settings', $registered);
        $this->assertContains('swishfolio/v1/ai/models', $registered);
    }
}
