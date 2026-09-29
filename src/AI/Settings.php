<?php
/**
 * AI provider settings, with the key encrypted at rest.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI;

/**
 * The raw API key never leaves PHP. get() is what the REST layer and the
 * editor are allowed to see; getKey() is for server-side provider calls only.
 *
 * Reuses the EncryptionService already storing ESP credentials, so no new
 * secret-handling code is introduced.
 */
class Settings
{
    const OPTION = 'swishfolio_ai_settings';

    /** @var object Anything exposing encrypt/decrypt/isEncrypted. */
    private $encryption;

    /**
     * @param object $encryption EncryptionService instance.
     */
    public function __construct($encryption)
    {
        $this->encryption = $encryption;
    }

    /**
     * Settings safe to expose to JavaScript.
     *
     * @return array
     */
    public function get()
    {
        $stored = $this->raw();

        return [
            'provider' => isset($stored['provider']) ? $stored['provider'] : 'openai',
            'model'    => isset($stored['model']) ? $stored['model'] : 'gpt-4o',
            'base_url' => isset($stored['base_url']) ? $stored['base_url'] : '',
            'enabled'  => ! empty($stored['enabled']),
            'hasKey'   => $this->hasKey(),
        ];
    }

    /**
     * @return bool
     */
    public function hasKey()
    {
        $stored = $this->raw();

        return ! empty($stored['api_key']);
    }

    /**
     * Server-side only. Never return this over REST.
     *
     * @return string|null
     */
    public function getKey()
    {
        $stored = $this->raw();

        if (empty($stored['api_key'])) {
            return null;
        }

        return $this->encryption->decrypt((string) $stored['api_key']);
    }

    /**
     * @param array $settings Incoming settings. An empty api_key preserves the
     *                        stored one, so the masked form can be resubmitted.
     *
     * @return void
     */
    public function save(array $settings)
    {
        $stored = $this->raw();

        $next = [
            'provider' => isset($settings['provider']) ? (string) $settings['provider'] : 'openai',
            'model'    => isset($settings['model']) ? (string) $settings['model'] : 'gpt-4o',
            'base_url' => isset($settings['base_url']) ? (string) $settings['base_url'] : '',
            'enabled'  => ! empty($settings['enabled']),
            'api_key'  => isset($stored['api_key']) ? $stored['api_key'] : '',
        ];

        if (! empty($settings['api_key'])) {
            $next['api_key'] = $this->encryption->encrypt((string) $settings['api_key']);
        }

        update_option(self::OPTION, $next, false);
    }

    /**
     * @return array
     */
    private function raw()
    {
        $stored = get_option(self::OPTION, []);

        return is_array($stored) ? $stored : [];
    }
}
