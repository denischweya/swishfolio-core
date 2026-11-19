<?php

namespace SwishfolioCore\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\Services\EncryptionService;

/**
 * The old format stored `base64(iv . '::' . ciphertext)` and split on the first
 * '::'. Two things broke it, and the smaller one is the obvious one:
 *
 *  - an IV containing '::' (about 1 in 4,000), and
 *  - an IV whose LAST byte is a single ':' (about 1 in 256) — because
 *    `iv . '::'` then contains '::' one byte early, truncating the IV to 15
 *    bytes and decrypting to garbage.
 *
 * Together: roughly 1 in 254 credentials corrupted, and 96% of those returned a
 * mangled NON-EMPTY string, which defeats every `if (empty($key))` check
 * downstream. Measured at 785 failures in 200,000 round trips.
 */
class EncryptionServiceTest extends TestCase
{
    private function service()
    {
        return new EncryptionService();
    }

    public function test_a_value_round_trips()
    {
        $service = $this->service();

        $this->assertSame('sk-ant-secret', $service->decrypt($service->encrypt('sk-ant-secret')));
    }

    public function test_an_empty_value_is_passed_through()
    {
        $service = $this->service();

        $this->assertSame('', $service->encrypt(''));
        $this->assertSame('', $service->decrypt(''));
    }

    public function test_ciphertext_differs_between_calls_so_the_iv_is_not_reused()
    {
        $service = $this->service();

        $this->assertNotSame($service->encrypt('same-value'), $service->encrypt('same-value'));
    }

    /**
     * The regression guard. The old format failed roughly 1 in 254 times, so
     * 20,000 round trips would have produced ~79 failures. The new format uses
     * a fixed 16-byte offset and no delimiter, so it cannot collide at all.
     */
    public function test_twenty_thousand_round_trips_never_corrupt_a_value()
    {
        $service = $this->service();
        $failures = [];

        for ($i = 0; $i < 20000; $i++) {
            $value     = 'sk-test-' . $i;
            $decrypted = $service->decrypt($service->encrypt($value));

            if ($decrypted !== $value) {
                $failures[] = $i . ': got ' . var_export($decrypted, true);

                if (count($failures) >= 5) {
                    break;
                }
            }
        }

        $this->assertSame([], $failures, 'Corrupted round trips: ' . implode('; ', $failures));
    }

    public function test_new_ciphertext_carries_a_version_prefix()
    {
        $this->assertStringStartsWith(
            EncryptionService::PREFIX,
            $this->service()->encrypt('sk-test')
        );
    }

    // --- Backward compatibility with values already on disk ------------------

    public function test_a_legacy_value_still_decrypts()
    {
        $service = $this->service();
        $legacy  = $this->legacyEncrypt('sk-legacy-value', str_repeat('A', 16));

        $this->assertSame('sk-legacy-value', $service->decrypt($legacy));
    }

    public function test_a_legacy_value_whose_iv_ends_in_a_colon_is_reported_unreadable_not_mangled()
    {
        $service = $this->service();

        // The dominant historical failure: IV ending in ':'.
        $legacy = $this->legacyEncrypt('sk-legacy-value', str_repeat('A', 15) . ':');
        $result = $service->decrypt($legacy);

        // It cannot be recovered — the IV is gone. What matters is that it does
        // not return plausible-looking garbage that downstream code accepts.
        $this->assertSame(
            '',
            $result,
            'A credential that cannot be decrypted must read as absent, never as a mangled string.'
        );
    }

    public function test_is_encrypted_recognises_both_formats()
    {
        $service = $this->service();

        $this->assertTrue($service->isEncrypted($service->encrypt('sk-test')));
        $this->assertTrue($service->isEncrypted($this->legacyEncrypt('sk-test', str_repeat('A', 16))));
        $this->assertFalse($service->isEncrypted('plain-text-key'));
        $this->assertFalse($service->isEncrypted(''));
    }

    public function test_a_legacy_value_can_be_detected_so_it_can_be_re_encrypted()
    {
        $service = $this->service();

        $this->assertTrue($service->isLegacy($this->legacyEncrypt('sk-test', str_repeat('A', 16))));
        $this->assertFalse($service->isLegacy($service->encrypt('sk-test')));
    }

    public function test_garbage_input_decrypts_to_empty_rather_than_warning()
    {
        $service = $this->service();

        $this->assertSame('', $service->decrypt('not-base64-at-all!!!'));
        $this->assertSame('', $service->decrypt(EncryptionService::PREFIX . 'dG9vLXNob3J0'));
    }

    /**
     * Recreate the pre-fix storage format with a chosen IV.
     *
     * @param string $value Plaintext.
     * @param string $iv    Exactly 16 bytes.
     *
     * @return string
     */
    private function legacyEncrypt($value, $iv)
    {
        $key       = hash('sha256', defined('AUTH_KEY') ? AUTH_KEY : 'swishfolio-default-key', true);
        $encrypted = openssl_encrypt($value, 'aes-256-cbc', $key, 0, $iv);

        return base64_encode($iv . '::' . $encrypted);
    }
}
