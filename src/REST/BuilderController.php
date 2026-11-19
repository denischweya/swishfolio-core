<?php
/**
 * REST route behind the editor's floating AI prompt bar.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\REST;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\ModelRouter;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\AI\UsageLogger;
use SwishfolioCore\AI\Progress\BuilderStreamTracker;
use SwishfolioCore\Jobs\BackgroundJobs;
use SwishfolioCore\Jobs\JobProgress;

/**
 * Prompt-to-blocks for any block, any selection or the whole page.
 *
 * Like /ai/edit this writes nothing: the editor sends the target blocks as
 * serialized markup, the model answers with a short list of operations
 * (replace, insert before/after, remove, append) carrying new block markup, and
 * the editor applies them as one undoable step. Every piece of returned markup
 * is parsed here first and refused unless it is made only of registered blocks;
 * users without unfiltered_html get it through filter_block_content, the same
 * filter a normal save would apply.
 */
class BuilderController
{
    const NAMESPACE_V1 = 'swishfolio/v1';
    const CAPABILITY   = 'swishfolio_use_ai';
    const RATE_LIMIT   = 40;
    const RATE_WINDOW  = 3600;

    const MAX_SECTIONS_CHARS  = 200000;
    const MAX_REFERENCE_CHARS = 60000;
    const MAX_INSTRUCTION     = 2000;

    const OPS = ['replace', 'insert_before', 'insert_after', 'remove', 'append'];

    /** @var Settings */
    private $settings;

    /** @var ProviderRegistry */
    private $providers;

    /** @var UsageLogger */
    private $logger;

    /** @var BackgroundJobs|null */
    private $jobs;

    public function __construct(Settings $settings, ProviderRegistry $providers, UsageLogger $logger, BackgroundJobs $jobs = null)
    {
        $this->settings  = $settings;
        $this->providers = $providers;
        $this->logger    = $logger;
        $this->jobs      = $jobs;

        if ($jobs !== null) {
            $jobs->register('builder', [$this, 'execute']);
        }
    }

    /**
     * @return void
     */
    public function registerRoutes()
    {
        register_rest_route(self::NAMESPACE_V1, '/ai/builder', [
            'methods'             => 'POST',
            'callback'            => [$this, 'build'],
            'permission_callback' => [$this, 'canUse'],
            'args'                => [
                'postId'      => ['type' => 'integer', 'required' => true],
                'instruction' => ['type' => 'string', 'required' => true],
                'scope'       => ['type' => 'string', 'required' => true, 'enum' => ['page', 'selection']],
                'sections'    => ['type' => 'array', 'required' => false, 'items' => ['type' => 'string']],
                'reference'   => ['type' => 'string', 'required' => false],
                'title'       => ['type' => 'string', 'required' => false],
                'async'       => ['type' => 'boolean', 'required' => false],
            ],
        ]);
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return bool|\WP_Error
     */
    public function canUse($request)
    {
        if (! is_user_logged_in()) {
            return new \WP_Error('sfcore_ai_forbidden', __('You must be signed in.', 'swishfolio-core'), ['status' => 401]);
        }

        if (! current_user_can(self::CAPABILITY)) {
            return new \WP_Error('sfcore_ai_forbidden', __('You cannot use AI editing.', 'swishfolio-core'), ['status' => 403]);
        }

        if (! $this->settings->get()['enabled']) {
            return new \WP_Error('sfcore_ai_disabled', __('AI features are turned off for this site.', 'swishfolio-core'), ['status' => 403]);
        }

        $postId = (int) $request->get_param('postId');

        if ($postId <= 0 || ! current_user_can('edit_post', $postId)) {
            return new \WP_Error('sfcore_ai_forbidden', __('You cannot edit this post.', 'swishfolio-core'), ['status' => 403]);
        }

        return true;
    }

    /**
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function build($request)
    {
        $instruction = trim((string) $request->get_param('instruction'));
        $scope       = (string) $request->get_param('scope');
        $sections    = array_values(array_map('strval', (array) $request->get_param('sections')));

        if ($instruction === '') {
            return new \WP_Error('sfcore_ai_bad_selection', __('Describe what you want first.', 'swishfolio-core'), ['status' => 400]);
        }

        if ($scope === 'selection' && ! $sections) {
            return new \WP_Error('sfcore_ai_bad_selection', __('No block was selected.', 'swishfolio-core'), ['status' => 400]);
        }

        if (strlen(implode('', $sections)) > self::MAX_SECTIONS_CHARS) {
            return new \WP_Error(
                'sfcore_ai_too_large',
                __('This is too much content for one request. Select a section and try again.', 'swishfolio-core'),
                ['status' => 413]
            );
        }

        $limited = $this->checkRateLimit();

        if ($limited !== null) {
            return $limited;
        }

        try {
            $this->providers->configured();
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_not_configured', $e->getMessage(), ['status' => 409]);
        }

        $payload = [
            'postId'      => (int) $request->get_param('postId'),
            'instruction' => $instruction,
            'scope'       => $scope,
            'sections'    => $sections,
            'reference'   => (string) $request->get_param('reference'),
            'title'       => (string) $request->get_param('title'),
        ];

        // A whole page can take more than a minute to write, longer than many
        // hosts let a request live. Queued, it runs after this response is
        // sent and the editor polls /ai/jobs/<id> for its steps.
        if ($request->get_param('async') && $this->jobs !== null) {
            $job = $this->jobs->queue('builder', mb_substr($instruction, 0, 120), $payload, get_current_user_id());

            return rest_ensure_response(array_merge($job->toStatusArray(), ['queued' => true]));
        }

        $result = $this->execute($payload, JobProgress::silent());

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    /**
     * The work itself: one provider call, then validation of every operation.
     * Runs inside the request (synchronous) or in a background job.
     *
     * @param array       $payload  Validated request fields.
     * @param JobProgress $progress Where steps are reported.
     *
     * @return array|\WP_Error {summary, operations}.
     */
    public function execute(array $payload, JobProgress $progress)
    {
        $instruction = (string) $payload['instruction'];
        $scope       = (string) $payload['scope'];
        $sections    = array_values(array_map('strval', (array) $payload['sections']));
        $reference   = (string) $payload['reference'];
        $postId      = (int) $payload['postId'];
        $post        = get_post($postId);

        $progress->step('read', __('Reading the page', 'swishfolio-core'), $this->describeTarget($scope, $sections));

        try {
            $provider = $this->providers->configured();
        } catch (ProviderException $e) {
            return new \WP_Error('sfcore_ai_not_configured', $e->getMessage(), ['status' => 409]);
        }

        // The page itself is the best style guide. When it is empty (or only a
        // selection was sent and no reference came with it) fall back to the
        // front page, so a new page is written in the site's existing look.
        if ($reference === '' && ($scope === 'selection' || ! $sections)) {
            $reference = $this->frontPageMarkup($postId);

            if ($reference !== '' && ! $sections) {
                $progress->detail('read', __('Empty page · using your home page as the style guide', 'swishfolio-core'));
            }
        }

        $prompts = new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt');

        $numbered = [];
        foreach ($sections as $index => $markup) {
            $numbered[] = '<section index="' . $index . '">' . "\n" . $markup . "\n</section>";
        }

        $userPrompt = $prompts->task('builder', [
            'instruction' => mb_substr($instruction, 0, self::MAX_INSTRUCTION),
            'scope'       => $scope === 'page'
                ? ($sections ? 'the whole page (each top-level block is one section)' : 'the whole page, which is currently empty')
                : 'only the selected block(s); leave everything else on the page alone',
            'page'        => wp_json_encode([
                'title'    => (string) $payload['title'] ?: ($post ? $post->post_title : ''),
                'postType' => $post ? $post->post_type : 'page',
                'site'     => get_bloginfo('name'),
                'tagline'  => get_bloginfo('description'),
            ]),
            'sections'    => $sections ? implode("\n\n", $numbered) : '(none: the target is empty, use "append")',
            'reference'   => $reference !== '' ? mb_substr($reference, 0, self::MAX_REFERENCE_CHARS) : '(none)',
        ]);

        $system = $prompts->system('builder') . "\n\n" . 'Site facts (JSON):' . "\n" . wp_json_encode($this->siteFacts());

        // A whole-page request writes or redesigns the page: design-heavy.
        // A selection is a small edit and always uses the follow-up model.
        $kind      = $scope === 'page' ? ModelRouter::KIND_BUILDER_PAGE : ModelRouter::KIND_BUILDER_SELECTION;
        $router    = new ModelRouter($this->settings);
        $model     = $router->modelFor($kind);
        $maxTokens = (int) apply_filters('swishfolio_ai_builder_max_tokens', $scope === 'page' ? 16000 : 8000, $scope);

        // Writing a whole page of markup takes a while; the default 120s is
        // tuned for the small structured edits.
        if (function_exists('set_time_limit')) {
            @set_time_limit(320); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }
        $timeout = function () {
            return 300;
        };
        add_filter('swishfolio_ai_http_timeout', $timeout);

        $progress->step('ask', __('Asking AI', 'swishfolio-core'), __('Sending the page and your request', 'swishfolio-core'));

        $tracker   = new BuilderStreamTracker($progress, $scope, count($sections));
        $aiRequest = (new AIRequest($system, $userPrompt, $this->schema(), $model, $maxTokens))->withProgress($tracker);
        $startedAt = microtime(true);

        try {
            $response = $provider->generate($aiRequest);
        } catch (ProviderException $e) {
            remove_filter('swishfolio_ai_http_timeout', $timeout);

            return new \WP_Error(
                'sfcore_ai_provider_error',
                __("AI couldn't complete this request. Nothing was changed.", 'swishfolio-core'),
                ['status' => 502, 'detail' => $e->getMessage()]
            );
        }

        remove_filter('swishfolio_ai_http_timeout', $timeout);
        $tracker->finish();

        $duration   = (int) round((microtime(true) - $startedAt) * 1000);
        $decoded    = $response->getDecoded();
        $operations = [];
        $raw        = isset($decoded['operations']) ? (array) $decoded['operations'] : [];

        $progress->step('check', __('Checking blocks', 'swishfolio-core'));

        foreach ($raw as $position => $op) {
            $checked = $this->checkOperation($op, count($sections));

            if (is_wp_error($checked)) {
                $this->logger->recordFailure('builder', $response->getProviderSlug(), $response->getModel(), $duration);
                $checked->add_data(['status' => 422, 'path' => 'operations.' . $position]);

                return $checked;
            }

            $operations[] = $checked;
        }

        $blocks = 0;
        foreach ($operations as $op) {
            $blocks += $op['markup'] === '' ? 0 : $this->countBlocks(parse_blocks($op['markup']));
        }

        $progress->done(
            'check',
            /* translators: %d: number of blocks. */
            sprintf(_n('%d block is valid', 'All %d blocks are valid', $blocks, 'swishfolio-core'), $blocks)
        );

        $this->logger->record($response, count($operations), $duration, 'proposed');
        $router->markSucceeded($kind);

        return [
            'summary'    => isset($decoded['summary']) ? (string) $decoded['summary'] : '',
            'operations' => $operations,
        ];
    }

    /**
     * @param string   $scope    Scope.
     * @param string[] $sections Sent markup.
     *
     * @return string One line for the "Reading the page" step.
     */
    private function describeTarget($scope, array $sections)
    {
        if (! $sections) {
            return __('Empty page', 'swishfolio-core');
        }

        $words = str_word_count(wp_strip_all_tags(implode(' ', $sections)));

        if ($scope === 'selection') {
            /* translators: 1: number of selected blocks, 2: number of words. */
            return sprintf(_n('%1$d selected block · %2$s words', '%1$d selected blocks · %2$s words', count($sections), 'swishfolio-core'), count($sections), number_format_i18n($words));
        }

        /* translators: 1: number of sections, 2: number of words. */
        return sprintf(_n('%1$d section · %2$s words', '%1$d sections · %2$s words', count($sections), 'swishfolio-core'), count($sections), number_format_i18n($words));
    }

    /**
     * @param array $blocks Parsed blocks.
     *
     * @return int Named blocks, nested included.
     */
    private function countBlocks(array $blocks)
    {
        $count = 0;

        foreach ($blocks as $block) {
            if (! empty($block['blockName'])) {
                $count += 1 + $this->countBlocks((array) $block['innerBlocks']);
            }
        }

        return $count;
    }

    /**
     * Validate one operation and clean its markup.
     *
     * @param mixed $op           Operation from the model.
     * @param int   $sectionCount How many sections were sent.
     *
     * @return array|\WP_Error
     */
    private function checkOperation($op, $sectionCount)
    {
        $invalid = function ($why) {
            return new \WP_Error(
                'sfcore_ai_invalid_response',
                __('The AI answered with something the editor cannot use. Nothing was changed.', 'swishfolio-core'),
                ['detail' => $why]
            );
        };

        if (! is_array($op) || ! isset($op['op']) || ! in_array($op['op'], self::OPS, true)) {
            return $invalid('Unknown operation.');
        }

        $type  = $op['op'];
        $index = isset($op['index']) ? (int) $op['index'] : -1;

        if ($type !== 'append' && ($index < 0 || $index >= $sectionCount)) {
            return $invalid(sprintf('Section index %d does not exist.', $index));
        }

        if ($type === 'remove') {
            return ['op' => $type, 'index' => $index, 'markup' => ''];
        }

        $markup = trim(isset($op['markup']) ? (string) $op['markup'] : '');

        if ($markup === '') {
            return $invalid('Empty markup.');
        }

        $problem = $this->markupProblem(parse_blocks($markup));

        if ($problem !== null) {
            return $invalid($problem);
        }

        // Always filtered, whoever is asking: unfiltered_html (super admins)
        // would otherwise let injected text in a reference or page put script
        // into the editor. filter_block_content() only cleans attributes;
        // wp_kses_post() also cleans each block's HTML.
        $markup = wp_kses_post(filter_block_content($markup, 'post'));

        return ['op' => $type, 'index' => $type === 'append' ? -1 : $index, 'markup' => $markup];
    }

    /**
     * @param array $blocks Parsed blocks.
     *
     * @return string|null Why the markup is unusable, or null.
     */
    private function markupProblem(array $blocks)
    {
        $registry = \WP_Block_Type_Registry::get_instance();
        $named    = 0;

        foreach ($blocks as $block) {
            if (empty($block['blockName'])) {
                if (trim((string) $block['innerHTML']) !== '') {
                    return 'Markup outside of a block.';
                }
                continue;
            }

            if (! $registry->is_registered($block['blockName'])) {
                return sprintf('Unknown block "%s".', $block['blockName']);
            }

            $named++;

            if (! empty($block['innerBlocks'])) {
                $inner = $this->markupProblem($block['innerBlocks']);
                if ($inner !== null) {
                    return $inner;
                }
            }
        }

        return $named > 0 ? null : 'No blocks found.';
    }

    /**
     * @return array JSON Schema for the model's answer.
     */
    private function schema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['summary', 'operations'],
            'properties'           => [
                'summary'    => [
                    'type'        => 'string',
                    'description' => 'One short sentence telling the user what you changed.',
                ],
                'operations' => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['op', 'index', 'markup'],
                        'properties'           => [
                            'op'     => ['type' => 'string', 'enum' => self::OPS],
                            'index'  => ['type' => 'integer', 'description' => 'Section index the operation applies to; -1 for append.'],
                            'markup' => ['type' => 'string', 'description' => 'Serialized block markup; empty string for remove.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Design tokens, usable blocks and media the model may draw on.
     *
     * @return array
     */
    private function siteFacts()
    {
        $slugs = function ($items) {
            $out = [];
            foreach ((array) $items as $origin) {
                foreach ((array) $origin as $item) {
                    if (is_array($item) && isset($item['slug'])) {
                        $out[] = isset($item['color']) ? $item['slug'] . ' (' . $item['color'] . ')' : $item['slug'];
                    }
                }
            }
            return array_values(array_unique($out));
        };

        $blocks = array_keys(\WP_Block_Type_Registry::get_instance()->get_all_registered());
        $blocks = array_values(array_filter($blocks, function ($name) {
            return ! in_array($name, ['core/legacy-widget', 'core/widget-group', 'core/missing', 'core/freeform', 'core/block', 'core/pattern', 'core/template-part', 'core/post-content', 'core/comments', 'core/navigation-link', 'core/navigation-submenu', 'core/home-link'], true);
        }));

        $media = [];
        foreach (get_posts(['post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => 30, 'post_status' => 'inherit']) as $attachment) {
            $src = wp_get_attachment_image_src($attachment->ID, 'large');
            if ($src) {
                $media[] = [
                    'id'  => $attachment->ID,
                    'url' => $src[0],
                    'alt' => (string) get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
                ];
            }
        }

        return [
            'colors'       => $slugs(wp_get_global_settings(['color', 'palette'])),
            'gradients'    => $slugs(wp_get_global_settings(['color', 'gradients'])),
            'fontSizes'    => $slugs(wp_get_global_settings(['typography', 'fontSizes'])),
            'fontFamilies' => $slugs(wp_get_global_settings(['typography', 'fontFamilies'])),
            'spacing'      => $slugs(wp_get_global_settings(['spacing', 'spacingSizes'])),
            'blocks'       => $blocks,
            'mediaLibrary' => $media,
        ];
    }

    /**
     * @param int $exclude Post being edited.
     *
     * @return string
     */
    private function frontPageMarkup($exclude)
    {
        $id = (int) get_option('page_on_front');

        if ($id <= 0 || $id === (int) $exclude) {
            $pages = get_posts(['post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1, 'exclude' => [(int) $exclude], 'orderby' => 'menu_order', 'order' => 'ASC']);
            $id    = $pages ? $pages[0]->ID : 0;
        }

        return $id > 0 ? (string) get_post_field('post_content', $id) : '';
    }

    /**
     * @return \WP_Error|null
     */
    private function checkRateLimit()
    {
        $limit = (int) apply_filters('swishfolio_ai_builder_rate_limit', self::RATE_LIMIT);

        if ($limit <= 0) {
            return null;
        }

        $key   = 'sfcore_ai_builder_rate_' . get_current_user_id();
        $count = (int) get_transient($key);

        if ($count >= $limit) {
            return new \WP_Error('sfcore_ai_rate_limited', __('Too many AI requests. Try again later.', 'swishfolio-core'), ['status' => 429]);
        }

        set_transient($key, $count + 1, self::RATE_WINDOW);

        return null;
    }
}
