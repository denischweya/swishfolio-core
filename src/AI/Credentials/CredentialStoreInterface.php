<?php
/**
 * Contract for storing AI provider credentials.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Credentials;

/**
 * Providers never learn how their credentials are stored, and callers never
 * touch encryption. That separation is what lets the storage move to a table,
 * or to a hosted token, without changing a single provider.
 */
interface CredentialStoreInterface
{
    /**
     * @param string $provider   Provider slug.
     * @param string $credential Plaintext credential.
     *
     * @throws \InvalidArgumentException When the slug is not a valid provider slug.
     *
     * @return void
     */
    public function save($provider, $credential);

    /**
     * @param string $provider Provider slug.
     *
     * @return string|null Plaintext credential, or null when absent.
     */
    public function get($provider);

    /**
     * @param string $provider Provider slug.
     *
     * @return bool
     */
    public function has($provider);

    /**
     * @param string $provider Provider slug.
     *
     * @return void
     */
    public function delete($provider);
}
