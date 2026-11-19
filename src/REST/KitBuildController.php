<?php
/**
 * REST route for finishing a kit build in the onboarding wizard.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\REST;

use SwishfolioCore\AI\Generation\Kit;
use SwishfolioCore\AI\Generation\PatternLibrary;
use SwishfolioCore\AI\ModelRouter;
use SwishfolioCore\AI\Onboarding\DemoScan;
use SwishfolioCore\AI\Onboarding\KitBranding;
use SwishfolioCore\AI\Onboarding\KitForms;
use SwishfolioCore\AI\Onboarding\KitDesign;
use SwishfolioCore\AI\Onboarding\KitImages;
use SwishfolioCore\AI\Onboarding\KitLinks;
use SwishfolioCore\AI\Onboarding\SiteTemplates;
use SwishfolioCore\AI\Onboarding\TemplateCustomiser;
use SwishfolioCore\AI\Onboarding\WordmarkFit;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\AI\UsageLogger;
use SwishfolioCore\Jobs\BackgroundJobs;
use SwishfolioCore\Jobs\JobProgress;
use SwishfolioCore\Tools\ConfirmToken;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\Journal\WpdbJournalRepository;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;
use SwishfolioCore\Tools\Write\RewriteCopyTool;
use SwishfolioCore\Tools\Write\SetDesignTokensTool;
use SwishfolioCore\Tools\Write\TrustedMarkup;

/**
 * POST /ai/onboarding/kit: what happens after /ai/generate wrote the pages of
 * a kit build, all under the generation's run ID so the whole build is one
 * undo in the audit log. Every stage is scoped to a run the current user
 * generated (GenerationController::runMeta(), kept server side), so the kit
 * and the pages are never taken from the browser.
 *
 * design  No AI. Applies the kit's design (KitDesign: styles, fonts, header,
 *         footer, menus) unless the site already has it, points the header,
 *         footer and menus at the generated pages (KitLinks), and issues a
 *         ConfirmToken over the AI work that follows.
 * part    One header or footer part, rewritten for the owner (TemplateCustomiser).
 * tokens  The owner's design wishes as colour and font changes.
 * images  The owner's photos in place of the kit's placeholder photos (KitImages).
 * finish  No AI. Logo, links again (the part rewrite may touch them), and the
 *         leftover scan for the demo business's details, fixing what has an
 *         owner equivalent and reporting the rest.
 */
class KitBuildController
{
    const NAMESPACE_V1 = 'swishfolio/v1';
    const TOOL         = 'swishfolio/kit-build';
    const KIND         = ModelRouter::KIND_TEMPLATE_CUSTOMISE;

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

        TrustedMarkup::register();

        if ($jobs !== null) {
            $jobs->register('onboarding-kit', [$this, 'runJob']);
        }
    }

    /**
     * @return void
     */
    public function registerRoutes()
    {
        register_rest_route(self::NAMESPACE_V1, '/ai/onboarding/kit', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => [$this, 'canBuild'],
            'args'                => [
                'stage'        => ['type' => 'string', 'required' => true, 'enum' => ['design', 'part', 'tokens', 'images', 'finish']],
                'runId'        => ['type' => 'string', 'required' => true],
                'brief'        => ['type' => 'object', 'required' => false],
                'scope'        => ['type' => 'object', 'required' => false],
                'confirmToken' => ['type' => 'string', 'required' => false],
                'target'       => ['type' => 'string', 'required' => false],
                'async'        => ['type' => 'boolean', 'required' => false],
            ],
        ]);
    }

    /**
     * Same gate as the template customisation.
     *
     * @param \WP_REST_Request $request Request.
     *
     * @return true|\WP_Error
     */
    public function canBuild($request)
    {
        if (! is_user_logged_in()) {
            return new \WP_Error('sfcore_ai_forbidden', __('You must be signed in.', 'swishfolio-core'), ['status' => 401]);
        }

        if (! current_user_can('manage_options') || ! current_user_can(BuilderController::CAPABILITY) || ! current_user_can('edit_theme_options')) {
            return new \WP_Error('sfcore_ai_forbidden', __('You cannot set this site up.', 'swishfolio-core'), ['status' => 403]);
        }

        if (! $this->settings->get()['enabled']) {
            return new \WP_Error('sfcore_ai_disabled', __('AI features are turned off for this site.', 'swishfolio-core'), ['status' => 403]);
        }

        return true;
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle($request)
    {
        $stage = (string) $request->get_param('stage');
        $runId = (string) $request->get_param('runId');
        $meta  = GenerationController::runMeta($runId);

        if ($meta === null) {
            return new \WP_Error('sfcore_ai_bad_request', __('That build could not be found.', 'swishfolio-core'), ['status' => 404]);
        }

        $brief = CustomiseController::cleanBrief((array) $request->get_param('brief'));

        if ($stage === 'design') {
            return $this->rest($this->design($runId, $meta, $brief));
        }

        if ($stage === 'finish') {
            return $this->rest($this->finish($runId, $meta, $brief));
        }

        $scope = self::scope((array) $request->get_param('scope'));

        if ($scope['runId'] !== $runId || ! ConfirmToken::verify((string) $request->get_param('confirmToken'), self::calls($scope), get_current_user_id())) {
            return new \WP_Error('sfcore_confirm_invalid', __('That approval has expired. Start the build again.', 'swishfolio-core'), ['status' => 409]);
        }

        $target = (string) $request->get_param('target');

        if (($stage === 'part' && ! in_array((int) $target, $scope['posts'], true)) || ($stage === 'tokens' && ! $scope['design']) || ($stage === 'images' && ! $scope['images'])) {
            return new \WP_Error('sfcore_ai_bad_request', __('That is not part of this build.', 'swishfolio-core'), ['status' => 400]);
        }

        try {
            $this->providers->configured();
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_not_configured', $e->getMessage(), ['status' => 409]);
        }

        $payload = ['stage' => $stage, 'runId' => $runId, 'scope' => $scope, 'target' => $target, 'brief' => $brief];

        if ($request->get_param('async') && $this->jobs !== null) {
            $job = $this->jobs->queue('onboarding-kit', __('Finishing your site', 'swishfolio-core'), $payload, get_current_user_id());

            return rest_ensure_response(array_merge($job->toStatusArray(), ['queued' => true]));
        }

        return $this->rest($this->runJob($payload, JobProgress::silent()));
    }

    /**
     * @param array|\WP_Error $result Result.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    private function rest($result)
    {
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    /**
     * Stage design.
     *
     * @param string $runId Run.
     * @param array  $meta  Run facts.
     * @param array  $brief Brief.
     *
     * @return array|\WP_Error
     */
    private function design($runId, array $meta, array $brief)
    {
        $kit = $this->library->kit($meta['kit']);

        if ($kit === null) {
            return new \WP_Error('sfcore_ai_no_kit', __('That design is not available.', 'swishfolio-core'), ['status' => 409]);
        }

        $journal = new MutationJournal(new WpdbJournalRepository());
        $context = ToolContext::forUser(get_current_user_id(), $runId);
        $journal->resume($context);
        $applied = false;

        // A site cloned from the template already has its design.
        if ($this->library->current() !== $kit->getSlug()) {
            if (SiteTemplates::get($kit->getSlug()) === null) {
                return new \WP_Error('sfcore_ai_no_kit', __('That design is not available.', 'swishfolio-core'), ['status' => 409]);
            }
            try {
                (new KitDesign($journal))->apply($kit->getSlug(), $context);
                $applied = true;
            } catch (ToolException $e) {
                return new \WP_Error('sfcore_kit_design_failed', $e->getMessage(), ['status' => 409, 'runId' => $runId]);
            }
        }

        $relinked = $this->relink($meta, $kit, $journal, $context);
        $parts    = self::parts();

        $scope = [
            'runId'  => $runId,
            'posts'  => array_map(static function ($p) {
                return (int) $p['postId'];
            }, $parts),
            'design' => trim($brief['prompt']) !== '',
            // Also without the owner's photos: the template's photos are checked
            // against the business (a coffee shop must not show octopus).
            'images' => (bool) self::photos($brief) || (bool) KitImages::slots(self::draftPages($meta)),
            'model'  => (new ModelRouter($this->settings))->modelFor(self::KIND),
        ];

        return [
            'applied'      => $applied,
            'design'       => $kit->getName(),
            'parts'        => $parts,
            'relinked'     => $relinked,
            'scope'        => $scope,
            'confirmToken' => ConfirmToken::issue(self::calls($scope), get_current_user_id()),
            'runId'        => $runId,
        ];
    }

    /**
     * Job: part, tokens or images.
     *
     * @param array       $payload  stage, runId, scope, target, brief.
     * @param JobProgress $progress Progress.
     *
     * @return array|\WP_Error
     */
    public function runJob(array $payload, JobProgress $progress)
    {
        $runId = (string) $payload['runId'];
        $meta  = GenerationController::runMeta($runId);
        $scope = self::scope((array) $payload['scope']);
        $brief = CustomiseController::cleanBrief((array) $payload['brief']);

        if ($meta === null) {
            return new \WP_Error('sfcore_ai_bad_request', __('That build could not be found.', 'swishfolio-core'), ['status' => 404]);
        }

        try {
            $provider = $this->providers->configured();
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_not_configured', $e->getMessage(), ['status' => 409]);
        }

        $journal = new MutationJournal(new WpdbJournalRepository());
        $runner  = new ToolRunner(new ToolRegistry([new RewriteCopyTool($journal), new SetDesignTokensTool($journal)]), $journal);
        $context = ToolContext::forUser(get_current_user_id(), $runId);
        $journal->resume($context);
        // The owner started this build; the scope was signed for it (ConfirmToken).
        $context->setConfirmed(true);

        $prompts    = new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt');
        $customiser = new TemplateCustomiser($provider, $prompts, $runner);
        $startedAt  = microtime(true);
        $response   = null;

        if (function_exists('set_time_limit')) {
            @set_time_limit(320); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }
        $timeout = static function () {
            return 300;
        };
        add_filter('swishfolio_ai_http_timeout', $timeout);

        try {
            if ($payload['stage'] === 'part') {
                $title = strpos((string) get_post_field('post_name', (int) $payload['target']), 'header') === 0 ? __('header', 'swishfolio-core') : __('footer', 'swishfolio-core');
                $progress->step('part', sprintf(/* translators: %s: header or footer. */ __('Personalising your %s', 'swishfolio-core'), $title));
                $brief['sitePages'] = self::sitePages($meta);
                $result   = $customiser->customisePost((int) $payload['target'], $brief, $scope['model'], $context);
                $response = isset($result['response']) ? $result['response'] : null;
                unset($result['response']);
                $progress->done('part', $result['summary']);
            } elseif ($payload['stage'] === 'tokens') {
                $progress->step('tokens', __('Applying your colour and style wishes', 'swishfolio-core'));
                $brief['useLogoColors'] = false;
                $result   = $customiser->customiseDesign($brief, $scope['model'], $context);
                $response = $result['response'];
                unset($result['response']);
                $progress->done('tokens', $result['applied'] ? implode(', ', $result['changes']) : __('No design changes needed', 'swishfolio-core'));
            } else {
                $progress->step('images', __('Adding your photos', 'swishfolio-core'));
                $result   = $this->images($meta, $brief, $provider, $prompts, $runner, $context, $journal);
                $response = $result['response'];
                unset($result['response']);
                $progress->done('images', $result['summary']);
            }
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_provider_error', __("AI couldn't finish this part. Nothing was changed on it.", 'swishfolio-core'), ['status' => 502, 'detail' => $e->getMessage()]);
        } catch (ToolException $e) {
            return new \WP_Error('sfcore_customise_failed', $e->getMessage(), ['status' => 409, 'runId' => $runId]);
        } finally {
            remove_filter('swishfolio_ai_http_timeout', $timeout);
        }

        if ($response !== null) {
            $this->logger->record($response, 0, (int) round((microtime(true) - $startedAt) * 1000), 'customise');
        }

        // Only the design-heavy kind marks the site; images run on the follow-up model.
        if ($payload['stage'] !== 'images') {
            (new ModelRouter($this->settings))->markSucceeded(self::KIND);
        }

        $result['stage'] = $payload['stage'];
        $result['runId'] = $runId;
        $result['usage'] = TemplateCustomiser::usage($response);

        return $result;
    }

    /**
     * @return array {placed, pages, summary, response}
     */
    private function images(array $meta, array $brief, $provider, PromptManager $prompts, ToolRunner $runner, ToolContext $context, MutationJournal $journal)
    {
        $photos = self::photos($brief);
        $slots  = KitImages::slots(self::draftPages($meta));
        $placer = new KitImages($provider, $prompts, $runner);
        $kit    = $this->library->kit((string) $meta['kit']);
        $response = null;

        if (! $slots) {
            return ['placed' => 0, 'pages' => 0, 'summary' => __('No placeholder photos to replace', 'swishfolio-core'), 'response' => null];
        }

        // The kit's photos and what they show, for swapping a misfit for a fitting one.
        $catalogue = [];
        foreach ($kit !== null ? $kit->getAssets() : [] as $name => $asset) {
            if (! empty($asset['alt']) && $kit->assetUrl((string) $name) !== null) {
                $catalogue[(string) $name] = (string) $asset['alt'];
            }
        }

        $recasts = [];
        try {
            $business = trim((isset($brief['business']['name']) ? $brief['business']['name'] : '') . '. ' . (isset($brief['business']['brief']) ? mb_substr($brief['business']['brief'], 0, 600) : ''));
            $planned  = $placer->plan($slots, $photos, $business, (new ModelRouter($this->settings))->modelFor(KitImages::KIND), $catalogue);
            $response = $planned['response'];
            $placements = $planned['placements'];
            $recasts    = $planned['recasts'];
        } catch (ProviderException $e) {
            $placements = [];
        }

        if (! $placements && $photos) {
            $placements = KitImages::fallback($slots, $photos);
        }

        $changed = $placements ? $placer->apply($placements, $context) : 0;
        $swapped = 0;
        if ($recasts && $kit !== null) {
            $swapped = KitImages::recast($recasts, [$kit, 'assetUrl'], $catalogue, function ($postId, $before, $after) use ($journal, $context) {
                return $this->write($journal, $context, $postId, $before, $after, __('Swapped template photos that did not suit your business', 'swishfolio-core'));
            });
        }

        return [
            'placed'   => count($placements),
            'recast'   => $swapped ? count($recasts) : 0,
            'pages'    => $changed + $swapped,
            /* translators: 1: photos placed, 2: template photos swapped. */
            'summary'  => sprintf(__('%1$d of your photos placed, %2$d template photos swapped for ones that suit you', 'swishfolio-core'), count($placements), $swapped ? count($recasts) : 0),
            'response' => $response,
        ];
    }

    /**
     * The build's own pages, still drafts and editable, front page flagged.
     *
     * @param array $meta Run facts.
     *
     * @return array[] {postId, title, front}
     */
    private static function draftPages(array $meta)
    {
        $pages = [];
        foreach ($meta['pages'] as $page) {
            if (! empty($page['created']) && 'draft' === get_post_status((int) $page['postId']) && current_user_can('edit_post', (int) $page['postId'])) {
                $pages[] = ['postId' => (int) $page['postId'], 'title' => $page['title'], 'front' => $page['key'] === $meta['front']];
            }
        }

        return $pages;
    }

    /**
     * Stage finish.
     *
     * @param string $runId Run.
     * @param array  $meta  Run facts.
     * @param array  $brief Brief.
     *
     * @return array
     */
    private function finish($runId, array $meta, array $brief)
    {
        $journal = new MutationJournal(new WpdbJournalRepository());
        $context = ToolContext::forUser(get_current_user_id(), $runId);
        $journal->resume($context);
        $kit     = $this->library->kit($meta['kit']);
        $out     = ['logo' => false, 'relinked' => 0, 'samplePage' => false, 'scan' => null, 'runId' => $runId];

        // Logo: the site logo, and in the header where the kit draws its wordmark.
        $logo = 0;
        foreach (self::photos($brief, true) as $image) {
            $logo = (int) $image['id'];
            break;
        }
        if ($logo > 0) {
            if ((int) get_option('site_logo') !== $logo) {
                $journal->updateOption($context, self::TOOL, 'site_logo', $logo, __('Set your logo as the site logo', 'swishfolio-core'));
            }
            $name = (string) get_bloginfo('name');
            foreach (self::parts() as $part) {
                if ($part['type'] !== 'header') {
                    continue;
                }
                $content = (string) get_post_field('post_content', $part['postId']);
                $updated = KitBranding::withLogo($content, (string) wp_get_attachment_image_url($logo, 'medium'), $name);
                $out['logo'] = $this->write($journal, $context, $part['postId'], $content, $updated, __('Put your logo in the header', 'swishfolio-core')) || $out['logo'];
            }
            $out['logo'] = $out['logo'] || (int) get_option('site_logo') === $logo;
        }

        if ($kit !== null) {
            $out['relinked'] = $this->relink($meta, $kit, $journal, $context);
        }

        // A newsletter sign-up in the header or footer that sends nothing.
        foreach (self::parts() as $part) {
            $content = (string) get_post_field('post_content', $part['postId']);
            $this->write($journal, $context, $part['postId'], $content, KitForms::dropNewsletters($content), __('Removed a sign-up form that sent nothing', 'swishfolio-core'));
        }

        // WordPress's own "Sample Page" is somebody else's demo copy too.
        $sample = get_page_by_path('sample-page', OBJECT, 'page');
        if ($sample && 'publish' === $sample->post_status && strpos($sample->post_content, 'bike messenger') !== false && current_user_can('edit_post', $sample->ID)) {
            $journal->updatePost($context, self::TOOL, ['ID' => (int) $sample->ID, 'post_status' => 'draft'], __('Unpublished the WordPress sample page', 'swishfolio-core'));
            $out['samplePage'] = true;
        }

        if ($kit !== null) {
            $out['scan'] = $this->scan($kit, $meta, $brief, $journal, $context);

            // The footer's giant wordmark sized for the owner's name, whatever its length.
            $design = $kit->getDesignFile();
            $sizes  = WordmarkFit::sizes(isset($design['globalStyles']['styles']['css']) ? (string) $design['globalStyles']['styles']['css'] : '');
            foreach ($sizes ? self::parts() : [] as $part) {
                $content = (string) get_post_field('post_content', $part['postId']);
                $this->write($journal, $context, $part['postId'], $content, WordmarkFit::apply($content, $sizes), __('Sized the footer wordmark to your business name', 'swishfolio-core'));
            }
        }

        return $out;
    }

    /**
     * The leftover scan: fix, then report what remains.
     *
     * @return array {needles, fixed, remaining}
     */
    private function scan(Kit $kit, array $meta, array $brief, MutationJournal $journal, ToolContext $context)
    {
        $owner = implode("\n", array_merge(array_values((array) $brief['business']), array_values((array) $brief['contact']), [$brief['prompt'], $brief['documents']]));
        $scan  = (new DemoScan($kit->getDemo()))->exceptOwner($owner);
        $with  = [
            'business' => (string) get_bloginfo('name'),
            'phone'    => isset($brief['contact']['phone']) ? (string) $brief['contact']['phone'] : '',
            'email'    => isset($brief['contact']['email']) ? (string) $brief['contact']['email'] : '',
        ];

        $targets = [];
        foreach ($meta['pages'] as $page) {
            $targets[(int) $page['postId']] = 'page';
        }
        foreach (self::parts() as $part) {
            $targets[$part['postId']] = $part['type'];
            foreach (KitDesign::navigationRefs((string) get_post_field('post_content', $part['postId'])) as $ref) {
                $targets[(int) $ref] = 'menu';
            }
        }
        // Anything else visitors can see once the build is published.
        foreach (get_posts(['post_type' => ['page', 'post'], 'post_status' => 'publish', 'posts_per_page' => 50, 'fields' => 'ids', 'no_found_rows' => true]) as $id) {
            if (! isset($targets[(int) $id])) {
                $targets[(int) $id] = 'other';
            }
        }

        $fixed     = 0;
        $remaining = [];

        foreach ($targets as $postId => $where) {
            $post = get_post($postId);
            if (! $post || 'trash' === $post->post_status) {
                continue;
            }
            $content = (string) $post->post_content;

            // Only what this build wrote is fixed; anything else is reported.
            if ($where !== 'other' && $scan->scan(DemoScan::visible($content)) && current_user_can('edit_post', $postId)) {
                $updated = $scan->neutralise($scan->fix($content, $with), $with['business']);
                if ($this->write($journal, $context, $postId, $content, $updated, __('Replaced leftover demo details', 'swishfolio-core'))) {
                    $fixed++;
                    $content = $updated;
                }
            }

            // A kit menu is titled after the demo brand ("Lumbre"); the editor shows it.
            if ($where === 'menu' && $scan->scan($post->post_title) && current_user_can('edit_post', $postId)) {
                $title = sprintf(__('%s menu', 'swishfolio-core'), $with['business']);
                $journal->updatePost($context, self::TOOL, wp_slash(['ID' => (int) $postId, 'post_title' => $title]), __('Renamed the menu', 'swishfolio-core'));
                $post->post_title = $title;
                $fixed++;
            }

            foreach ($scan->scan(DemoScan::visible($content) . ' ' . $post->post_title) as $hit) {
                $remaining[] = array_merge($hit, ['postId' => $postId, 'where' => $where, 'title' => $post->post_title !== '' ? $post->post_title : $post->post_name]);
            }
        }

        foreach (['blogname' => 'site title', 'blogdescription' => 'tagline'] as $option => $label) {
            foreach ($scan->scan((string) get_option($option)) as $hit) {
                $remaining[] = array_merge($hit, ['postId' => 0, 'where' => 'setting', 'title' => $label]);
            }
        }

        return ['needles' => count($scan->needles()), 'fixed' => $fixed, 'remaining' => $remaining];
    }

    /**
     * Point the header, footer and their menus at the generated pages.
     *
     * @return int Posts changed.
     */
    private function relink(array $meta, Kit $kit, MutationJournal $journal, ToolContext $context)
    {
        $pages = [];
        foreach ($meta['pages'] as $page) {
            $pages[] = array_merge($page, ['url' => (string) get_page_link((int) $page['postId'], false, true)]);
        }

        $links   = new KitLinks(home_url('/'), $pages, $meta['front'], $meta['navigation'], $kit->getPages());
        $changed = 0;

        foreach (self::parts() as $part) {
            $content = (string) get_post_field('post_content', $part['postId']);
            $changed += $this->write($journal, $context, $part['postId'], $content, $links->remap($content), __('Pointed the links at your new pages', 'swishfolio-core')) ? 1 : 0;

            foreach (KitDesign::navigationRefs($content) as $ref) {
                $nav = get_post((int) $ref);
                if (! $nav || 'wp_navigation' !== $nav->post_type || ! current_user_can('edit_post', $nav->ID)) {
                    continue;
                }
                $menu    = (string) $nav->post_content;
                $updated = $part['type'] === 'header' && $meta['navigation'] ? $links->menu() : $links->remapMenu($menu);
                $changed += $this->write($journal, $context, (int) $nav->ID, $menu, $updated, __('Rebuilt the menu from your new pages', 'swishfolio-core')) ? 1 : 0;
            }
        }

        return $changed;
    }

    /**
     * One journalled body update of markup this build vouches for.
     *
     * @return bool Whether it changed.
     */
    private function write(MutationJournal $journal, ToolContext $context, $postId, $before, $after, $summary)
    {
        if ($after === $before || ! current_user_can('edit_post', (int) $postId)) {
            return false;
        }

        TrustedMarkup::allow((int) $postId, $before);
        TrustedMarkup::allow((int) $postId, $after);
        $journal->updatePost($context, self::TOOL, wp_slash(['ID' => (int) $postId, 'post_content' => $after]), $summary);

        return true;
    }

    /**
     * This theme's header and footer parts.
     *
     * @return array[] {postId, title, type}
     */
    private static function parts()
    {
        $out   = [];
        $posts = get_posts([
            'post_type'      => 'wp_template_part',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'no_found_rows'  => true,
            'orderby'        => 'name',
            'order'          => 'DESC',
            'tax_query'      => [[
                'taxonomy' => 'wp_theme',
                'field'    => 'name',
                'terms'    => get_stylesheet(),
            ]],
        ]);

        foreach ($posts as $post) {
            if (preg_match('/^(header|footer)/', $post->post_name, $m) && current_user_can('edit_post', $post->ID)) {
                $out[] = ['postId' => (int) $post->ID, 'title' => $m[1] === 'header' ? __('Header', 'swishfolio-core') : __('Footer', 'swishfolio-core'), 'type' => $m[1]];
            }
        }

        return $out;
    }

    /**
     * The owner's uploaded images (photos, or with $logos only the logo).
     *
     * @param array $brief Brief.
     * @param bool  $logos Logos instead of photos.
     *
     * @return array[] {id, role, note, size}
     */
    private static function photos(array $brief, $logos = false)
    {
        $out = [];
        foreach ($brief['images'] as $image) {
            $id = (int) $image['id'];
            if ($id <= 0 || ! wp_attachment_is_image($id) || ! current_user_can('edit_post', $id) || ($image['role'] === 'logo') !== $logos) {
                continue;
            }
            $meta  = wp_get_attachment_metadata($id);
            $out[] = [
                'id'   => $id,
                'role' => ['people' => 'photo of the owner or their team', 'work' => 'photo of their work, products or food', 'place' => 'photo of their premises', 'logo' => 'logo'][$image['role']] ?? 'photo',
                'note' => (string) $image['note'],
                'size' => is_array($meta) && ! empty($meta['width']) ? $meta['width'] . 'x' . $meta['height'] : '',
            ];
        }

        return $out;
    }

    /**
     * @param array $meta Run facts.
     *
     * @return string "Title: URL" lines, menu order first.
     */
    private static function sitePages(array $meta)
    {
        $lines = [];
        foreach ($meta['pages'] as $page) {
            $url     = $page['key'] === $meta['front'] ? home_url('/') : (string) get_page_link((int) $page['postId'], false, true);
            $lines[] = $page['title'] . ': ' . $url . ($page['key'] === $meta['front'] ? ' (home page)' : '');
        }

        return implode("\n", $lines);
    }

    /**
     * @param array $raw Scope from the client.
     *
     * @return array Canonical scope.
     */
    private static function scope(array $raw)
    {
        return [
            'runId'  => isset($raw['runId']) && preg_match('/^[a-f0-9]{32}$/', (string) $raw['runId']) ? (string) $raw['runId'] : '',
            'posts'  => array_values(array_map('intval', isset($raw['posts']) ? (array) $raw['posts'] : [])),
            'design' => ! empty($raw['design']),
            'images' => ! empty($raw['images']),
            'model'  => isset($raw['model']) ? (string) $raw['model'] : '',
        ];
    }

    /**
     * @param array $scope Scope.
     *
     * @return array The calls the token signs.
     */
    private static function calls(array $scope)
    {
        return [['name' => self::TOOL, 'input' => $scope]];
    }
}
