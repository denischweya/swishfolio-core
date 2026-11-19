<?php

namespace SwishfolioCore\Tests\Unit\AI;

/**
 * Stands in for SwishfolioCore\Services\EncryptionService, which needs
 * WordPress salts. Same contract, reversible without real crypto.
 */
class FakeEncryptionService
{
    public function encrypt(string $value): string
    {
        return 'enc:' . base64_encode($value);
    }

    public function decrypt(string $value): string
    {
        return strpos($value, 'enc:') === 0
            ? (string) base64_decode(substr($value, 4))
            : $value;
    }

    public function isEncrypted(string $value): bool
    {
        return strpos($value, 'enc:') === 0;
    }
}
