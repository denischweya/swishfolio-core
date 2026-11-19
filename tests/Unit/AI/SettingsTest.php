<?php

namespace SwishfolioCore\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Credentials\WordPressCredentialStore;
use SwishfolioCore\AI\Settings;

class SettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sfcore_test_options'] = [];
    }

    private function settings()
    {
        return new Settings(new WordPressCredentialStore(new FakeEncryptionService()));
    }

    // --- BYOK: both providers connect independently --------------------------

    public function test_both_providers_can_be_connected_at_once()
    {
        $settings = $this->settings();
        $settings->connect('openai', 'sk-openai', 'gpt-4o');
        $settings->connect('anthropic', 'sk-ant', 'claude-opus-5');

        $this->assertTrue($settings->isConnected('openai'));
        $this->assertTrue($settings->isConnected('anthropic'));
        $this->assertSame('gpt-4o', $settings->getModel('openai'));
        $this->assertSame('claude-opus-5', $settings->getModel('anthropic'));
    }

    public function test_the_active_provider_selects_which_key_is_used()
    {
        $settings = $this->settings();
        $settings->connect('openai', 'sk-openai', 'gpt-4o');
        $settings->connect('anthropic', 'sk-ant', 'claude-opus-5');

        $settings->setActive('anthropic');

        $this->assertSame('anthropic', $settings->getActive());
        $this->assertSame('sk-ant', $settings->getKey());
        $this->assertSame('sk-openai', $settings->getKey('openai'));
    }

    public function test_connecting_the_first_provider_makes_it_active()
    {
        $settings = $this->settings();
        $settings->connect('anthropic', 'sk-ant', 'claude-opus-5');

        $this->assertSame('anthropic', $settings->getActive());
    }

    public function test_disconnecting_removes_only_that_provider()
    {
        $settings = $this->settings();
        $settings->connect('openai', 'sk-openai', 'gpt-4o');
        $settings->connect('anthropic', 'sk-ant', 'claude-opus-5');

        $settings->disconnect('openai');

        $this->assertFalse($settings->isConnected('openai'));
        $this->assertNull($settings->getKey('openai'));
        $this->assertTrue($settings->isConnected('anthropic'));
        $this->assertSame('sk-ant', $settings->getKey('anthropic'));
    }

    public function test_disconnecting_the_active_provider_falls_back_to_a_connected_one()
    {
        $settings = $this->settings();
        $settings->connect('openai', 'sk-openai', 'gpt-4o');
        $settings->connect('anthropic', 'sk-ant', 'claude-opus-5');
        $settings->setActive('openai');

        $settings->disconnect('openai');

        $this->assertSame('anthropic', $settings->getActive(), 'AI must not be left pointing at a disconnected provider.');
    }

    public function test_disconnecting_the_last_provider_disables_ai()
    {
        $settings = $this->settings();
        $settings->connect('anthropic', 'sk-ant', 'claude-opus-5');
        $settings->setEnabled(true);

        $settings->disconnect('anthropic');

        $this->assertFalse($settings->get()['enabled'], 'Nothing can run with no credential, so the toggle must not stay on.');
    }

    // --- The key never leaves PHP -------------------------------------------

    public function test_get_never_exposes_any_api_key()
    {
        $settings = $this->settings();
        $settings->connect('openai', 'sk-openai-secret', 'gpt-4o');
        $settings->connect('anthropic', 'sk-ant-secret', 'claude-opus-5');

        $serialised = json_encode($settings->get());

        $this->assertStringNotContainsString('sk-openai-secret', $serialised);
        $this->assertStringNotContainsString('sk-ant-secret', $serialised);
        $this->assertStringNotContainsString('api_key', $serialised);
    }

    public function test_the_public_shape_reports_connection_state_per_provider()
    {
        $settings = $this->settings();
        $settings->connect('anthropic', 'sk-ant', 'claude-opus-5');

        $public = $settings->get();

        $this->assertTrue($public['providers']['anthropic']['connected']);
        $this->assertSame('claude-opus-5', $public['providers']['anthropic']['model']);
        $this->assertArrayHasKey('openai', $public['providers']);
        $this->assertFalse($public['providers']['openai']['connected']);
    }

    // --- Backward compatibility with the flat pre-BYOK option ---------------

    public function test_it_migrates_the_old_flat_single_provider_option()
    {
        $encryption = new FakeEncryptionService();

        $GLOBALS['sfcore_test_options']['swishfolio_ai_settings'] = [
            'provider' => 'openai',
            'model'    => 'gpt-4o-mini',
            'base_url' => '',
            'enabled'  => true,
            'api_key'  => $encryption->encrypt('sk-legacy'),
        ];

        $settings = new Settings(new WordPressCredentialStore($encryption));

        $this->assertTrue($settings->isConnected('openai'), 'A legacy key must survive the upgrade.');
        $this->assertSame('sk-legacy', $settings->getKey('openai'));
        $this->assertSame('gpt-4o-mini', $settings->getModel('openai'));
        $this->assertSame('openai', $settings->getActive());
        $this->assertTrue($settings->get()['enabled']);
    }

    public function test_the_legacy_key_is_removed_from_the_settings_option_after_migration()
    {
        $encryption = new FakeEncryptionService();

        $GLOBALS['sfcore_test_options']['swishfolio_ai_settings'] = [
            'provider' => 'openai',
            'model'    => 'gpt-4o',
            'enabled'  => false,
            'api_key'  => $encryption->encrypt('sk-legacy'),
        ];

        $settings = new Settings(new WordPressCredentialStore($encryption));
        $settings->get();

        $this->assertArrayNotHasKey(
            'api_key',
            $GLOBALS['sfcore_test_options']['swishfolio_ai_settings'],
            'The credential must move to the credential store, not be duplicated.'
        );
    }

    // --- Legacy accessor shape still works for existing callers -------------

    public function test_the_flat_accessors_still_describe_the_active_provider()
    {
        $settings = $this->settings();
        $settings->connect('anthropic', 'sk-ant', 'claude-opus-5');

        $public = $settings->get();

        $this->assertSame('anthropic', $public['provider']);
        $this->assertSame('claude-opus-5', $public['model']);
        $this->assertTrue($public['hasKey']);
    }

    // --- Brand context ------------------------------------------------------

    public function test_brand_context_round_trips_and_is_sanitised()
    {
        $settings = $this->settings();
        $settings->saveBrand([
            'siteName'        => '  Ramanops  ',
            'siteDescription' => 'We help SMEs grow.',
            'designStyle'     => 'minimalist',
            'blockShapes'     => 'soft',
            'copywritingTone' => 'professional',
        ]);

        $brand = $settings->getBrand();

        $this->assertSame('Ramanops', $brand['siteName']);
        $this->assertSame('minimalist', $brand['designStyle']);
    }

    public function test_an_unknown_design_style_is_rejected_rather_than_reaching_the_prompt()
    {
        $settings = $this->settings();
        $settings->saveBrand(['designStyle' => 'wormhole', 'blockShapes' => 'pill']);

        $this->assertSame('', $settings->getBrand()['designStyle']);
        $this->assertSame('pill', $settings->getBrand()['blockShapes']);
    }

    public function test_brand_description_is_length_capped()
    {
        $settings = $this->settings();
        $settings->saveBrand(['siteDescription' => str_repeat('a', 900)]);

        $this->assertSame(500, strlen($settings->getBrand()['siteDescription']));
    }

    public function test_enabling_requires_a_connected_provider()
    {
        $settings = $this->settings();
        $settings->setEnabled(true);

        $this->assertFalse($settings->get()['enabled'], 'AI cannot be enabled with no credential stored.');
    }
}
