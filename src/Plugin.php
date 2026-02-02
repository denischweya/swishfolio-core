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
use SwishfolioCore\Blocks\FaqBlock;
use SwishfolioCore\Blocks\SwishGalleryBlock;
use SwishfolioCore\Blocks\SwishFormBlock;
use SwishfolioCore\PostTypes\ProjectPostType;
use SwishfolioCore\PostTypes\TestimonialPostType;
use SwishfolioCore\Forms\FormEntryPostType;
use SwishfolioCore\Taxonomies\ProjectCategoryTaxonomy;
use SwishfolioCore\Taxonomies\ProjectTypeTaxonomy;
use SwishfolioCore\Admin\AdminMenu;
use SwishfolioCore\Forms\FormProcessor;
use SwishfolioCore\Email\EmailService;
use SwishfolioCore\Email\EspManager;

/**
 * Plugin orchestrator - boots and wires all components.
 */
class Plugin {
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
	 * Constructor.
	 */
	public function __construct() {
		$this->registerPostTypes();
		$this->registerTaxonomies();
		$this->registerBlocks();

		// Initialize admin menu.
		if ( is_admin() ) {
			$this->adminMenu = new AdminMenu();
		}

		// Initialize form processor.
		$this->formProcessor = new FormProcessor();

		// Initialize email service.
		$this->emailService = new EmailService();

		// Initialize ESP manager.
		$this->espManager = new EspManager();
	}

	/**
	 * Register all post types.
	 *
	 * @return void
	 */
	private function registerPostTypes(): void {
		$this->postTypes = [
			new ProjectPostType(),
			new TestimonialPostType(),
			new FormEntryPostType(),
		];
	}

	/**
	 * Register all taxonomies.
	 *
	 * @return void
	 */
	private function registerTaxonomies(): void {
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
	private function registerBlocks(): void {
		$this->blocks = [
			new CardBlock(),
			new HeroBlock(),
			new HeadingBlock(),
			new PortfolioBlock(),
			new LatestPostsBlock(),
			new TestimonialsBlock(),
			new FaqBlock(),
			new SwishGalleryBlock(),
			new SwishFormBlock(),
		];
	}

	/**
	 * Boot the plugin.
	 *
	 * @return void
	 */
	public function boot(): void {
		// Load text domain.
		add_action( 'init', [ $this, 'loadTextDomain' ] );

		// Register block category.
		add_filter( 'block_categories_all', [ $this, 'registerBlockCategory' ] );

		// Register post types and taxonomies.
		add_action( 'init', [ $this, 'initPostTypes' ] );
		add_action( 'init', [ $this, 'initTaxonomies' ] );

		// Register blocks.
		add_action( 'init', [ $this, 'initBlocks' ] );

		// Enqueue editor assets.
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueueEditorAssets' ] );
	}

	/**
	 * Load plugin text domain.
	 *
	 * @return void
	 */
	public function loadTextDomain(): void {
		load_plugin_textdomain(
			'swishfolio-core',
			false,
			dirname( plugin_basename( SFCORE_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Register block category.
	 *
	 * @param array $categories Existing categories.
	 * @return array
	 */
	public function registerBlockCategory( array $categories ): array {
		return array_merge(
			[
				[
					'slug'  => 'swishfolio',
					'title' => __( 'Swishfolio', 'swishfolio-core' ),
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
	public function initPostTypes(): void {
		foreach ( $this->postTypes as $postType ) {
			$postType->register();
		}
	}

	/**
	 * Initialize and register all taxonomies.
	 *
	 * @return void
	 */
	public function initTaxonomies(): void {
		foreach ( $this->taxonomies as $taxonomy ) {
			$taxonomy->register();
		}
	}

	/**
	 * Initialize and register all blocks.
	 *
	 * @return void
	 */
	public function initBlocks(): void {
		foreach ( $this->blocks as $block ) {
			$block->register();
		}
	}

	/**
	 * Enqueue editor assets.
	 *
	 * @return void
	 */
	public function enqueueEditorAssets(): void {
		if ( file_exists( SFCORE_PLUGIN_DIR . 'assets/css/editor.css' ) ) {
			wp_enqueue_style(
				'sfcore-editor',
				SFCORE_PLUGIN_URL . 'assets/css/editor.css',
				[],
				SFCORE_VERSION
			);
		}

		// Enqueue project sidebar script.
		$this->enqueueProjectSidebar();
	}

	/**
	 * Enqueue the project sidebar script for the block editor.
	 *
	 * @return void
	 */
	private function enqueueProjectSidebar(): void {
		$screen = get_current_screen();

		// Only load on project post type editor.
		if ( ! $screen || $screen->post_type !== 'sfcore_project' ) {
			return;
		}

		$asset_file = SFCORE_PLUGIN_DIR . 'assets/js/project-sidebar.asset.php';

		if ( ! file_exists( $asset_file ) ) {
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
}
