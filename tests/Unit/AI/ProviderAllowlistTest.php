<?php

namespace SwishfolioCore\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Credentials\WordPressCredentialStore;
use SwishfolioCore\AI\Settings;

/**
 * The bug the catalogue was built to fix.
 *
 * The slug allowlist was a hardcoded constant enforced in nine places, so the
 * documented swishfolio_ai_providers filter could register a provider class that
 * the settings, the connect route and the admin screen all then refused by slug.
 * A third party could add a provider and never connect it.
 *
 * These assertions are about the new providers working through the same path,
 * and about a filtered-in one working at all.
 */
class ProviderAllowlistTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['sfcore_test_options'] = [];

        unset($GLOBALS['sfcore_test_filters']['swishfolio_ai_providers']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['sfcore_test_filters']['swishfolio_ai_providers']);

        parent::tearDown();
    }

    private function settings()
    {
        return new Settings(new WordPressCredentialStore(new FakeEncryptionService()));
    }

    /**
     * @return string[]
     */
    public static function newProviders()
    {
        return [
            'gemini'     => ['gemini'],
            'mistral'    => ['mistral'],
            'groq'       => ['groq'],
            'openrouter' => ['openrouter'],
        ];
    }

    /**
     * @dataProvider newProviders
     *
     * @param string $slug Provider slug.
     *
     * @return void
     */
    public function test_each_new_provider_can_be_connected_and_becomes_active($slug)
    {
        $settings = $this->settings();

        $settings->connect($slug, 'test-key');

        $this->assertSame($slug, $settings->getActive());
        $this->assertTrue($settings->get()['providers'][$slug]['connected']);
    }

    /**
     * @dataProvider newProviders
     *
     * @param string $slug Provider slug.
     *
     * @return void
     */
    public function test_each_new_provider_appears_in_the_settings_payload($slug)
    {
        $this->assertArrayHasKey($slug, $this->settings()->get()['providers']);
    }

    public function test_a_model_can_be_chosen_per_provider()
    {
        $settings = $this->settings();

        $settings->connect('groq', 'test-key');
        $settings->setModel('groq', 'llama-3.3-70b-versatile');

        $this->assertSame('llama-3.3-70b-versatile', $settings->get()['providers']['groq']['model']);
    }

    public function test_two_providers_can_be_connected_at_once_with_one_active()
    {
        $settings = $this->settings();

        $settings->connect('gemini', 'key-a');
        $settings->connect('mistral', 'key-b');
        $settings->setActive('mistral');

        $payload = $settings->get();

        $this->assertTrue($payload['providers']['gemini']['connected']);
        $this->assertTrue($payload['providers']['mistral']['connected']);
        $this->assertSame('mistral', $payload['active']);
    }

    public function test_disconnecting_the_active_provider_falls_back_to_another_connected_one()
    {
        $settings = $this->settings();

        $settings->connect('gemini', 'key-a');
        $settings->connect('groq', 'key-b');
        $settings->setActive('groq');

        $settings->disconnect('groq');

        $this->assertSame('gemini', $settings->getActive(), 'Falls back rather than pointing at nothing.');
    }

    public function test_an_unknown_slug_is_still_refused()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->settings()->connect('definitely-not-a-provider', 'test-key');
    }

    public function test_a_filtered_in_provider_can_now_be_connected()
    {
        // This is what the hardcoded constant made impossible.
        $GLOBALS['sfcore_test_filters']['swishfolio_ai_providers'] = static function ($providers) {
            $providers['acme'] = new FakeProvider();

            return $providers;
        };

        $settings = $this->settings();

        $settings->connect('acme', 'test-key');

        $this->assertSame('acme', $settings->getActive());
        $this->assertArrayHasKey('acme', $settings->get()['providers']);
    }
}
