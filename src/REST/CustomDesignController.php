<?php
/**
 * REST routes for a custom design in the onboarding wizard.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\REST;

use SwishfolioCore\AI\Design\CustomDesignApplier;
use SwishfolioCore\AI\Design\CustomDesigner;
use SwishfolioCore\AI\Design\DesignTokens;
use SwishfolioCore\AI\Design\StylePresets;
use SwishfolioCore\AI\Generation\PatternLibrary;
use SwishfolioCore\AI\ModelRouter;
use SwishfolioCore\AI\Onboarding\SiteTemplates;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\AI\UsageLogger;
use SwishfolioCore\Jobs\BackgroundJobs;
use SwishfolioCore\Jobs\JobProgress;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\Journal\WpdbJournalRepository;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolException;

/**
 * POST /ai/onboarding/custom-design        (metered: custom_design)
 *   The owner's design prompt and business in; the strong model writes a
 *   design system (CustomDesigner, ModelRouter::KIND_CUSTOM_DESIGN). The
 *   validated tokens are kept server side as this site's draft and only a
 *   preview (name, summary, colours, fonts) and the draft id go back. Writes
 *   nothing visitors see. Runs as a background job with "async".
 *   With a "preset" (a theme style variation, StylePresets) the model starts
 *   from that preset's tokens and changes what the prompt asks; with a preset
 *   and no prompt there is no AI call at all: the draft is the preset's
 *   tokens (validated, contrast enforced), answered at once and not metered.
 *
 * POST /ai/onboarding/custom-design/apply  (no AI, not metered)
 *   Applies that draft to the site under a build's run ID (a run this user
 *   generated, GenerationController::runMeta()), so the pages, the kit design
 *   and the custom design are one undo. Re-running it re-applies the same
 *   draft from the kit's shipped design.
 *
 * Neither route accepts CSS, colours or fonts from the browser: the draft is
 * read back from the option and validated again before it is compiled.
 */
class CustomDesignController
{
    const NAMESPACE_V1 = 'swishfolio/v1';
    const DRAFT        = 'swish_custom_design_draft';
    const KIND         = ModelRouter::KIND_CUSTOM_DESIGN;

    /** @var Settings */
    private $settings;

    /** @var ProviderRegistry */
    private $providers;

    /** @var UsageLogger */
    private $logger;

    /** @var PatternLibrary */
    private $library;

    /** @var BackgroundJobs|null */
    private $jobs;

    public function __construct(Settings $settings, ProviderRegistry $providers, UsageLogger $logger, PatternLibrary $library, BackgroundJobs $jobs = null)
    {
        $this->settings  = $settings;
        $this->providers = $providers;
        $this->logger    = $logger;
        $this->library   = $library;
        $this->jobs      = $jobs;

        if ($jobs !== null) {
            $jobs->register('custom-design', [$this, 'runJob']);
        }
    }

    /**
     * @return void
     */
    public function registerRoutes()
    {
        register_rest_route(self::NAMESPACE_V1, '/ai/onboarding/custom-design', [
            'methods'             => 'POST',
            'callback'            => [$this, 'generate'],
            'permission_callback' => [$this, 'canDesign'],
            'args'                => [
                'prompt'   => ['type' => 'string', 'required' => false],
                'preset'   => ['type' => 'string', 'required' => false],
                'business' => ['type' => 'string', 'required' => false],
                'kit'      => ['type' => 'string', 'required' => true],
                'async'    => ['type' => 'boolean', 'required' => false],
            ],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/ai/onboarding/custom-design/apply', [
            'methods'             => 'POST',
            'callback'            => [$this, 'apply'],
            'permission_callback' => [$this, 'canApply'],
            'args'                => [
                'designId' => ['type' => 'string', 'required' => true],
                'runId'    => ['type' => 'string', 'required' => true],
            ],
        ]);
    }

    /**
     * Same gate as finishing a kit build.
     *
     * @param \WP_REST_Request $request Request.
     *
     * @return true|\WP_Error
     */
    public function canDesign($request)
    {
        $can = $this->canApply($request);
        if ($can !== true) {
            return $can;
        }

        if (! $this->settings->get()['enabled']) {
            return new \WP_Error('sfcore_ai_disabled', __('AI features are turned off for this site.', 'swishfolio-core'), ['status' => 403]);
        }

        return true;
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return true|\WP_Error
     */
    public function canApply($request)
    {
        if (! is_user_logged_in()) {
            return new \WP_Error('sfcore_ai_forbidden', __('You must be signed in.', 'swishfolio-core'), ['status' => 401]);
        }

        if (! current_user_can('manage_options') || ! current_user_can(BuilderController::CAPABILITY) || ! current_user_can('edit_theme_options')) {
            return new \WP_Error('sfcore_ai_forbidden', __('You cannot set this site up.', 'swishfolio-core'), ['status' => 403]);
        }

        return true;
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function generate($request)
    {
        $prompt = mb_substr(sanitize_textarea_field((string) $request->get_param('prompt')), 0, 1500);
        $kit    = sanitize_key((string) $request->get_param('kit'));
        $preset = sanitize_key((string) $request->get_param('preset'));

        if ($preset !== '' && ! StylePresets::has($preset)) {
            return new \WP_Error('sfcore_ai_bad_request', __('That style is not available.', 'swishfolio-core'), ['status' => 400]);
        }
        if ($preset === '' && mb_strlen(trim($prompt)) < 8) {
            return new \WP_Error('sfcore_ai_bad_request', __('Describe the look you want in a few words first.', 'swishfolio-core'), ['status' => 400]);
        }
        // Only a registered site template's kit gives the structure.
        if ($this->library->kit($kit) === null || SiteTemplates::get($kit) === null) {
            return new \WP_Error('sfcore_ai_no_kit', __('That design is not available.', 'swishfolio-core'), ['status' => 409]);
        }

        // Same owner, same structure, same words: the design already written
        // (for a build that then failed, or before a reload) is used again,
        // with no second provider call and so no second charge.
        $draft = self::reusable($request);
        if ($draft !== null) {
            return rest_ensure_response(self::preview($draft, true));
        }

        // A preset as it is: no provider call, nothing to queue.
        if (self::presetOnly($request)) {
            return rest_ensure_response(self::presetDesign($preset, $kit));
        }

        try {
            $this->providers->configured();
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_not_configured', $e->getMessage(), ['status' => 409]);
        }

        $payload = [
            'prompt'   => $prompt,
            'preset'   => $preset,
            'business' => mb_substr(sanitize_textarea_field((string) $request->get_param('business')), 0, 2000),
            'kit'      => $kit,
            'user'     => get_current_user_id(),
        ];

        if ($request->get_param('async') && $this->jobs !== null) {
            $job = $this->jobs->queue('custom-design', __('Designing your custom look', 'swishfolio-core'), $payload, get_current_user_id());

            return rest_ensure_response(array_merge($job->toStatusArray(), ['queued' => true]));
        }

        $result = $this->runJob($payload, JobProgress::silent());

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    /**
     * @param array       $payload  prompt, preset, business, kit, user.
     * @param JobProgress $progress Progress.
     *
     * @return array|\WP_Error
     */
    public function runJob(array $payload, JobProgress $progress)
    {
        try {
            $provider = $this->providers->configured();
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_not_configured', $e->getMessage(), ['status' => 409]);
        }

        $router    = new ModelRouter($this->settings);
        $designer  = new CustomDesigner($provider, new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'));
        $startedAt = microtime(true);

        $progress->step('design', __('Designing your custom look', 'swishfolio-core'));

        $timeout = static function () {
            return 180;
        };
        add_filter('swishfolio_ai_http_timeout', $timeout);

        try {
            $preset = isset($payload['preset']) ? (string) $payload['preset'] : '';
            $result = $designer->design((string) $payload['prompt'], (string) $payload['business'], array_values($this->library->kits()), $router->modelFor(self::KIND), StylePresets::seed($preset), $this->library->kit((string) $payload['kit']));
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_provider_error', __('AI could not design that look. Nothing was changed.', 'swishfolio-core'), ['status' => 502, 'detail' => $e->getMessage()]);
        } finally {
            remove_filter('swishfolio_ai_http_timeout', $timeout);
        }

        $this->logger->record($result['response'], 0, (int) round((microtime(true) - $startedAt) * 1000), 'onboarding');
        // Not marked here: the site plan runs alongside and may still be retried
        // on the strong model. The mark comes with the apply, after a finished build.

        $tokens = $result['tokens'];
        $id     = wp_generate_password(20, false, false);

        update_option(self::DRAFT, [
            'id'        => $id,
            'user'      => (int) $payload['user'],
            'kit'       => (string) $payload['kit'],
            'prompt'    => (string) $payload['prompt'],
            'preset'    => isset($payload['preset']) ? (string) $payload['preset'] : '',
            'tokens'    => $tokens,
            'createdAt' => time(),
        ], false);

        $progress->done('design', $tokens['name']);

        return array_merge(self::preview(['id' => $id, 'tokens' => $tokens], false), ['usage' => CustomDesigner::usage($result['response'])]);
    }

    /**
     * A preset with no prompt: answered from the preset alone, no AI call.
     * Read by the hosting layer too, so it is not metered.
     *
     * @param \WP_REST_Request $request Request.
     *
     * @return bool
     */
    public static function presetOnly($request)
    {
        return StylePresets::has(sanitize_key((string) $request->get_param('preset')))
            && trim(sanitize_textarea_field((string) $request->get_param('prompt'))) === '';
    }

    /**
     * The draft for a preset as it is: its tokens through DesignTokens::validate
     * (contrast enforced), stored like a model's design so apply() and the
     * reuse check treat both the same.
     *
     * @param string $preset Preset slug (StylePresets::has()).
     * @param string $kit    Structural kit slug.
     *
     * @return array The preview, with zero usage.
     */
    public static function presetDesign($preset, $kit)
    {
        $tokens = StylePresets::tokens($preset);
        $id     = bin2hex(random_bytes(10));

        update_option(self::DRAFT, [
            'id'        => $id,
            'user'      => get_current_user_id(),
            'kit'       => (string) $kit,
            'prompt'    => '',
            'preset'    => (string) $preset,
            'tokens'    => $tokens,
            'createdAt' => time(),
        ], false);

        return array_merge(self::preview(['id' => $id, 'tokens' => $tokens], false), ['usage' => CustomDesigner::usage(null)]);
    }

    /**
     * This site's draft when it answers this request exactly: the same user,
     * the same structural kit, the same preset and the same design prompt (as
     * generate() cleans it). Read by the hosting layer too, so a reuse is not metered.
     *
     * @param \WP_REST_Request $request Request.
     *
     * @return array|null Draft.
     */
    public static function reusable($request)
    {
        $draft  = get_option(self::DRAFT);
        $prompt = mb_substr(sanitize_textarea_field((string) $request->get_param('prompt')), 0, 1500);
        $kit    = sanitize_key((string) $request->get_param('kit'));
        $preset = sanitize_key((string) $request->get_param('preset'));

        if (! is_array($draft) || empty($draft['id']) || empty($draft['tokens']) || (int) $draft['user'] !== get_current_user_id() || get_current_user_id() <= 0) {
            return null;
        }

        $same = (string) $draft['kit'] === $kit && (string) $draft['prompt'] === $prompt && (isset($draft['preset']) ? (string) $draft['preset'] : '') === $preset;

        return $same && (trim($prompt) !== '' || $preset !== '') ? $draft : null;
    }

    /**
     * @param array $draft  {id, tokens}.
     * @param bool  $reused Whether it came from an earlier call.
     *
     * @return array What the browser sees (no CSS, no raw tokens beyond the preview).
     */
    private static function preview(array $draft, $reused)
    {
        $tokens = DesignTokens::validate((array) $draft['tokens']);

        return [
            'designId' => (string) $draft['id'],
            'name'     => $tokens['name'],
            'summary'  => $tokens['summary'],
            'palette'  => $tokens['palette'],
            'fonts'    => $tokens['fonts'],
            'reused'   => (bool) $reused,
        ];
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function apply($request)
    {
        $runId = (string) $request->get_param('runId');
        $meta  = GenerationController::runMeta($runId);
        $draft = get_option(self::DRAFT);

        if ($meta === null) {
            return new \WP_Error('sfcore_ai_bad_request', __('That build could not be found.', 'swishfolio-core'), ['status' => 404]);
        }
        if (! is_array($draft) || empty($draft['id']) || ! hash_equals((string) $draft['id'], (string) $request->get_param('designId')) || (int) $draft['user'] !== get_current_user_id()) {
            return new \WP_Error('sfcore_ai_bad_request', __('That custom design could not be found. Create it again.', 'swishfolio-core'), ['status' => 404]);
        }

        // The build's kit gives the structure the pages were composed from.
        $kit = $this->library->kit((string) $meta['kit']);
        if ($kit === null) {
            return new \WP_Error('sfcore_ai_no_kit', __('That design is not available.', 'swishfolio-core'), ['status' => 409]);
        }

        $pages = [];
        foreach ((array) $meta['pages'] as $page) {
            if (! empty($page['created'])) {
                $pages[] = (int) $page['postId'];
            }
        }

        $journal = new MutationJournal(new WpdbJournalRepository());
        $context = ToolContext::forUser(get_current_user_id(), $runId);
        $journal->resume($context);

        try {
            $result = (new CustomDesignApplier($journal))->apply(DesignTokens::validate($draft['tokens']), $kit, $pages, (string) $draft['prompt'], $context);
        } catch (ToolException $e) {
            return new \WP_Error('sfcore_custom_design_failed', $e->getMessage(), ['status' => 409, 'runId' => $runId]);
        }

        // A custom design is design-heavy (ModelRouter): only now, applied to a
        // finished build, does it count as the site's successful first one.
        (new ModelRouter($this->settings))->markSucceeded(self::KIND);

        $result['runId'] = $runId;

        return rest_ensure_response($result);
    }
}
