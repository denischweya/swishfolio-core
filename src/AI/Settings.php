<?php
/**
 * AI provider settings and brand context.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI;

use SwishfolioCore\AI\Credentials\CredentialStoreInterface;

/**
 * BYOK: the site owner connects their own OpenAI and/or Anthropic account and
 * pays that provider directly. This plugin ships no credits and no AI backend.
 *
 * Both providers can be connected at once; `active` decides which one requests
 * actually use. Credentials live in the CredentialStore — a separate option —
 * so nothing here can leak a key, and this class never returns one to callers
 * that only asked for settings.
 */
class Settings
{
    const OPTION = 'swishfolio_ai_settings';

    /** Providers this plugin knows how to connect. */
    const PROVIDERS = ['anthropic', 'openai', 'openai-compatible'];

    /** Brand vocabularies. Free text would reach the prompt unvalidated. */
    const DESIGN_STYLES = ['minimalist', 'tech-modern', 'corporate', 'brutalist', 'luxury', 'organic'];
    const BLOCK_SHAPES  = ['sharp', 'soft', 'rounded', 'pill'];
    const TONES         = ['professional', 'friendly', 'persuasive', 'educational', 'enthusiastic'];

    const DESCRIPTION_LIMIT = 500;

    /** @var CredentialStoreInterface */
    private $credentials;

    /** @var bool */
    private $migrated = false;

    public function __construct(CredentialStoreInterface $credentials)
    {
        $this->credentials = $credentials;
    }

    // -------------------------------------------------------------- Connections

    /**
     * Store a provider credential and mark it connected.
     *
     * @param string      $provider Provider slug.
     * @param string      $apiKey   Plaintext key.
     * @param string|null $model    Model to use, or null to keep the current one.
     * @param string      $baseUrl  Only meaningful for openai-compatible.
     *
     * @throws \InvalidArgumentException When the provider is unknown.
     *
     * @return void
     */
    public function connect($provider, $apiKey, $model = null, $baseUrl = '')
    {
        $provider = $this->assertProvider($provider);

        if ((string) $apiKey !== '') {
            $this->credentials->save($provider, (string) $apiKey);
        }

        $stored = $this->raw();

        if (! isset($stored['providers'][$provider]) || ! is_array($stored['providers'][$provider])) {
            $stored['providers'][$provider] = [];
        }

        if ($model !== null && (string) $model !== '') {
            $stored['providers'][$provider]['model'] = sanitize_text_field((string) $model);
        }

        if ((string) $baseUrl !== '') {
            $stored['providers'][$provider]['base_url'] = esc_url_raw((string) $baseUrl);
        }

        $stored['providers'][$provider]['status'] = 'connected';

        // The first provider connected becomes the one requests use, so a user
        // who connects one account is not left with nothing selected.
        if (empty($stored['active']) || ! $this->credentials->has($stored['active'])) {
            $stored['active'] = $provider;
        }

        $this->write($stored);
    }

    /**
     * Forget a provider entirely: credential, model and status.
     *
     * Never touches the user's account at the provider — only our copy.
     *
     * @param string $provider Provider slug.
     *
     * @return void
     */
    public function disconnect($provider)
    {
        $provider = $this->assertProvider($provider);

        $this->credentials->delete($provider);

        $stored = $this->raw();
        unset($stored['providers'][$provider]);

        if (isset($stored['active']) && $stored['active'] === $provider) {
            $stored['active'] = '';

            // Fall back to any provider that still has a credential rather than
            // leaving AI pointed at one that cannot answer.
            foreach (self::PROVIDERS as $candidate) {
                if ($this->credentials->has($candidate)) {
                    $stored['active'] = $candidate;
                    break;
                }
            }
        }

        if ($stored['active'] === '') {
            $stored['enabled'] = false;
        }

        $this->write($stored);
    }

    /**
     * @param string $provider Provider slug.
     *
     * @return bool
     */
    public function isConnected($provider)
    {
        $this->migrate();

        return $this->credentials->has($this->assertProvider($provider));
    }

    /**
     * Record the outcome of a Test Connection.
     *
     * @param string $provider Provider slug.
     * @param bool   $ok       Whether the credential worked.
     * @param int    $now      Timestamp, injected so this stays testable.
     *
     * @return void
     */
    public function markValidated($provider, $ok, $now)
    {
        $provider = $this->assertProvider($provider);
        $stored   = $this->raw();

        if (! isset($stored['providers'][$provider])) {
            return;
        }

        $stored['providers'][$provider]['status']            = $ok ? 'connected' : 'invalid';
        $stored['providers'][$provider]['last_validated_at'] = (int) $now;

        $this->write($stored);
    }

    // ------------------------------------------------------------ Active choice

    /**
     * @return string Active provider slug, or '' when none is connected.
     */
    public function getActive()
    {
        $stored = $this->raw();
        $active = isset($stored['active']) ? (string) $stored['active'] : '';

        return in_array($active, self::PROVIDERS, true) ? $active : '';
    }

    /**
     * @param string $provider Provider slug.
     *
     * @return void
     */
    public function setActive($provider)
    {
        $provider = $this->assertProvider($provider);
        $stored   = $this->raw();

        $stored['active'] = $provider;

        $this->write($stored);
    }

    /**
     * @param string|null $provider Provider slug, or null for the active one.
     *
     * @return string
     */
    public function getModel($provider = null)
    {
        $provider = $provider === null ? $this->getActive() : $this->assertProvider($provider);
        $stored   = $this->raw();

        if ($provider !== '' && ! empty($stored['providers'][$provider]['model'])) {
            return (string) $stored['providers'][$provider]['model'];
        }

        return '';
    }

    /**
     * @param string $provider Provider slug.
     * @param string $model    Model identifier.
     *
     * @return void
     */
    public function setModel($provider, $model)
    {
        $provider = $this->assertProvider($provider);
        $stored   = $this->raw();

        if (! isset($stored['providers'][$provider])) {
            $stored['providers'][$provider] = [];
        }

        $stored['providers'][$provider]['model'] = sanitize_text_field((string) $model);

        $this->write($stored);
    }

    /**
     * @param string|null $provider Provider slug, or null for the active one.
     *
     * @return string
     */
    public function getBaseUrl($provider = null)
    {
        $provider = $provider === null ? $this->getActive() : $this->assertProvider($provider);
        $stored   = $this->raw();

        return $provider !== '' && ! empty($stored['providers'][$provider]['base_url'])
            ? (string) $stored['providers'][$provider]['base_url']
            : '';
    }

    // ------------------------------------------------------------------- Toggle

    /**
     * @param bool $enabled Whether AI features may run.
     *
     * @return void
     */
    public function setEnabled($enabled)
    {
        $stored = $this->raw();

        // Enabling with no credential would produce a toggle that promises
        // something the site cannot deliver.
        $stored['enabled'] = (bool) $enabled && $this->getActive() !== ''
            && $this->credentials->has($this->getActive());

        $this->write($stored);
    }

    // -------------------------------------------------------------------- Brand

    /**
     * Brand context blended into generation prompts.
     *
     * @return array
     */
    public function getBrand()
    {
        $stored = $this->raw();
        $brand  = isset($stored['brand']) && is_array($stored['brand']) ? $stored['brand'] : [];

        return array_merge([
            'siteName'        => '',
            'siteDescription' => '',
            'designStyle'     => '',
            'blockShapes'     => '',
            'copywritingTone' => '',
        ], $brand);
    }

    /**
     * @param array $brand Incoming brand fields.
     *
     * @return void
     */
    public function saveBrand(array $brand)
    {
        $current = $this->getBrand();

        $next = [
            'siteName'        => isset($brand['siteName'])
                ? sanitize_text_field(trim((string) $brand['siteName']))
                : $current['siteName'],
            'siteDescription' => isset($brand['siteDescription'])
                ? substr(sanitize_textarea_field_compat(trim((string) $brand['siteDescription'])), 0, self::DESCRIPTION_LIMIT)
                : $current['siteDescription'],
            'designStyle'     => $this->oneOf($brand, 'designStyle', self::DESIGN_STYLES, $current['designStyle']),
            'blockShapes'     => $this->oneOf($brand, 'blockShapes', self::BLOCK_SHAPES, $current['blockShapes']),
            'copywritingTone' => $this->oneOf($brand, 'copywritingTone', self::TONES, $current['copywritingTone']),
        ];

        $stored          = $this->raw();
        $stored['brand'] = $next;

        $this->write($stored);
    }

    // ------------------------------------------------------------------- Public

    /**
     * Everything safe to hand to JavaScript. Never a credential.
     *
     * Keeps the flat `provider`/`model`/`base_url`/`hasKey` keys describing the
     * ACTIVE provider so existing callers keep working, alongside the
     * per-provider map the settings UI needs.
     *
     * @return array
     */
    public function get()
    {
        $this->migrate();

        $stored    = $this->raw();
        $active    = $this->getActive();
        $providers = [];

        foreach (self::PROVIDERS as $slug) {
            $entry = isset($stored['providers'][$slug]) && is_array($stored['providers'][$slug])
                ? $stored['providers'][$slug]
                : [];

            $providers[$slug] = [
                'connected'       => $this->credentials->has($slug),
                'model'           => isset($entry['model']) ? (string) $entry['model'] : '',
                'baseUrl'         => isset($entry['base_url']) ? (string) $entry['base_url'] : '',
                'status'          => isset($entry['status']) ? (string) $entry['status'] : 'disconnected',
                'lastValidatedAt' => isset($entry['last_validated_at']) ? (int) $entry['last_validated_at'] : 0,
            ];
        }

        return [
            'active'    => $active,
            'enabled'   => ! empty($stored['enabled']),
            'providers' => $providers,
            'brand'     => $this->getBrand(),

            // Flat view of the active provider, for callers that predate BYOK.
            'provider'  => $active,
            'model'     => $this->getModel(),
            'base_url'  => $this->getBaseUrl(),
            'hasKey'    => $active !== '' && $this->credentials->has($active),
        ];
    }

    /**
     * Server-side only. Never return this over REST.
     *
     * @param string|null $provider Provider slug, or null for the active one.
     *
     * @return string|null
     */
    public function getKey($provider = null)
    {
        $this->migrate();

        $provider = $provider === null ? $this->getActive() : $this->assertProvider($provider);

        return $provider === '' ? null : $this->credentials->get($provider);
    }

    /**
     * @return bool Whether the active provider has a credential.
     */
    public function hasKey()
    {
        return $this->getKey() !== null;
    }

    // ------------------------------------------------------------------ Internals

    /**
     * Move a pre-BYOK single-provider option into the new shape.
     *
     * The old option held provider/model/base_url/enabled plus an encrypted
     * api_key inline. The credential moves to the store and the inline copy is
     * removed, so a settings dump can no longer contain a key.
     *
     * @return void
     */
    private function migrate()
    {
        if ($this->migrated) {
            return;
        }

        $this->migrated = true;

        $stored = get_option(self::OPTION, []);

        if (! is_array($stored) || ! array_key_exists('api_key', $stored)) {
            return;
        }

        $provider = isset($stored['provider']) && in_array($stored['provider'], self::PROVIDERS, true)
            ? (string) $stored['provider']
            : 'openai';

        $next = [
            'active'    => $provider,
            'enabled'   => ! empty($stored['enabled']),
            'providers' => [
                $provider => [
                    'model'    => isset($stored['model']) ? (string) $stored['model'] : '',
                    'base_url' => isset($stored['base_url']) ? (string) $stored['base_url'] : '',
                    'status'   => empty($stored['api_key']) ? 'disconnected' : 'connected',
                ],
            ],
            'brand'     => isset($stored['brand']) && is_array($stored['brand']) ? $stored['brand'] : [],
        ];

        update_option(self::OPTION, $next, false);

        if (! empty($stored['api_key'])) {
            // Already ciphertext in the old option; hand it over verbatim so the
            // store owns it without a decrypt/encrypt round trip.
            $existing                  = get_option(WordPressCredentialStoreOption::NAME, []);
            $existing                  = is_array($existing) ? $existing : [];
            $existing[$provider]       = $stored['api_key'];

            update_option(WordPressCredentialStoreOption::NAME, $existing, false);
        }
    }

    /**
     * @return array
     */
    private function raw()
    {
        $this->migrate();

        $stored = get_option(self::OPTION, []);
        $stored = is_array($stored) ? $stored : [];

        if (! isset($stored['providers']) || ! is_array($stored['providers'])) {
            $stored['providers'] = [];
        }

        return $stored;
    }

    /**
     * @param array $stored Settings to persist.
     *
     * @return void
     */
    private function write(array $stored)
    {
        update_option(self::OPTION, $stored, false);
    }

    /**
     * @param array    $input   Incoming data.
     * @param string   $key     Field name.
     * @param string[] $allowed Permitted values.
     * @param string   $current Existing value.
     *
     * @return string
     */
    private function oneOf(array $input, $key, array $allowed, $current)
    {
        if (! isset($input[$key])) {
            return $current;
        }

        $value = (string) $input[$key];

        if ($value === '') {
            return '';
        }

        return in_array($value, $allowed, true) ? $value : '';
    }

    /**
     * @param string $provider Candidate slug.
     *
     * @throws \InvalidArgumentException When unknown.
     *
     * @return string
     */
    private function assertProvider($provider)
    {
        $provider = (string) $provider;

        if (! in_array($provider, self::PROVIDERS, true)) {
            throw new \InvalidArgumentException(
                sprintf('"%s" is not a supported AI provider.', $provider)
            );
        }

        return $provider;
    }
}

/**
 * The credential option name, referenced during migration without coupling
 * Settings to a concrete store implementation.
 */
class WordPressCredentialStoreOption
{
    const NAME = 'swishfolio_ai_credentials';
}

if (! function_exists('SwishfolioCore\AI\sanitize_textarea_field_compat')) {
    /**
     * sanitize_textarea_field() when WordPress is loaded, a safe fallback otherwise.
     *
     * @param string $value Raw value.
     *
     * @return string
     */
    function sanitize_textarea_field_compat($value)
    {
        return function_exists('sanitize_textarea_field')
            ? sanitize_textarea_field($value)
            : trim(strip_tags((string) $value));
    }
}
