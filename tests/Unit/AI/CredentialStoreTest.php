<?php

namespace SwishfolioCore\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Credentials\WordPressCredentialStore;

class CredentialStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sfcore_test_options'] = [];
    }

    private function store()
    {
        return new WordPressCredentialStore(new FakeEncryptionService());
    }

    public function test_a_saved_credential_round_trips()
    {
        $store = $this->store();
        $store->save('anthropic', 'sk-ant-secret');

        $this->assertSame('sk-ant-secret', $store->get('anthropic'));
    }

    public function test_credentials_are_stored_encrypted_not_plaintext()
    {
        $this->store()->save('openai', 'sk-openai-secret');

        $this->assertStringNotContainsString(
            'sk-openai-secret',
            json_encode($GLOBALS['sfcore_test_options'])
        );
    }

    public function test_providers_are_isolated_from_each_other()
    {
        $store = $this->store();
        $store->save('openai', 'key-a');
        $store->save('anthropic', 'key-b');

        $this->assertSame('key-a', $store->get('openai'));
        $this->assertSame('key-b', $store->get('anthropic'));
    }

    public function test_an_absent_credential_is_null()
    {
        $this->assertNull($this->store()->get('anthropic'));
        $this->assertFalse($this->store()->has('anthropic'));
    }

    public function test_deleting_one_provider_leaves_the_other_connected()
    {
        $store = $this->store();
        $store->save('openai', 'key-a');
        $store->save('anthropic', 'key-b');

        $store->delete('openai');

        $this->assertFalse($store->has('openai'));
        $this->assertTrue($store->has('anthropic'));
        $this->assertSame('key-b', $store->get('anthropic'));
    }

    public function test_credentials_live_in_their_own_option_away_from_settings()
    {
        $this->store()->save('anthropic', 'sk-ant');

        // A settings export must not be able to leak keys.
        $this->assertArrayHasKey(WordPressCredentialStore::OPTION, $GLOBALS['sfcore_test_options']);
        $this->assertArrayNotHasKey('swishfolio_ai_settings', $GLOBALS['sfcore_test_options']);
    }

    public function test_an_unknown_provider_slug_is_rejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->store()->save('../../etc/passwd', 'x');
    }
}
