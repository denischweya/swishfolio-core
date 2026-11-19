<?php
/**
 * REST routes for connecting AI providers (BYOK).
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\REST;

use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\Providers\ProviderCatalogue;
use SwishfolioCore\AI\Settings;

/**
 * Connect, test and disconnect a provider.
 *
 * The site owner brings their own key and pays the provider directly. A key
 * enters through here, is validated, encrypted and stored — and is never
 * returned by any route, on any path, to any caller.
 */
class ConnectionsController
{
    const NAMESPACE_V1 = 'swishfolio/v1';

    /** @var Settings */
    private $settings;

    /** @var ProviderRegistry */
    private $providers;

    public function __construct(Settings $settings, ProviderRegistry $providers)
    {
        $this->settings  = $settings;
        $this->providers = $providers;
    }

    /**
     * @return void
     */
    public function registerRoutes()
    {
        register_rest_route(self::NAMESPACE_V1, '/ai/connections', [
            'methods'             => 'POST',
            'callback'            => [$this, 'connect'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'provider' => ['type' => 'string', 'required' => true],
                'api_key'  => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/ai/connections/test', [
            'methods'             => 'POST',
            'callback'            => [$this, 'test'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'provider' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/ai/connections/(?P<provider>[a-z0-9-]+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'disconnect'],
            'permission_callback' => [$this, 'canManage'],
        ]);
    }

    /**
     * @return bool
     */
    public function canManage()
    {
        return current_user_can('manage_options');
    }

    /**
     * @param object $request REST request.
     *
     * @return mixed
     */
    public function connect($request)
    {
        $provider = $this->provider($request);

        if ($provider instanceof \WP_Error) {
            return $provider;
        }

        $apiKey = trim((string) $request->get_param('api_key'));

        if ($apiKey === '' && ! $this->settings->isConnected($provider)) {
            return new \WP_Error(
                'sfcore_ai_key_required',
                __('Enter an API key to connect this provider.', 'swishfolio-core'),
                ['status' => 400]
            );
        }

        $this->settings->connect(
            $provider,
            $apiKey,
            (string) $request->get_param('model'),
            (string) $request->get_param('base_url')
        );

        return rest_ensure_response([
            'connected' => true,
            'settings'  => $this->settings->publicView(),
        ]);
    }

    /**
     * Make a lightweight authenticated call to prove the credential works.
     *
     * @param object $request REST request.
     *
     * @return mixed
     */
    public function test($request)
    {
        $provider = $this->provider($request);

        if ($provider instanceof \WP_Error) {
            return $provider;
        }

        if (! $this->settings->isConnected($provider)) {
            return new \WP_Error(
                'sfcore_ai_not_connected',
                __('Connect that provider first.', 'swishfolio-core'),
                ['status' => 409]
            );
        }

        $previous = $this->settings->getActive();
        $this->settings->setActive($provider);

        $ok = false;

        try {
            $instance = $this->providers->configured();
            $ok       = $instance->testConnection();
        } catch (\Exception $e) {
            $ok = false;
        }

        $this->settings->markValidated($provider, $ok, time());

        if ($previous !== '' && $previous !== $provider) {
            $this->settings->setActive($previous);
        }

        // Raw provider errors are not surfaced: they leak endpoint detail and
        // read as noise to a site owner who just wants to know if the key works.
        return rest_ensure_response([
            'ok'      => $ok,
            'message' => $ok
                ? __('Connection successful — that key works.', 'swishfolio-core')
                : __('That key was rejected. Check it is correct, active, and has credit.', 'swishfolio-core'),
        ]);
    }

    /**
     * @param object $request REST request.
     *
     * @return mixed
     */
    public function disconnect($request)
    {
        $provider = $this->provider($request);

        if ($provider instanceof \WP_Error) {
            return $provider;
        }

        $this->settings->disconnect($provider);

        return rest_ensure_response([
            'disconnected' => true,
            'settings'     => $this->settings->publicView(),
        ]);
    }

    /**
     * @param object $request REST request.
     *
     * @return string|\WP_Error
     */
    private function provider($request)
    {
        $provider = sanitize_key((string) $request->get_param('provider'));

        if (! ProviderCatalogue::has($provider)) {
            return new \WP_Error(
                'sfcore_ai_unknown_provider',
                __('Unknown AI provider.', 'swishfolio-core'),
                ['status' => 400]
            );
        }

        return $provider;
    }
}
