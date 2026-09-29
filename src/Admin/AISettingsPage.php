<?php
/**
 * AI settings admin screen.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Admin;

use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\AI\UsageLogger;

/**
 * The provider configuration and consent surface.
 *
 * This screen is not optional polish. AI editing sends post content and theme
 * tokens to a third-party service, and WordPress.org requires that a user be
 * told exactly what leaves their site before it does. Without a screen stating
 * that, the plugin has documentation describing a data flow nobody ever agreed
 * to — so AI stays disabled until someone enables it here.
 */
class AISettingsPage
{
    const PARENT_SLUG = 'swish-forms';
    const PAGE_SLUG   = 'swish-forms-ai';
    const ACTION      = 'sfcore_save_ai_settings';
    const NONCE       = 'sfcore_ai_settings';

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
        add_submenu_page(
            self::PARENT_SLUG,
            __('AI Settings', 'swishfolio-core'),
            __('AI Settings', 'swishfolio-core'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
        );
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
            'When you use the AI editing or AI generation features, this site sends the content and settings of the blocks you selected, plus your site\'s design tokens (colours, spacing and font size names from theme.json), to the AI provider you configured. Your instruction text is sent with it. Your API key is stored on this site, encrypted, and is never sent to the plugin author.',
            'swishfolio-core'
        ) . '</p><p>' . __(
            'No AI request is made unless you explicitly ask for one. This site records the number of requests, the tokens they consumed and how long they took, but never the text of your prompts or your content. Consult your AI provider\'s own privacy policy for how they handle what is sent to them.',
            'swishfolio-core'
        ) . '</p>';

        wp_add_privacy_policy_content(__('Swishfolio Core', 'swishfolio-core'), wp_kses_post($content));
    }

    /**
     * Handle the settings form submission.
     *
     * @return void
     */
    public function handleSave()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to change these settings.', 'swishfolio-core'), '', ['response' => 403]);
        }

        check_admin_referer(self::NONCE);

        $enabled = ! empty($_POST['enabled']);

        // Enabling AI is the consent gesture: it cannot be turned on without
        // ticking the acknowledgement beside it.
        if ($enabled && empty($_POST['acknowledged'])) {
            $this->redirect('consent-required');

            return;
        }

        $this->settings->save([
            'provider' => isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : 'openai',
            'model'    => isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : '',
            'base_url' => isset($_POST['base_url']) ? esc_url_raw(wp_unslash($_POST['base_url'])) : '',
            'enabled'  => $enabled,
            'api_key'  => isset($_POST['api_key']) ? trim((string) wp_unslash($_POST['api_key'])) : '',
        ]);

        $this->redirect('saved');
    }

    /**
     * @param string $notice Notice slug.
     *
     * @return void
     */
    private function redirect($notice)
    {
        wp_safe_redirect(
            add_query_arg(
                ['page' => self::PAGE_SLUG, 'sfcore_notice' => $notice],
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * @return void
     */
    public function render()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings  = $this->settings->get();
        $usage     = $this->logger->getUsage();
        $providers = $this->providers->available();
        $notice    = isset($_GET['sfcore_notice']) ? sanitize_key(wp_unslash($_GET['sfcore_notice'])) : '';

        include SFCORE_PLUGIN_DIR . 'templates/admin/ai-settings.php';
    }
}
