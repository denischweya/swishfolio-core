<?php

namespace SwishfolioCore\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
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
        return new Settings(new FakeEncryptionService());
    }

    public function test_get_never_exposes_the_api_key()
    {
        $settings = $this->settings();
        $settings->save(['provider' => 'openai', 'model' => 'gpt-4o', 'api_key' => 'sk-secret']);

        $public = $settings->get();

        $this->assertArrayNotHasKey('api_key', $public);
        $this->assertSame('openai', $public['provider']);
        $this->assertTrue($public['hasKey']);
    }

    public function test_key_round_trips_through_encryption()
    {
        $settings = $this->settings();
        $settings->save(['provider' => 'openai', 'model' => 'gpt-4o', 'api_key' => 'sk-secret']);

        $this->assertSame('sk-secret', $settings->getKey());
    }

    public function test_stored_key_is_not_plaintext()
    {
        $settings = $this->settings();
        $settings->save(['provider' => 'openai', 'model' => 'gpt-4o', 'api_key' => 'sk-secret']);

        $this->assertStringNotContainsString(
            'sk-secret',
            json_encode($GLOBALS['sfcore_test_options'][Settings::OPTION])
        );
    }

    public function test_saving_with_an_empty_key_preserves_the_existing_one()
    {
        $settings = $this->settings();
        $settings->save(['provider' => 'openai', 'model' => 'gpt-4o', 'api_key' => 'sk-secret']);
        $settings->save(['provider' => 'openai', 'model' => 'gpt-4o-mini', 'api_key' => '']);

        $this->assertSame('sk-secret', $settings->getKey());
        $this->assertSame('gpt-4o-mini', $settings->get()['model']);
    }

    public function test_has_key_is_false_before_anything_is_saved()
    {
        $this->assertFalse($this->settings()->hasKey());
        $this->assertNull($this->settings()->getKey());
    }
}
