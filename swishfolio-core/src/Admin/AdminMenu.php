<?php
/**
 * Admin Menu
 *
 * Registers the Swish Forms admin menu and pages.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Admin;

class AdminMenu {
	/**
	 * Menu slug.
	 */
	public const MENU_SLUG = 'swish-forms';

	/**
	 * Page instances.
	 *
	 * @var array
	 */
	private array $pages = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'registerMenus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAdminAssets' ] );
	}

	/**
	 * Register admin menus.
	 *
	 * @return void
	 */
	public function registerMenus(): void {
		// Main menu.
		add_menu_page(
			__( 'Swish Forms', 'swishfolio-core' ),
			__( 'Swish Forms', 'swishfolio-core' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'renderDashboardPage' ],
			'dashicons-feedback',
			30
		);

		// Dashboard submenu (same as main).
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'swishfolio-core' ),
			__( 'Dashboard', 'swishfolio-core' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'renderDashboardPage' ]
		);

		// Form Entries submenu.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Form Entries', 'swishfolio-core' ),
			__( 'Form Entries', 'swishfolio-core' ),
			'manage_options',
			self::MENU_SLUG . '-entries',
			[ $this, 'renderEntriesPage' ]
		);

		// Email Settings submenu.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Email Settings', 'swishfolio-core' ),
			__( 'Email Settings', 'swishfolio-core' ),
			'manage_options',
			self::MENU_SLUG . '-email',
			[ $this, 'renderEmailSettingsPage' ]
		);

		// Integrations submenu.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Integrations', 'swishfolio-core' ),
			__( 'Integrations', 'swishfolio-core' ),
			'manage_options',
			self::MENU_SLUG . '-integrations',
			[ $this, 'renderIntegrationsPage' ]
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueueAdminAssets( string $hook ): void {
		// Only load on Swish Forms pages.
		if ( strpos( $hook, self::MENU_SLUG ) === false ) {
			return;
		}

		// Admin styles.
		if ( file_exists( SFCORE_PLUGIN_DIR . 'assets/admin/admin.css' ) ) {
			wp_enqueue_style(
				'swish-forms-admin',
				SFCORE_PLUGIN_URL . 'assets/admin/admin.css',
				[],
				SFCORE_VERSION
			);
		}

		// Admin scripts.
		if ( file_exists( SFCORE_PLUGIN_DIR . 'assets/admin/admin.js' ) ) {
			wp_enqueue_script(
				'swish-forms-admin',
				SFCORE_PLUGIN_URL . 'assets/admin/admin.js',
				[ 'jquery' ],
				SFCORE_VERSION,
				true
			);

			wp_localize_script( 'swish-forms-admin', 'swishFormsAdmin', [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'swish_forms_admin' ),
				'i18n'    => [
					'testSuccess'    => __( 'Connection successful!', 'swishfolio-core' ),
					'testFailed'     => __( 'Connection failed.', 'swishfolio-core' ),
					'emailSent'      => __( 'Test email sent!', 'swishfolio-core' ),
					'emailFailed'    => __( 'Failed to send test email.', 'swishfolio-core' ),
					'confirmDelete'  => __( 'Are you sure you want to delete this entry?', 'swishfolio-core' ),
					'confirmBulk'    => __( 'Are you sure you want to delete the selected entries?', 'swishfolio-core' ),
				],
			] );
		}
	}

	/**
	 * Render the dashboard page.
	 *
	 * @return void
	 */
	public function renderDashboardPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'swishfolio-core' ) );
		}

		$this->loadPage( 'dashboard' );
	}

	/**
	 * Render the form entries page.
	 *
	 * @return void
	 */
	public function renderEntriesPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'swishfolio-core' ) );
		}

		$this->loadPage( 'entries' );
	}

	/**
	 * Render the email settings page.
	 *
	 * @return void
	 */
	public function renderEmailSettingsPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'swishfolio-core' ) );
		}

		$this->loadPage( 'email-settings' );
	}

	/**
	 * Render the integrations page.
	 *
	 * @return void
	 */
	public function renderIntegrationsPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'swishfolio-core' ) );
		}

		$this->loadPage( 'integrations' );
	}

	/**
	 * Load an admin page template.
	 *
	 * @param string $page Page template name.
	 * @return void
	 */
	private function loadPage( string $page ): void {
		$template = SFCORE_PLUGIN_DIR . 'templates/admin/' . $page . '.php';

		if ( file_exists( $template ) ) {
			include $template;
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'Page not found', 'swishfolio-core' ) . '</h1></div>';
		}
	}
}
