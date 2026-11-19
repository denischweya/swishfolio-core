<?php
/**
 * REST route: match an onboarding brief to a site template (kit).
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\REST;

use SwishfolioCore\AI\ModelRouter;
use SwishfolioCore\AI\Onboarding\SiteTemplates;
use SwishfolioCore\AI\Onboarding\TemplateMatcher;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\AI\UsageLogger;

/**
 * POST /ai/onboarding/match
 *
 * Returns the closest template for the owner's brief and three alternatives,
 * each with what the wizard needs to show it (name, description, thumbnail,
 * demo URL). Writes nothing; the wizard stores the owner's final choice.
 *
 * One cheap classification call on the follow-up model; without a working
 * provider it still answers, from the keyword ranking ("source": "keywords").
 * Applying the chosen design is KitDesign's job, not this route's.
 */
class KitController
{
    const NAMESPACE_V1 = 'swishfolio/v1';

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
    public function registerRoutes()
    {
        register_rest_route(self::NAMESPACE_V1, '/ai/onboarding/match', [
            'methods'             => 'POST',
            'callback'            => [$this, 'match'],
            'permission_callback' => [$this, 'canMatch'],
            'args'                => [
                'brief' => ['type' => 'object', 'required' => false],
            ],
        ]);
    }

    /**
     * Site owners only, like the rest of onboarding.
     *
     * @param \WP_REST_Request $request Request.
     *
     * @return true|\WP_Error
     */
    public function canMatch($request)
    {
        if (! is_user_logged_in()) {
            return new \WP_Error('sfcore_ai_forbidden', __('You must be signed in.', 'swishfolio-core'), ['status' => 401]);
        }

        if (! current_user_can('manage_options')) {
            return new \WP_Error('sfcore_ai_forbidden', __('You cannot set this site up.', 'swishfolio-core'), ['status' => 403]);
        }

        return true;
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function match($request)
    {
        $templates = SiteTemplates::all();

        if (! $templates) {
            return new \WP_Error('sfcore_no_templates', __('No designs are available.', 'swishfolio-core'), ['status' => 404]);
        }

        $brief    = self::brief((array) $request->get_param('brief'));
        $provider = null;

        if ($this->settings->get()['enabled']) {
            try {
                $provider = $this->providers->configured();
            } catch (ProviderException $e) {
                $provider = null;
            }
        }

        $matcher   = new TemplateMatcher($provider, $provider ? new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt') : null);
        $startedAt = microtime(true);
        $result    = $matcher->match($brief, $templates, (new ModelRouter($this->settings))->modelFor(ModelRouter::KIND_TEMPLATE_MATCH));
        $response  = $result['response'];
        unset($result['response']);

        if ($response !== null) {
            $this->logger->record($response, 0, (int) round((microtime(true) - $startedAt) * 1000), 'onboarding');
        }

        $result['usage']     = TemplateMatcher::usage($response);
        $result['templates'] = [];
        foreach (array_merge([$result['slug']], $result['alternatives']) as $slug) {
            $t = $templates[$slug];
            $result['templates'][] = [
                'slug'        => $t['slug'],
                'name'        => $t['name'],
                'title'       => $t['title'],
                'description' => $t['description'],
                'thumb'       => $t['thumb'],
                'demo'        => $t['demo'],
            ];
        }

        return rest_ensure_response($result);
    }

    /**
     * The same brief shape as /ai/onboarding/customise, plain text only.
     *
     * @param array $raw Brief from the client.
     *
     * @return array
     */
    private static function brief(array $raw)
    {
        $text = static function ($value, $max) {
            return is_scalar($value) ? mb_substr(sanitize_textarea_field((string) $value), 0, $max) : '';
        };

        $group = static function ($value, $max) use ($text) {
            $out = [];
            foreach ((array) $value as $key => $v) {
                if (is_scalar($v) && preg_match('/^[a-zA-Z]{1,20}$/', (string) $key)) {
                    $out[$key] = $text($v, $max);
                }
            }
            return $out;
        };

        $images = [];
        foreach (array_slice(isset($raw['images']) ? (array) $raw['images'] : [], 0, 12) as $image) {
            if (is_array($image)) {
                $images[] = [
                    'role' => isset($image['role']) ? sanitize_key((string) $image['role']) : 'other',
                    'note' => isset($image['note']) ? $text($image['note'], 120) : '',
                ];
            }
        }

        return [
            'business'  => $group(isset($raw['business']) ? $raw['business'] : [], 3000),
            'design'    => $group(isset($raw['design']) ? $raw['design'] : [], 60),
            'prompt'    => $text(isset($raw['prompt']) ? $raw['prompt'] : '', 3000),
            'documents' => $text(isset($raw['documents']) ? $raw['documents'] : '', TemplateMatcher::DOC_CHARS),
            'profile'   => isset($raw['profile']) && is_array($raw['profile']) ? map_deep($raw['profile'], 'sanitize_textarea_field') : [],
            'images'    => $images,
        ];
    }
}
