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
use SwishfolioCore\AI\Credentials\WordPressCredentialStore;
use SwishfolioCore\REST\AIController;
use SwishfolioCore\REST\SettingsController;
use SwishfolioCore\REST\ToolsController;
use SwishfolioCore\REST\AgentController;
use SwishfolioCore\REST\OnboardingController;
use SwishfolioCore\REST\CustomiseController;
use SwishfolioCore\REST\KitController;
use SwishfolioCore\REST\KitBuildController;
use SwishfolioCore\Admin\AuditLogPage;
use SwishfolioCore\Tools\AbilitiesBridge;
use SwishfolioCore\Jobs\AgentJobRunner;
use SwishfolioCore\Jobs\TransientJobStore;
use SwishfolioCore\Jobs\BackgroundJobs;
use SwishfolioCore\Tools\Journal\JournalTable;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\Journal\WpdbJournalRepository;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;
use SwishfolioCore\REST\ConnectionsController;
use SwishfolioCore\REST\GenerationController;
use SwishfolioCore\REST\BuilderController;
use SwishfolioCore\AI\Generation\PatternLibrary;
use SwishfolioCore\Admin\SwishAiMenu;

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
     * Floating prompt bar backend.
     *
     * @var BuilderController|null
     */
    private ?BuilderController $aiBuilderController = null;

    /**
     * Tool pipeline: every server-side site operation goes through it.
     *
     * @var ToolRunner|null
     */
    private ?ToolRunner $toolRunner = null;

    /**
     * Tool REST controller.
     *
     * @var ToolsController|null
     */
    private ?ToolsController $toolsController = null;

    /**
     * Audit log screen.
     *
     * @var AuditLogPage|null
     */
    private ?AuditLogPage $auditLogPage = null;

    /**
     * Conversational site management REST controller.
     *
     * @var AgentController|null
     */
    private ?AgentController $agentController = null;

    /**
     * Background runner for agent turns.
     *
     * @var AgentJobRunner|null
     */
    private ?AgentJobRunner $agentJobRunner = null;

    /**
     * Background runner for long AI requests (builder bar, plans, generation).
     *
     * @var BackgroundJobs|null
     */
    private ?BackgroundJobs $backgroundJobs = null;

    /**
     * CV and profile import REST controller.
     *
     * @var OnboardingController|null
     */
    private ?OnboardingController $onboardingController = null;

    /**
     * Onboarding template customisation and image placement.
     *
     * @var CustomiseController|null
     */
    private ?CustomiseController $customiseController = null;

    /**
     * Onboarding: match a brief to a site template.
     *
     * @var KitController|null
     */
    private ?KitController $kitController = null;

    /**
     * @var KitBuildController|null
     */
    private ?KitBuildController $kitBuildController = null;

    /**
     * Onboarding: a custom design system instead of a template's look.
     *
     * @var \SwishfolioCore\REST\CustomDesignController|null
     */
    private ?\SwishfolioCore\REST\CustomDesignController $customDesignController = null;

    /**
     * Abilities API bridge. Registers the tools; exposes none of them.
     *
     * @var AbilitiesBridge|null
     */
    private ?AbilitiesBridge $abilitiesBridge = null;

    /**
     * AI connections REST controller.
     *
     * @var ConnectionsController|null
     */
    private ?ConnectionsController $aiConnectionsController = null;

    /**
     * Swish AI admin menu.
     *
     * @var SwishAiMenu|null
     */
    private ?SwishAiMenu $swishAiMenu = null;

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

        $aiSettings  = new AISettings(new WordPressCredentialStore(new EncryptionService()));
        $aiProviders = new ProviderRegistry($aiSettings);
        $aiLogger    = new UsageLogger();

        $this->aiController         = new AIController(
            $this->aiBlockRegistry,
            $aiSettings,
            $aiProviders,
            $aiLogger
        );
        $this->aiSettingsController = new SettingsController($aiSettings, $aiProviders);
        $this->aiConnectionsController = new ConnectionsController($aiSettings, $aiProviders);

        // Long AI requests (builder bar, site plans) run as background jobs
        // that the browser polls; the agent's turns share the same store.
        $jobStore             = new TransientJobStore();
        $this->backgroundJobs = new BackgroundJobs($jobStore);

        $this->aiBuilderController = new BuilderController($aiSettings, $aiProviders, $aiLogger, $this->backgroundJobs);

        // One kit per template site, written by seed/extract-patterns.php.
        $this->aiPatternLibrary = PatternLibrary::shipped();

        $this->aiGenerationController = new GenerationController(
            $this->aiBlockRegistry,
            $this->aiPatternLibrary,
            $aiSettings,
            $aiProviders,
            $aiLogger,
            $this->backgroundJobs
        );

        // The tool layer. One registry, one runner, one journal: the REST
        // routes, the audit screen's undo and the Abilities bridge all share
        // them, so there is no second path that could write without a journal
        // row or skip the confirmation gate.
        $journalRepository = new WpdbJournalRepository();
        $mutationJournal   = new MutationJournal($journalRepository);

        $this->toolRunner = new ToolRunner(
            ToolRegistry::withDefaultTools($mutationJournal, $this->aiBlockRegistry),
            $mutationJournal
        );

        $this->toolsController = new ToolsController($this->toolRunner, $journalRepository, $aiSettings);
        // The loop is built lazily: it needs a configured provider, and asking
        // for one at boot would throw on every request of a site that has not
        // connected an account yet.
        $toolRunner           = $this->toolRunner;
        $this->agentJobRunner = new AgentJobRunner(
            $jobStore,
            static function () use ($toolRunner, $aiSettings, $aiProviders) {
                return new \SwishfolioCore\AI\Agent\AgentLoop(
                    $aiProviders->configured(),
                    $toolRunner,
                    new \SwishfolioCore\AI\Prompt\PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'),
                    // Same rule as the synchronous /ai/agent route.
                    (new \SwishfolioCore\AI\ModelRouter($aiSettings))->modelFor(\SwishfolioCore\AI\ModelRouter::KIND_AGENT)
                );
            }
        );

        $this->onboardingController = new OnboardingController($aiSettings, $aiProviders, $aiLogger);
        $this->customiseController  = new CustomiseController($aiSettings, $aiProviders, $aiLogger, $this->backgroundJobs, $this->aiBuilderController);
        $this->kitController        = new KitController($aiSettings, $aiProviders, $aiLogger);
        $this->kitBuildController   = new KitBuildController($aiSettings, $aiProviders, $aiLogger, $this->aiPatternLibrary, $this->backgroundJobs);
        // Credit line for imported stock photos (Unsplash / Pexels terms).
        \SwishfolioCore\AI\Photos\PhotoCredits::register();
        $this->customDesignController = new \SwishfolioCore\REST\CustomDesignController($aiSettings, $aiProviders, $aiLogger, $this->aiPatternLibrary, $this->backgroundJobs);

        $this->agentController = new AgentController(
            $this->toolRunner,
            $aiSettings,
            $aiProviders,
            $aiLogger,
            $this->agentJobRunner,
            $jobStore
        );
        $this->abilitiesBridge = new AbilitiesBridge($this->toolRunner->registry(), $this->toolRunner, $aiSettings);

        if (is_admin()) {
            $this->swishAiMenu  = new SwishAiMenu($aiSettings, $aiProviders, $aiLogger);
            $this->auditLogPage = new AuditLogPage($journalRepository, $this->toolRunner);
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
        // Front-end login and registration forms (main site /login/ and /register/).
        (new \SwishfolioCore\Auth\AuthHandler())->boot();

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

        // Swish AI: its own top-level menu, not a Swish Forms submenu — AI is a
        // product surface in its own right.
        if ($this->swishAiMenu !== null) {
            add_action('admin_menu', [ $this->swishAiMenu, 'registerMenu' ]);
            add_action('admin_enqueue_scripts', [ $this->swishAiMenu, 'enqueueAssets' ]);
            add_action('admin_init', [ $this->swishAiMenu, 'registerPrivacyContent' ]);

            add_action('admin_post_' . SwishAiMenu::ACTION_CONNECT, [ $this->swishAiMenu, 'handleConnect' ]);
            add_action('admin_post_' . SwishAiMenu::ACTION_TEST, [ $this->swishAiMenu, 'handleTest' ]);
            add_action('admin_post_' . SwishAiMenu::ACTION_DISCONNECT, [ $this->swishAiMenu, 'handleDisconnect' ]);
            add_action('admin_post_' . SwishAiMenu::ACTION_ACTIVATE, [ $this->swishAiMenu, 'handleActivate' ]);
            add_action('admin_post_' . SwishAiMenu::ACTION_TOGGLE, [ $this->swishAiMenu, 'handleToggle' ]);
            add_action('admin_post_' . SwishAiMenu::ACTION_BRAND, [ $this->swishAiMenu, 'handleBrand' ]);
        }
        add_filter('user_has_cap', [ $this, 'grantAICapability' ]);

        // Registers every tool with the Abilities API where it exists, and
        // exposes none of them. On the 6.7 floor this is a no-op.
        if ($this->abilitiesBridge !== null) {
            $this->abilitiesBridge->boot();
        }

        if ($this->auditLogPage !== null) {
            $this->auditLogPage->boot();
        }

        if ($this->agentJobRunner !== null) {
            $this->agentJobRunner->boot();
        }

        if ($this->backgroundJobs !== null) {
            $this->backgroundJobs->boot();
        }

        // The journal table is created on activation, but an update from a
        // version without it never runs activation, so the version-guarded
        // installer runs on admin_init too. It is one get_option when current.
        add_action('admin_init', [ self::class, 'installJournalTable' ]);
        add_action('admin_init', [ self::class, 'scheduleJournalPrune' ]);

        // Core only drops its own tables when a site is deleted; without this
        // the journal (AI inputs and prior content) outlives the tenant.
        add_filter('wpmu_drop_tables', [ self::class, 'dropJournalTable' ], 10, 2);

        // Register WP-CLI commands.
        if (defined('WP_CLI') && \WP_CLI) {
            \WP_CLI::add_command(
                'swishfolio migrate-colors',
                \SwishfolioCore\Commands\MigrateColorsCommand::class
            );

            \WP_CLI::add_command(
                'swishfolio ai-test',
                \SwishfolioCore\Commands\AiTestCommand::class
            );

            \WP_CLI::add_command(
                'swishfolio migrate-credentials',
                \SwishfolioCore\Commands\MigrateCredentialsCommand::class
            );

            \WP_CLI::add_command(
                'swishfolio visual-review',
                \SwishfolioCore\Commands\VisualReviewCommand::class
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
            SFCORE_VERSION . '.' . filemtime(SFCORE_PLUGIN_DIR . 'assets/css/ai-sidebar.css')
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

        if ($this->aiConnectionsController !== null) {
            $this->aiConnectionsController->registerRoutes();
        }

        if ($this->aiGenerationController !== null) {
            $this->aiGenerationController->registerRoutes();
        }

        if ($this->aiBuilderController !== null) {
            $this->aiBuilderController->registerRoutes();
        }

        if ($this->toolsController !== null) {
            $this->toolsController->registerRoutes();
        }

        if ($this->agentController !== null) {
            $this->agentController->registerRoutes();
        }

        if ($this->customiseController !== null) {
            $this->customiseController->registerRoutes();
        }

        if ($this->onboardingController !== null) {
            $this->onboardingController->registerRoutes();
        }

        if ($this->kitController !== null) {
            $this->kitController->registerRoutes();
        }

        if ($this->kitBuildController !== null) {
            $this->kitBuildController->registerRoutes();
        }

        if ($this->customDesignController !== null) {
            $this->customDesignController->registerRoutes();
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
    /**
     * Creates the AI journal table when the stored schema version is behind.
     *
     * @return void
     */
    public static function installJournalTable(): void
    {
        JournalTable::install();
    }

    /**
     * @param string[] $tables Tables core will drop for the deleted site.
     * @param int      $siteId Site being deleted.
     *
     * @return string[]
     */
    public static function dropJournalTable($tables, $siteId)
    {
        global $wpdb;

        $tables[] = $wpdb->get_blog_prefix((int) $siteId) . 'swishfolio_ai_journal';

        return $tables;
    }

    /**
     * Schedules the daily expiry of aged journal rows.
     *
     * @return void
     */
    public static function scheduleJournalPrune(): void
    {
        if (! wp_next_scheduled(AuditLogPage::PRUNE_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', AuditLogPage::PRUNE_HOOK);
        }
    }

    public function grantAICapability(array $allcaps): array
    {
        if (! empty($allcaps['edit_posts'])) {
            $allcaps[AIController::CAPABILITY] = true;
        }

        return $allcaps;
    }
}
