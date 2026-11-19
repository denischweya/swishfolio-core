<?php
/**
 * REST routes for AI settings and brand context.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\REST;

use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\Providers\ProviderCatalogue;
use SwishfolioCore\AI\Settings;

/**
 * Reads and writes everything EXCEPT credentials. Connecting, testing and
 * disconnecting a provider live in ConnectionsController, because handling a
 * secret is a different responsibility from saving a preference.
 */
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

        register_rest_route(self::NAMESPACE_V1, '/ai/models', [
            'methods'             => 'GET',
            'callback'            => [$this, 'models'],
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
     * Provider state, models and brand context — never a key.
     *
     * @return mixed
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
            'settings'  => $this->settings->publicView(),
            'providers' => $providers,
        ]);
    }

    /**
     * Model lists keyed by provider, for populating a model control.
     *
     * @return mixed
     */
    public function models()
    {
        $models = [];

        foreach ($this->providers->available() as $slug => $provider) {
            $models[$slug] = $provider->getModels();
        }

        return rest_ensure_response(['models' => $models]);
    }

    /**
     * Save non-credential settings.
     *
     * Accepts no api_key: a secret must go through ConnectionsController, which
     * validates and encrypts it. Ignoring it here means a client cannot smuggle
     * a key into the settings option by accident.
     *
     * @param object $request REST request.
     *
     * @return mixed
     */
    public function write($request)
    {
        $active = $request->get_param('active');

        if ($active !== null && (string) $active !== '') {
            $slug = sanitize_key((string) $active);

            if (! ProviderCatalogue::has($slug)) {
                return new \WP_Error(
                    'sfcore_ai_unknown_provider',
                    __('Unknown AI provider.', 'swishfolio-core'),
                    ['status' => 400]
                );
            }

            if (! $this->settings->isConnected($slug)) {
                return new \WP_Error(
                    'sfcore_ai_not_connected',
                    __('Connect that provider before selecting it.', 'swishfolio-core'),
                    ['status' => 409]
                );
            }

            $this->settings->setActive($slug);
        }

        $model = $request->get_param('model');

        if ($model !== null && (string) $model !== '' && $this->settings->getActive() !== '') {
            $this->settings->setModel($this->settings->getActive(), (string) $model);
        }

        $brand = $request->get_param('brand');

        if (is_array($brand)) {
            $this->settings->saveBrand($brand);
        }

        $enabled = $request->get_param('enabled');

        if ($enabled !== null) {
            $this->settings->setEnabled((bool) $enabled);
        }

        return rest_ensure_response(['settings' => $this->settings->publicView()]);
    }
}
