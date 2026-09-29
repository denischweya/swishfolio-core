<?php
/**
 * Main Plugin Class.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore;

use SwishfolioCore\Blocks\CardBlock;
use SwishfolioCore\Blocks\HeroBlock;
use SwishfolioCore\Blocks\HeadingBlock;
use SwishfolioCore\Blocks\PortfolioBlock;
use SwishfolioCore\Blocks\LatestPostsBlock;
use SwishfolioCore\Blocks\TestimonialsBlock;
use SwishfolioCore\Blocks\TestimonialCardBlock;
use SwishfolioCore\Blocks\FaqBlock;
use SwishfolioCore\Blocks\FeaturesBlock;
use SwishfolioCore\Blocks\FeatureCardBlock;
use SwishfolioCore\Blocks\BentoCardsBlock;
use SwishfolioCore\Blocks\BentoCardBlock;
use SwishfolioCore\Blocks\SwishGalleryBlock;
use SwishfolioCore\Blocks\SwishFormBlock;
use SwishfolioCore\Blocks\SwishSocialsBlock;
use SwishfolioCore\Blocks\PricingBlock;
use SwishfolioCore\Blocks\PricingPlanBlock;
use SwishfolioCore\Blocks\SwishCvBlock;
use SwishfolioCore\Blocks\SwishLinksBlock;
use SwishfolioCore\Blocks\SwishCounterBlock;
use SwishfolioCore\Blocks\SwishCounterItemBlock;
use SwishfolioCore\Blocks\SwishSliderBlock;
use SwishfolioCore\Blocks\SwishSlideBlock;
use SwishfolioCore\PostTypes\ProjectPostType;
use SwishfolioCore\Forms\FormEntryPostType;
use SwishfolioCore\Taxonomies\ProjectCategoryTaxonomy;
use SwishfolioCore\Taxonomies\ProjectTypeTaxonomy;
use SwishfolioCore\Admin\AdminMenu;
use SwishfolioCore\Forms\FormProcessor;
use SwishfolioCore\Email\EmailService;
use SwishfolioCore\Email\EspManager;
use SwishfolioCore\Services\SvgSupport;
use SwishfolioCore\Extensions\ColumnsExtension;
use SwishfolioCore\Extensions\ButtonExtension;
use SwishfolioCore\Extensions\NavigationExtension;
use SwishfolioCore\Blocks\BlockRegistry;
use SwishfolioCore\AI\Settings as AISettings;
use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\UsageLogger;
use SwishfolioCore\Services\EncryptionService;
use SwishfolioCore\REST\AIController;
use SwishfolioCore\REST\SettingsController;
use SwishfolioCore\REST\GenerationController;
use SwishfolioCore\AI\Generation\PatternLibrary;
use SwishfolioCore\Admin\AISettingsPage;

/**
 * Plugin orchestrator - boots and wires all components.
 */
class Plugin
{
    /**
     * Registered blocks.
     *
     * @var array
     */
    private array $blocks = [];

    /**
     * Registered post types.
     *
     * @var array
     */
    private array $postTypes = [];

    /**
     * Registered taxonomies.
     *
     * @var array
     */
    private array $taxonomies = [];

    /**
     * Admin menu instance.
     *
     * @var AdminMenu|null
     */
    private ?AdminMenu $adminMenu = null;

    /**
     * Form processor instance.
     *
     * @var FormProcessor|null
     */
    private ?FormProcessor $formProcessor = null;

    /**
     * Email service instance.
     *
     * @var EmailService|null
     */
    private ?EmailService $emailService = null;

    /**
     * ESP manager instance.
     *
     * @var EspManager|null
     */
    private ?EspManager $espManager = null;

    /**
     * SVG support instance.
     *
     * @var SvgSupport|null
     */
    private ?SvgSupport $svgSupport = null;

    /**
     * Columns extension instance.
     *
     * @var ColumnsExtension|null
     */
    private ?ColumnsExtension $columnsExtension = null;

    /**
     * Button extension instance.
     *
     * @var ButtonExtension|null
     */
    private ?ButtonExtension $buttonExtension = null;

    /**
     * Navigation extension instance.
     *
     * @var NavigationExtension|null
     */
    private ?NavigationExtension $navigationExtension = null;

    /**
     * AI capability registry.
     *
     * @var BlockRegistry|null
     */
    private ?BlockRegistry $aiBlockRegistry = null;

    /**
     * AI REST controller.
     *
     * @var AIController|null
     */
    private ?AIController $aiController = null;

    /**
     * AI settings REST controller.
     *
     * @var SettingsController|null
     */
    private ?SettingsController $aiSettingsController = null;

    /**
     * AI generation pattern library.
     *
     * @var PatternLibrary|null
     */
    private ?PatternLibrary $aiPatternLibrary = null;

    /**
     * AI generation REST controller.
     *
     * @var GenerationController|null
     */
    private ?GenerationController $aiGenerationController = null;

    /**
     * AI settings admin screen.
     *
     * @var AISettingsPage|null
     */
    private ?AISettingsPage $aiSettingsPage = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->registerPostTypes();
        $this->registerTaxonomies();
        $this->registerBlocks();

        // Initialize admin menu.
        if (is_admin()) {
            $this->adminMenu = new AdminMenu();
        }

        // Initialize form processor.
        $this->formProcessor = new FormProcessor();

        // Initialize email service.
        $this->emailService = new EmailService();

        // Initialize ESP manager.
        $this->espManager = new EspManager();

        // Initialize SVG support.
        $this->svgSupport = new SvgSupport();

        // Initialize columns extension.
        $this->columnsExtension = new ColumnsExtension();

        // Initialize button extension.
        $this->buttonExtension = new ButtonExtension();

        // Initialize navigation extension.
        $this->navigationExtension = new NavigationExtension();

        // AI block editing.
        $this->aiBlockRegistry = new BlockRegistry([
            SFCORE_PLUGIN_DIR . 'blocks',
            SFCORE_PLUGIN_DIR . 'src/AI/Manifests/core',
        ]);

        $aiSettings  = new AISettings(new EncryptionService());
        $aiProviders = new ProviderRegistry($aiSettings);
        $aiLogger    = new UsageLogger();

        $this->aiController         = new AIController(
            $this->aiBlockRegistry,
            $aiSettings,
            $aiProviders,
            $aiLogger
        );
        $this->aiSettingsController = new SettingsController($aiSettings, $aiProviders);

        $this->aiPatternLibrary = new PatternLibrary([
            SFCORE_PLUGIN_DIR . 'src/AI/Generation/Patterns',
        ]);

        $this->aiGenerationController = new GenerationController(
            $this->aiBlockRegistry,
            $this->aiPatternLibrary,
            $aiSettings,
            $aiProviders,
            $aiLogger
        );

        if (is_admin()) {
            $this->aiSettingsPage = new AISettingsPage($aiSettings, $aiProviders, $aiLogger);
        }
    }

    /**
     * Register all post types.
     *
     * @return void
     */
    private function registerPostTypes(): void
    {
        $this->postTypes = [
            new ProjectPostType(),
            new FormEntryPostType(),
        ];
    }

    /**
     * Register all taxonomies.
     *
     * @return void
     */
    private function registerTaxonomies(): void
    {
        $this->taxonomies = [
            new ProjectCategoryTaxonomy(),
            new ProjectTypeTaxonomy(),
        ];
    }

    /**
     * Register all blocks.
     *
     * @return void
     */
    private function registerBlocks(): void
    {
        $this->blocks = [
            new CardBlock(),
            new HeroBlock(),
            new HeadingBlock(),
            new PortfolioBlock(),
            new LatestPostsBlock(),
            new TestimonialsBlock(),
            new TestimonialCardBlock(),
            new FaqBlock(),
            new FeaturesBlock(),
            new FeatureCardBlock(),
            new BentoCardsBlock(),
            new BentoCardBlock(),
            new SwishGalleryBlock(),
            new SwishFormBlock(),
            new SwishSocialsBlock(),
            new PricingBlock(),
            new PricingPlanBlock(),
            new SwishCvBlock(),
            new SwishLinksBlock(),
            new SwishCounterBlock(),
            new SwishCounterItemBlock(),
            new SwishSliderBlock(),
            new SwishSlideBlock(),
        ];
    }

    /**
     * Boot the plugin.
     *
     * @return void
     */
    public function boot(): void
    {
        // Load text domain.
        add_action('init', [ $this, 'loadTextDomain' ]);

        // Register block category.
        add_filter('block_categories_all', [ $this, 'registerBlockCategory' ]);

        // Register post types and taxonomies.
        add_action('init', [ $this, 'initPostTypes' ]);
        add_action('init', [ $this, 'initTaxonomies' ]);

        // Register blocks.
        add_action('init', [ $this, 'initBlocks' ]);

        // Register block patterns.
        add_action('init', [ $this, 'initBlockPatterns' ]);

        // Enqueue editor assets.
        add_action('enqueue_block_editor_assets', [ $this, 'enqueueEditorAssets' ]);

        // Enqueue design token defaults (frontend + editor iframe).
        add_action('enqueue_block_assets', [ $this, 'enqueueTokenStyles' ]);

        // AI block editing: REST routes and the AI capability.
        add_action('rest_api_init', [ $this, 'registerAIRoutes' ]);

        // AI settings screen (consent and provider configuration surface).
        if ($this->aiSettingsPage !== null) {
            add_action('admin_menu', [ $this->aiSettingsPage, 'registerMenu' ]);
            add_action('admin_init', [ $this->aiSettingsPage, 'registerPrivacyContent' ]);
            add_action('admin_post_' . AISettingsPage::ACTION, [ $this->aiSettingsPage, 'handleSave' ]);
        }
        add_filter('user_has_cap', [ $this, 'grantAICapability' ]);

        // Register WP-CLI commands.
        if (defined('WP_CLI') && \WP_CLI) {
            \WP_CLI::add_command(
                'swishfolio migrate-colors',
                \SwishfolioCore\Commands\MigrateColorsCommand::class
            );
        }
    }

    /**
     * Load plugin text domain.
     *
     * @return void
     */
    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'swishfolio-core',
            false,
            dirname(plugin_basename(SFCORE_PLUGIN_FILE)) . '/languages'
        );
    }

    /**
     * Register block category.
     *
     * @param array $categories Existing categories.
     * @return array
     */
    public function registerBlockCategory(array $categories): array
    {
        return array_merge(
            [
                [
                    'slug'  => 'swishfolio',
                    'title' => __('Swishfolio', 'swishfolio-core'),
                    'icon'  => 'portfolio',
                ],
            ],
            $categories
        );
    }

    /**
     * Initialize and register all post types.
     *
     * @return void
     */
    public function initPostTypes(): void
    {
        foreach ($this->postTypes as $postType) {
            $postType->register();
        }
    }

    /**
     * Initialize and register all taxonomies.
     *
     * @return void
     */
    public function initTaxonomies(): void
    {
        foreach ($this->taxonomies as $taxonomy) {
            $taxonomy->register();
        }
    }

    /**
     * Initialize and register all blocks.
     *
     * @return void
     */
    public function initBlocks(): void
    {
        foreach ($this->blocks as $block) {
            $block->register();
        }
    }

    /**
     * Register block patterns.
     *
     * Patterns are scoped to specific blocks via "blockTypes" so they surface
     * in that block's Advanced tab pattern list.
     *
     * @return void
     */
    public function initBlockPatterns(): void
    {
        if (! function_exists('register_block_pattern')) {
            return;
        }

        if (function_exists('register_block_pattern_category')) {
            register_block_pattern_category(
                'swishfolio',
                [ 'label' => __('Swishfolio', 'swishfolio-core') ]
            );
        }

        // Pattern markup lives in /patterns/*.html as static block-comment
        // markup. We load it via file_get_contents (local file, never a URL —
        // this is not a remote call) and register through the standard
        // pattern API. WordPress.org review guidelines forbid HEREDOC for
        // HTML because the linter cannot inspect what's inside.
        $features_showcase_file = SFCORE_PLUGIN_DIR . 'patterns/features-showcase.html';
        $features_showcase      = is_readable($features_showcase_file)
            ? file_get_contents($features_showcase_file)
            : '';

        if ('' !== $features_showcase) {
            register_block_pattern(
                'swishfolio-core/features-showcase',
                [
                    'title'      => __('Features: Showcase', 'swishfolio-core'),
                    'categories' => [ 'swishfolio' ],
                    'blockTypes' => [ 'swishfolio-core/features' ],
                    'content'    => $features_showcase,
                ]
            );
        }

        // Pass pattern screenshot URLs to the Features editor script so the
        // Advanced tab can show a preview image instead of a plain button.
        $handle = generate_block_asset_handle('swishfolio-core/features', 'editorScript');

        wp_localize_script(
            $handle,
            'sfcoreFeaturesData',
            [
                'patternPreviews' => [
                    'swishfolio-core/features-showcase' => SFCORE_PLUGIN_URL . 'assets/images/patterns/features-showcase.png',
                ],
            ]
        );
    }

    /**
     * Enqueue editor assets.
     *
     * @return void
     */
    public function enqueueEditorAssets(): void
    {
        if (file_exists(SFCORE_PLUGIN_DIR . 'assets/css/editor.css')) {
            wp_enqueue_style(
                'sfcore-editor',
                SFCORE_PLUGIN_URL . 'assets/css/editor.css',
                [],
                SFCORE_VERSION
            );
        }

        // Enqueue project sidebar script.
        $this->enqueueProjectSidebar();

        // AI block editing UI. Not post-type gated: AI editing applies to
        // every editable post type, unlike the project sidebar.
        $this->enqueueAiSidebar();
    }

    /**
     * Enqueue default values for the --sf-* design tokens.
     *
     * Theme style variations override these; without them, blocks using
     * `var(--sf-border)` etc. render unstyled when no variation defines
     * the token (default and sasha variations).
     *
     * Versioned by file mtime rather than SFCORE_VERSION: this single file
     * styles every block, so a token tweak deployed without a plugin header
     * bump would stay cached behind Cloudflare's immutable max-age.
     *
     * @return void
     */
    public function enqueueTokenStyles(): void
    {
        $path = SFCORE_PLUGIN_DIR . 'assets/css/tokens.css';

        wp_enqueue_style(
            'sfcore-tokens',
            SFCORE_PLUGIN_URL . 'assets/css/tokens.css',
            [],
            file_exists($path) ? (string) filemtime($path) : SFCORE_VERSION
        );
    }

    /**
     * Enqueue the project sidebar script for the block editor.
     *
     * @return void
     */
    private function enqueueProjectSidebar(): void
    {
        $screen = get_current_screen();

        // Only load on project post type editor.
        if (! $screen || $screen->post_type !== 'sfcore_project') {
            return;
        }

        $asset_file = SFCORE_PLUGIN_DIR . 'assets/js/project-sidebar.asset.php';

        if (! file_exists($asset_file)) {
            return;
        }

        $asset = require $asset_file;

        wp_enqueue_script(
            'sfcore-project-sidebar',
            SFCORE_PLUGIN_URL . 'assets/js/project-sidebar.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_set_script_translations(
            'sfcore-project-sidebar',
            'swishfolio-core',
            SFCORE_PLUGIN_DIR . 'languages'
        );
    }

    /**
     * Enqueue the AI block editing UI for the block editor.
     *
     * Unlike enqueueProjectSidebar() this is not post-type gated: AI editing
     * applies to every editable post type.
     *
     * @return void
     */
    private function enqueueAiSidebar(): void
    {
        if ($this->aiBlockRegistry === null || ! current_user_can(AIController::CAPABILITY)) {
            return;
        }

        $asset_file = SFCORE_PLUGIN_DIR . 'assets/js/ai-sidebar.asset.php';

        if (! file_exists($asset_file)) {
            return;
        }

        $asset = require $asset_file;

        wp_enqueue_script(
            'sfcore-ai-sidebar',
            SFCORE_PLUGIN_URL . 'assets/js/ai-sidebar.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_set_script_translations(
            'sfcore-ai-sidebar',
            'swishfolio-core',
            SFCORE_PLUGIN_DIR . 'languages'
        );

        // Must run before the bundle: the client reads window.sfcoreAiEditor.
        wp_add_inline_script(
            'sfcore-ai-sidebar',
            'window.sfcoreAiEditor = ' . wp_json_encode($this->aiCapabilityPayload()) . ';',
            'before'
        );

        wp_enqueue_style(
            'sfcore-ai-sidebar',
            SFCORE_PLUGIN_URL . 'assets/css/ai-sidebar.css',
            [],
            SFCORE_VERSION
        );
    }

    /**
     * The capability manifests the editor UI needs, flattened for JSON.
     *
     * Attribute ORDER IS SIGNIFICANT and is preserved by getFields(): the
     * client resolves update_block_content to the first richtext field, which
     * is the same rule AllowlistStage and ValueStage apply server-side.
     *
     * @return array
     */
    private function aiCapabilityPayload(): array
    {
        $capabilities = [];

        foreach ($this->aiBlockRegistry->all() as $blockName => $capability) {
            $fields = [];

            foreach ($capability->getFields() as $attribute => $field) {
                $spec = ['group' => $field->getGroup(), 'type' => $field->getType()];

                if ($field->getRole() !== null) {
                    $spec['role'] = $field->getRole();
                }
                if ($field->getEnum() !== []) {
                    $spec['enum'] = $field->getEnum();
                }
                if ($field->getTokenSet() !== null) {
                    $spec['tokenSet'] = $field->getTokenSet();
                }
                if ($field->getMin() !== null) {
                    $spec['min'] = $field->getMin();
                }
                if ($field->getMax() !== null) {
                    $spec['max'] = $field->getMax();
                }
                if ($field->getMaxLength() !== null) {
                    $spec['maxLength'] = $field->getMaxLength();
                }
                if ($field->getUnit() !== null) {
                    $spec['unit'] = $field->getUnit();
                }

                $fields[$attribute] = $spec;
            }

            $capabilities[$blockName] = [
                'tier'     => $capability->getTier(),
                'purpose'  => $capability->getPurpose(),
                'summary'  => $capability->getSummary(),
                'fields'   => $fields,
                'children' => [
                    'allowed' => $capability->getAllowedChildren(),
                    'min'     => $capability->getChildMin(),
                    'max'     => $capability->getChildMax(),
                ],
            ];
        }

        return [
            'maxActions'   => (int) apply_filters('swishfolio_ai_max_actions', 25),
            'capabilities' => $capabilities,
        ];
    }

    /**
     * Register the AI REST routes.
     *
     * @return void
     */
    public function registerAIRoutes(): void
    {
        if ($this->aiController !== null) {
            $this->aiController->registerRoutes();
        }

        if ($this->aiSettingsController !== null) {
            $this->aiSettingsController->registerRoutes();
        }

        if ($this->aiGenerationController !== null) {
            $this->aiGenerationController->registerRoutes();
        }
    }

    /**
     * Grant the AI capability to anyone who can already edit posts.
     *
     * Applied as a filter rather than written to roles, so site owners can
     * revoke it with a higher-priority filter and nothing is persisted.
     *
     * @param array $allcaps Capabilities for the current user.
     *
     * @return array
     */
    public function grantAICapability(array $allcaps): array
    {
        if (! empty($allcaps['edit_posts'])) {
            $allcaps[AIController::CAPABILITY] = true;
        }

        return $allcaps;
    }
}
