<?php
/**
 * ESP Manager
 *
 * Manages email service providers and handles subscriptions.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Email;

use SwishfolioCore\Email\Providers\MailchimpProvider;
use SwishfolioCore\Email\Providers\ConvertKitProvider;
use SwishfolioCore\Email\Providers\KlaviyoProvider;
use SwishfolioCore\Email\Providers\ActiveCampaignProvider;
use SwishfolioCore\Email\Providers\BrevoProvider;
use SwishfolioCore\Forms\FormEntryPostType;

class EspManager {
	/**
	 * Registered providers.
	 *
	 * @var array<string, EmailProviderInterface>
	 */
	private array $providers = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->registerProviders();

		// Hook into subscription form submission.
		add_action( 'swish_forms_subscription_submitted', [ $this, 'handleSubscription' ], 10, 5 );

		// AJAX handlers.
		add_action( 'wp_ajax_swish_forms_test_connection', [ $this, 'handleTestConnection' ] );
		add_action( 'wp_ajax_swish_forms_fetch_lists', [ $this, 'handleFetchLists' ] );
	}

	/**
	 * Register all providers.
	 *
	 * @return void
	 */
	private function registerProviders(): void {
		$this->providers = [
			'mailchimp'      => new MailchimpProvider(),
			'convertkit'     => new ConvertKitProvider(),
			'klaviyo'        => new KlaviyoProvider(),
			'activecampaign' => new ActiveCampaignProvider(),
			'brevo'          => new BrevoProvider(),
		];
	}

	/**
	 * Get a provider by slug.
	 *
	 * @param string $slug Provider slug.
	 * @return EmailProviderInterface|null
	 */
	public function getProvider( string $slug ): ?EmailProviderInterface {
		return $this->providers[ $slug ] ?? null;
	}

	/**
	 * Get all providers.
	 *
	 * @return array<string, EmailProviderInterface>
	 */
	public function getProviders(): array {
		return $this->providers;
	}

	/**
	 * Handle subscription form submission.
	 *
	 * @param int    $entryId     Entry ID.
	 * @param string $providerSlug Provider slug.
	 * @param string $listId      List ID.
	 * @param array  $fields      Form fields.
	 * @param string $email       Subscriber email.
	 * @return void
	 */
	public function handleSubscription( int $entryId, string $providerSlug, string $listId, array $fields, string $email ): void {
		if ( empty( $providerSlug ) || $providerSlug === 'none' || empty( $email ) ) {
			return;
		}

		$provider = $this->getProvider( $providerSlug );
		if ( ! $provider ) {
			return;
		}

		// Get credentials from settings.
		$settings = get_option( 'swish_forms_esp_settings', [] );
		$credentials = $settings[ $providerSlug ] ?? [];

		if ( empty( $credentials ) ) {
			return;
		}

		$provider->setCredentials( $credentials );

		// Use default list if not specified.
		if ( empty( $listId ) ) {
			$listId = $credentials['list_id'] ?? '';
		}

		if ( empty( $listId ) ) {
			return;
		}

		// Subscribe.
		$success = $provider->subscribe( $email, $fields, $listId );

		if ( $success ) {
			FormEntryPostType::markAsSynced( $entryId );
		}
	}

	/**
	 * Handle test connection AJAX.
	 *
	 * @return void
	 */
	public function handleTestConnection(): void {
		check_ajax_referer( 'swish_forms_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'swishfolio-core' ) ] );
		}

		$providerSlug = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
		$provider = $this->getProvider( $providerSlug );

		if ( ! $provider ) {
			wp_send_json_error( [ 'message' => __( 'Invalid provider.', 'swishfolio-core' ) ] );
		}

		// Get credentials from settings.
		$settings = get_option( 'swish_forms_esp_settings', [] );
		$credentials = $settings[ $providerSlug ] ?? [];

		if ( empty( $credentials ) ) {
			wp_send_json_error( [ 'message' => __( 'No credentials found. Please save your settings first.', 'swishfolio-core' ) ] );
		}

		$provider->setCredentials( $credentials );

		if ( $provider->testConnection() ) {
			wp_send_json_success( [ 'message' => __( 'Connection successful!', 'swishfolio-core' ) ] );
		} else {
			wp_send_json_error( [ 'message' => $provider->getLastError() ?: __( 'Connection failed.', 'swishfolio-core' ) ] );
		}
	}

	/**
	 * Handle fetch lists AJAX.
	 *
	 * @return void
	 */
	public function handleFetchLists(): void {
		check_ajax_referer( 'swish_forms_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'swishfolio-core' ) ] );
		}

		$providerSlug = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
		$provider = $this->getProvider( $providerSlug );

		if ( ! $provider ) {
			wp_send_json_error( [ 'message' => __( 'Invalid provider.', 'swishfolio-core' ) ] );
		}

		// Get credentials from settings.
		$settings = get_option( 'swish_forms_esp_settings', [] );
		$credentials = $settings[ $providerSlug ] ?? [];

		if ( empty( $credentials ) ) {
			wp_send_json_error( [ 'message' => __( 'No credentials found. Please save your settings first.', 'swishfolio-core' ) ] );
		}

		$provider->setCredentials( $credentials );

		$lists = $provider->getLists();

		if ( empty( $lists ) && $provider->getLastError() ) {
			wp_send_json_error( [ 'message' => $provider->getLastError() ] );
		}

		wp_send_json_success( [ 'lists' => $lists ] );
	}
}
