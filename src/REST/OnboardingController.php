<?php
/**
 * REST route for importing a CV or professional profile.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\REST;

use SwishfolioCore\AI\Onboarding\ProfileExtractor;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\AI\UsageLogger;

/**
 * One route, and it writes nothing.
 *
 * The extraction comes back as a proposal: the owner reads it, corrects the bits
 * the CV got wrong or left out, and only then does a separate request save the
 * brand context and generate pages. An import that saved straight into settings
 * would be fast and wrong — a CV is full of things nobody wants on their
 * website.
 *
 * Its own controller rather than another method on SettingsController: that one
 * owns stored state, this one owns a one-time proposal, and the pattern here is
 * already one controller per surface.
 */
class OnboardingController
{
    const NAMESPACE_V1 = 'swishfolio/v1';

    /** @var Settings */
    private $settings;

    /** @var ProviderRegistry */
    private $providers;

    /** @var UsageLogger */
    private $logger;

    /**
     * @param Settings         $settings  AI settings.
     * @param ProviderRegistry $providers Provider registry.
     * @param UsageLogger      $logger    Usage counters.
     */
    public function __construct(Settings $settings, ProviderRegistry $providers, UsageLogger $logger)
    {
        $this->settings  = $settings;
        $this->providers = $providers;
        $this->logger    = $logger;
    }

    /**
     * @return void
     */
    public function registerRoutes()
    {
        register_rest_route(
            self::NAMESPACE_V1,
            '/ai/onboarding/extract',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'extract'],
                'permission_callback' => [$this, 'canOnboard'],
                'args'                => [
                    'text' => ['type' => 'string', 'required' => true],
                ],
            ]
        );
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return true|\WP_Error
     */
    public function canOnboard($request)
    {
        if (! is_user_logged_in()) {
            return new \WP_Error('sfcore_ai_forbidden', __('You must be signed in.', 'swishfolio-core'), ['status' => 401]);
        }

        // Onboarding sets the site's brand and generates its pages, which is an
        // owner's job rather than an author's.
        if (! current_user_can('manage_options')) {
            return new \WP_Error('sfcore_ai_forbidden', __('You cannot set this site up.', 'swishfolio-core'), ['status' => 403]);
        }

        if (! $this->settings->get()['enabled']) {
            return new \WP_Error(
                'sfcore_ai_disabled',
                __('AI features are turned off for this site.', 'swishfolio-core'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function extract($request)
    {
        try {
            $provider = $this->providers->configured();
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_not_configured', $e->getMessage(), ['status' => 409]);
        }

        $extractor = new ProfileExtractor(
            $provider,
            new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt')
        );

        $startedAt = microtime(true);

        try {
            $result = $extractor->extract((string) $request->get_param('text'), $this->settings->get()['model']);
        } catch (\InvalidArgumentException $e) {
            return new \WP_Error('sfcore_ai_bad_request', $e->getMessage(), ['status' => 400]);
        } catch (ProviderException $e) {
            return new \WP_Error(
                'sfcore_ai_provider_error',
                __('The AI could not read that profile. Nothing was saved.', 'swishfolio-core'),
                ['status' => 502, 'detail' => $e->getMessage()]
            );
        }

        $this->logger->record(
            new \SwishfolioCore\AI\AIResponse(
                [],
                $result['usage']['inputTokens'],
                $result['usage']['outputTokens'],
                $this->settings->get()['model'],
                $provider->getSlug()
            ),
            0,
            (int) round((microtime(true) - $startedAt) * 1000),
            'onboarding'
        );

        // "saved" is false and says so: the next step is the owner's.
        return rest_ensure_response(
            [
                'profile' => $result['profile'],
                'brand'   => $result['brand'],
                'saved'   => false,
            ]
        );
    }
}
