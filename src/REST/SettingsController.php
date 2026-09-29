<?php
/**
 * REST routes for AI provider settings.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\REST;

use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\Settings;

class SettingsController
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
        register_rest_route(self::NAMESPACE_V1, '/ai/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'read'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'write'],
                'permission_callback' => [$this, 'canManage'],
            ],
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
     * Returns provider, model and whether a key is stored — never the key.
     *
     * @return \WP_REST_Response
     */
    public function read()
    {
        $providers = [];

        foreach ($this->providers->available() as $slug => $provider) {
            $providers[$slug] = [
                'label'          => $provider->getLabel(),
                'requiredFields' => $provider->getRequiredFields(),
                'models'         => $provider->getModels(),
            ];
        }

        return rest_ensure_response([
            'settings'  => $this->settings->get(),
            'providers' => $providers,
        ]);
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response
     */
    public function write($request)
    {
        $this->settings->save([
            'provider' => sanitize_text_field((string) $request->get_param('provider')),
            'model'    => sanitize_text_field((string) $request->get_param('model')),
            'base_url' => esc_url_raw((string) $request->get_param('base_url')),
            'enabled'  => (bool) $request->get_param('enabled'),
            'api_key'  => (string) $request->get_param('api_key'),
        ]);

        return rest_ensure_response(['settings' => $this->settings->get()]);
    }
}
