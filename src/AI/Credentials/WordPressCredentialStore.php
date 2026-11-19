<?php
/**
 * Credential storage backed by a WordPress option.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Credentials;

/**
 * Keys live in their OWN option, deliberately separate from settings.
 *
 * Settings get exported, logged, diffed and shown in support threads;
 * credentials must not ride along. Keeping them in a different option means a
 * settings dump cannot leak a key even by accident.
 */
class WordPressCredentialStore implements CredentialStoreInterface
{
    const OPTION = 'swishfolio_ai_credentials';

    /** @var object Anything exposing encrypt/decrypt. */
    private $encryption;

    /**
     * @param object $encryption EncryptionService instance.
     */
    public function __construct($encryption)
    {
        $this->encryption = $encryption;
    }

    public function save($provider, $credential)
    {
        $provider = $this->assertSlug($provider);
        $stored   = $this->all();

        $stored[$provider] = $this->encryption->encrypt((string) $credential);

        update_option(self::OPTION, $stored, false);
    }

    public function get($provider)
    {
        $provider = $this->assertSlug($provider);
        $stored   = $this->all();

        if (empty($stored[$provider])) {
            return null;
        }

        return $this->encryption->decrypt((string) $stored[$provider]);
    }

    public function has($provider)
    {
        $stored = $this->all();

        return ! empty($stored[$this->assertSlug($provider)]);
    }

    public function delete($provider)
    {
        $provider = $this->assertSlug($provider);
        $stored   = $this->all();

        unset($stored[$provider]);

        update_option(self::OPTION, $stored, false);
    }

    /**
     * @return array Provider slug => encrypted credential.
     */
    private function all()
    {
        $stored = get_option(self::OPTION, []);

        return is_array($stored) ? $stored : [];
    }

    /**
     * Provider slugs become array keys, so they are validated rather than trusted.
     *
     * @param string $provider Candidate slug.
     *
     * @throws \InvalidArgumentException When invalid.
     *
     * @return string
     */
    private function assertSlug($provider)
    {
        $provider = (string) $provider;

        if (! preg_match('/^[a-z0-9-]{1,32}$/', $provider)) {
            throw new \InvalidArgumentException(
                sprintf('"%s" is not a valid provider slug.', $provider)
            );
        }

        return $provider;
    }
}
