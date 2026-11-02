<?php
/**
 * The Swish AI admin menu.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Admin;

use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\Providers\ProviderCatalogue;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\AI\UsageLogger;

/**
 * Top-level "Swish AI" menu: connections, brand context and usage.
 *
 * AI is its own product surface, not a sub-feature of Swish Forms, so it gets a
 * top-level menu rather than a submenu under an unrelated parent.
 *
 * This plugin is BYOK: the site owner connects their own OpenAI and/or
 * Anthropic account and pays that provider directly. Nothing here talks to a
 * Swishfolio backend, and there are no credits to buy.
 */
class SwishAiMenu
{
    const MENU_SLUG        = 'swish-ai';
    const BRAND_SLUG       = 'swish-ai-brand';
    const USAGE_SLUG       = 'swish-ai-usage';
    const ACTION_CONNECT   = 'sfcore_ai_connect';
    const ACTION_TEST      = 'sfcore_ai_test';
    const ACTION_DISCONNECT = 'sfcore_ai_disconnect';
    const ACTION_ACTIVATE  = 'sfcore_ai_activate';
    const ACTION_TOGGLE    = 'sfcore_ai_toggle';
    const ACTION_BRAND     = 'sfcore_ai_brand';
    const NONCE            = 'sfcore_ai_admin';

    /** @var Settings */
    private $settings;

    /** @var ProviderRegistry */
    private $providers;

    /** @var UsageLogger */
    private $logger;

    public function __construct(Settings $settings, ProviderRegistry $providers, UsageLogger $logger)
    {
        $this->settings  = $settings;
        $this->providers = $providers;
        $this->logger    = $logger;
    }

    /**
     * @return void
     */
    public function registerMenu()
    {
        add_menu_page(
            __('Swish AI', 'swishfolio-core'),
            __('Swish AI', 'swishfolio-core'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderConnections'],
            'dashicons-superhero-alt',
            31
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('AI Connections', 'swishfolio-core'),
            __('Connections', 'swishfolio-core'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderConnections']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Brand & Context', 'swishfolio-core'),
            __('Brand & Context', 'swishfolio-core'),
            'manage_options',
            self::BRAND_SLUG,
            [$this, 'renderBrand']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('AI Usage', 'swishfolio-core'),
            __('Usage', 'swishfolio-core'),
            'manage_options',
            self::USAGE_SLUG,
            [$this, 'renderUsage']
        );
    }

    /**
     * @param string $hook Current admin page hook.
     *
     * @return void
     */
    public function enqueueAssets($hook)
    {
        if (strpos((string) $hook, self::MENU_SLUG) === false) {
            return;
        }

        wp_enqueue_style(
            'sfcore-swish-ai-admin',
            SFCORE_PLUGIN_URL . 'assets/admin/swish-ai.css',
            [],
            SFCORE_VERSION
        );

        wp_enqueue_script(
            'sfcore-swish-ai-admin',
            SFCORE_PLUGIN_URL . 'assets/admin/swish-ai.js',
            [],
            SFCORE_VERSION,
            true
        );

        // The model list is provider-specific, so the dropdown is repopulated
        // from this map rather than hardcoded in the template.
        wp_localize_script('sfcore-swish-ai-admin', 'sfcoreSwishAi', [
            'models'   => $this->modelMap(),
            'settings' => $this->settings->get(),
            'i18n'     => [
                'chooseModel'  => __('— Select a model —', 'swishfolio-core'),
                'customModel'  => __('This endpoint decides its own model names — type one.', 'swishfolio-core'),
                'confirmReset' => __('Disconnect this provider? The stored key will be deleted from this site.', 'swishfolio-core'),
            ],
        ]);
    }

    /**
     * Provider slug => [model id => label].
     *
     * @return array
     */
    public function modelMap()
    {
        $map = [];

        foreach ($this->providers->available() as $slug => $provider) {
            $map[$slug] = $provider->getModels();
        }

        return $map;
    }

    // ------------------------------------------------------------- Form actions

    /**
     * @return void
     */
    public function handleConnect()
    {
        $provider = $this->guard();
        $apiKey   = isset($_POST['api_key']) ? trim((string) wp_unslash($_POST['api_key'])) : '';
        $model    = isset($_POST['model']) ? (string) wp_unslash($_POST['model']) : '';
        $baseUrl  = isset($_POST['base_url']) ? (string) wp_unslash($_POST['base_url']) : '';

        // An empty key on an already-connected provider means "keep the key,
        // just change the model" — the field is masked, so it arrives empty.
        if ($apiKey === '' && ! $this->settings->isConnected($provider)) {
            $this->redirect(self::MENU_SLUG, 'key-required');

            return;
        }

        $this->settings->connect($provider, $apiKey, $model, $baseUrl);

        $this->redirect(self::MENU_SLUG, 'connected');
    }

    /**
     * @return void
     */
    public function handleTest()
    {
        $provider = $this->guard();

        if (! $this->settings->isConnected($provider)) {
            $this->redirect(self::MENU_SLUG, 'not-connected');

            return;
        }

        $previousActive = $this->settings->getActive();
        $this->settings->setActive($provider);

        $instance = null;

        try {
            $instance = $this->providers->configured();
        } catch (\Exception $e) {
            $instance = null;
        }

        $ok = $instance !== null && $instance->testConnection();

        $this->settings->markValidated($provider, $ok, time());

        if ($previousActive !== '' && $previousActive !== $provider) {
            $this->settings->setActive($previousActive);
        }

        $this->redirect(self::MENU_SLUG, $ok ? 'test-ok' : 'test-failed');
    }

    /**
     * @return void
     */
    public function handleDisconnect()
    {
        $provider = $this->guard();

        $this->settings->disconnect($provider);

        $this->redirect(self::MENU_SLUG, 'disconnected');
    }

    /**
     * @return void
     */
    public function handleActivate()
    {
        $provider = $this->guard();

        if (! $this->settings->isConnected($provider)) {
            $this->redirect(self::MENU_SLUG, 'not-connected');

            return;
        }

        $this->settings->setActive($provider);

        $this->redirect(self::MENU_SLUG, 'activated');
    }

    /**
     * @return void
     */
    public function handleToggle()
    {
        $this->assertPermission();

        $enable = ! empty($_POST['enabled']);

        if ($enable && empty($_POST['acknowledged'])) {
            $this->redirect(self::MENU_SLUG, 'consent-required');

            return;
        }

        $this->settings->setEnabled($enable);

        $notice = $enable && ! $this->settings->get()['enabled'] ? 'no-connection' : 'saved';

        $this->redirect(self::MENU_SLUG, $notice);
    }

    /**
     * @return void
     */
    public function handleBrand()
    {
        $this->assertPermission();

        $this->settings->saveBrand([
            'siteName'        => isset($_POST['siteName']) ? (string) wp_unslash($_POST['siteName']) : '',
            'siteDescription' => isset($_POST['siteDescription']) ? (string) wp_unslash($_POST['siteDescription']) : '',
            'designStyle'     => isset($_POST['designStyle']) ? (string) wp_unslash($_POST['designStyle']) : '',
            'blockShapes'     => isset($_POST['blockShapes']) ? (string) wp_unslash($_POST['blockShapes']) : '',
            'copywritingTone' => isset($_POST['copywritingTone']) ? (string) wp_unslash($_POST['copywritingTone']) : '',
        ]);

        $this->redirect(self::BRAND_SLUG, 'saved');
    }

    // ----------------------------------------------------------------- Renderers

    /**
     * @return void
     */
    public function renderConnections()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings  = $this->settings->get();
        $providers = $this->providers->available();
        $models    = $this->modelMap();
        $notice    = $this->notice();

        include SFCORE_PLUGIN_DIR . 'templates/admin/swish-ai-connections.php';
    }

    /**
     * @return void
     */
    public function renderBrand()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $brand  = $this->settings->getBrand();
        $notice = $this->notice();

        include SFCORE_PLUGIN_DIR . 'templates/admin/swish-ai-brand.php';
    }

    /**
     * @return void
     */
    public function renderUsage()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $usage  = $this->logger->getUsage();
        $notice = $this->notice();

        include SFCORE_PLUGIN_DIR . 'templates/admin/swish-ai-usage.php';
    }

    /**
     * Declare the AI data flow to WordPress's privacy policy generator.
     *
     * @return void
     */
    public function registerPrivacyContent()
    {
        if (! function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content = '<p>' . __(
            'When you use the AI editing or generation features, this site sends the content and settings of the blocks you selected, your site\'s design token names, and your brand context to the AI provider you connected. Your instruction text is sent with it. Your API key is stored on this site, encrypted, and is never sent to the plugin author.',
            'swishfolio-core'
        ) . '</p><p>' . __(
            'No AI request is made unless you explicitly ask for one. This site records request counts, token usage and timings, but never the text of your prompts or your content. Consult your AI provider\'s own privacy policy for how they handle what is sent to them.',
            'swishfolio-core'
        ) . '</p>';

        wp_add_privacy_policy_content(__('Swishfolio Core', 'swishfolio-core'), wp_kses_post($content));
    }

    // ------------------------------------------------------------------ Internals

    /**
     * Verify capability and nonce, then return the sanitised provider slug.
     *
     * @return string
     */
    private function guard()
    {
        $this->assertPermission();

        $provider = isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : '';

        if (! ProviderCatalogue::has($provider)) {
            wp_die(esc_html__('Unknown AI provider.', 'swishfolio-core'), '', ['response' => 400]);
        }

        return $provider;
    }

    /**
     * @return void
     */
    private function assertPermission()
    {
        if (! current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have permission to change these settings.', 'swishfolio-core'),
                '',
                ['response' => 403]
            );
        }

        check_admin_referer(self::NONCE);
    }

    /**
     * @param string $page   Page slug.
     * @param string $notice Notice slug.
     *
     * @return void
     */
    private function redirect($page, $notice)
    {
        wp_safe_redirect(
            add_query_arg(
                ['page' => $page, 'sfcore_notice' => $notice],
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * @return string
     */
    private function notice()
    {
        return isset($_GET['sfcore_notice']) ? sanitize_key(wp_unslash($_GET['sfcore_notice'])) : '';
    }

    /**
     * Human-readable notice text. Raw provider errors never reach the user.
     *
     * @param string $notice Notice slug.
     *
     * @return array{type:string,message:string}|null
     */
    public static function noticeText($notice)
    {
        $map = [
            'connected'        => ['success', __('Provider connected.', 'swishfolio-core')],
            'disconnected'     => ['success', __('Provider disconnected and its key deleted from this site.', 'swishfolio-core')],
            'activated'        => ['success', __('Active provider changed.', 'swishfolio-core')],
            'saved'            => ['success', __('Settings saved.', 'swishfolio-core')],
            'test-ok'          => ['success', __('Connection successful — that key works.', 'swishfolio-core')],
            'test-failed'      => ['error', __('That key was rejected. Check it is correct, active, and has credit.', 'swishfolio-core')],
            'key-required'     => ['error', __('Enter an API key to connect this provider.', 'swishfolio-core')],
            'not-connected'    => ['error', __('Connect that provider first.', 'swishfolio-core')],
            'consent-required' => ['error', __('AI was not enabled: you must acknowledge what is sent to your provider.', 'swishfolio-core')],
            'no-connection'    => ['error', __('AI cannot be enabled until a provider is connected.', 'swishfolio-core')],
        ];

        return isset($map[$notice])
            ? ['type' => $map[$notice][0], 'message' => $map[$notice][1]]
            : null;
    }
}
