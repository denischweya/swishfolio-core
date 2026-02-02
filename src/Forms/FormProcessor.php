<?php
/**
 * Form Processor
 *
 * Handles form submissions via REST API.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Forms;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class FormProcessor {
	/**
	 * Validation service.
	 *
	 * @var ValidationService
	 */
	private ValidationService $validator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->validator = new ValidationService();

		add_action( 'rest_api_init', [ $this, 'registerRoutes' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'localizeScript' ] );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function registerRoutes(): void {
		register_rest_route( 'swishfolio/v1', '/forms/submit', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handleSubmission' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'formId'   => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'formType' => [
					'required'          => true,
					'type'              => 'string',
					'enum'              => [ 'contact', 'subscription' ],
					'sanitize_callback' => 'sanitize_text_field',
				],
				'fields'   => [
					'required' => true,
					'type'     => 'object',
				],
				'nonce'    => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );
	}

	/**
	 * Localize script for frontend.
	 *
	 * @return void
	 */
	public function localizeScript(): void {
		// Only localize if form view script is enqueued.
		wp_localize_script( 'swishfolio-core-swish-form-view-script', 'swishFormData', [
			'restUrl'   => rest_url(),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
		] );
	}

	/**
	 * Handle form submission.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handleSubmission( WP_REST_Request $request ) {
		$formId = $request->get_param( 'formId' );
		$formType = $request->get_param( 'formType' );
		$fields = $request->get_param( 'fields' );
		$nonce = $request->get_param( 'nonce' );

		// Verify nonce.
		if ( ! wp_verify_nonce( $nonce, 'swish_form_submit_' . $formId ) ) {
			return new WP_Error(
				'invalid_nonce',
				__( 'Security check failed. Please refresh the page and try again.', 'swishfolio-core' ),
				[ 'status' => 403 ]
			);
		}

		// Rate limiting check (simple implementation using transients).
		$ip = $this->getClientIp();
		$rateLimitKey = 'swish_form_rate_' . md5( $ip );
		$submissions = (int) get_transient( $rateLimitKey );

		if ( $submissions >= 10 ) {
			return new WP_Error(
				'rate_limit',
				__( 'Too many submissions. Please try again later.', 'swishfolio-core' ),
				[ 'status' => 429 ]
			);
		}

		// Get form field definitions from block attributes (stored in post content).
		$fieldDefinitions = $this->getFieldDefinitions( $formId );

		// Validate fields.
		if ( ! $this->validator->validate( $fieldDefinitions, $fields ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Please correct the errors below.', 'swishfolio-core' ),
				'errors'  => $this->validator->getErrors(),
			], 400 );
		}

		// Sanitize fields.
		$sanitizedFields = $this->validator->sanitize( $fieldDefinitions, $fields );

		// Find email field.
		$email = $this->findEmailField( $fieldDefinitions, $sanitizedFields );

		// Create entry.
		$entryId = FormEntryPostType::createEntry( [
			'form_id'   => $formId,
			'form_type' => $formType,
			'fields'    => $sanitizedFields,
			'email'     => $email,
			'ip'        => $ip,
		] );

		if ( ! $entryId ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to save submission. Please try again.', 'swishfolio-core' ),
				[ 'status' => 500 ]
			);
		}

		// Update rate limit.
		set_transient( $rateLimitKey, $submissions + 1, HOUR_IN_SECONDS );

		// Process based on form type.
		if ( $formType === 'contact' ) {
			$this->processContactForm( $entryId, $formId, $sanitizedFields, $email );
		} else {
			$this->processSubscriptionForm( $entryId, $formId, $sanitizedFields, $email );
		}

		return new WP_REST_Response( [
			'success' => true,
			'message' => __( 'Form submitted successfully.', 'swishfolio-core' ),
			'entryId' => $entryId,
		], 200 );
	}

	/**
	 * Process contact form submission.
	 *
	 * @param int    $entryId Entry ID.
	 * @param string $formId  Form ID.
	 * @param array  $fields  Sanitized fields.
	 * @param string $email   Submitter email.
	 * @return void
	 */
	private function processContactForm( int $entryId, string $formId, array $fields, string $email ): void {
		// Get block attributes for email settings.
		$attributes = $this->getBlockAttributes( $formId );
		$recipientEmail = $attributes['recipientEmail'] ?? '';
		$emailSubject = $attributes['emailSubject'] ?? __( 'New Form Submission', 'swishfolio-core' );

		// Use default from settings if not set.
		if ( empty( $recipientEmail ) ) {
			$settings = get_option( 'swish_forms_email_settings', [] );
			$recipientEmail = $settings['from_email'] ?? get_option( 'admin_email' );
		}

		// Dispatch email (will be handled by EmailService in Phase 5).
		do_action( 'swish_forms_contact_submitted', $entryId, $recipientEmail, $emailSubject, $fields, $email );
	}

	/**
	 * Process subscription form submission.
	 *
	 * @param int    $entryId Entry ID.
	 * @param string $formId  Form ID.
	 * @param array  $fields  Sanitized fields.
	 * @param string $email   Subscriber email.
	 * @return void
	 */
	private function processSubscriptionForm( int $entryId, string $formId, array $fields, string $email ): void {
		// Get block attributes for ESP settings.
		$attributes = $this->getBlockAttributes( $formId );
		$espProvider = $attributes['espProvider'] ?? 'none';
		$espListId = $attributes['espListId'] ?? '';

		// Use default from settings if not set.
		if ( $espProvider === 'none' ) {
			$settings = get_option( 'swish_forms_esp_settings', [] );
			$espProvider = $settings['active_provider'] ?? 'none';
		}

		if ( empty( $espListId ) && $espProvider !== 'none' ) {
			$settings = get_option( 'swish_forms_esp_settings', [] );
			$espListId = $settings[ $espProvider ]['list_id'] ?? '';
		}

		// Dispatch to ESP (will be handled by ESP providers in Phase 6).
		do_action( 'swish_forms_subscription_submitted', $entryId, $espProvider, $espListId, $fields, $email );
	}

	/**
	 * Get field definitions from block.
	 *
	 * @param string $formId Form ID.
	 * @return array
	 */
	private function getFieldDefinitions( string $formId ): array {
		$attributes = $this->getBlockAttributes( $formId );
		return $attributes['fields'] ?? [];
	}

	/**
	 * Get block attributes by form ID.
	 *
	 * @param string $formId Form ID.
	 * @return array
	 */
	private function getBlockAttributes( string $formId ): array {
		// Search all posts for the block with this form ID.
		global $wpdb;

		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts}
				WHERE post_content LIKE %s
				AND post_status IN ('publish', 'draft')
				LIMIT 1",
				'%' . $wpdb->esc_like( $formId ) . '%'
			)
		);

		if ( empty( $posts ) ) {
			return [];
		}

		$blocks = parse_blocks( $posts[0]->post_content );
		return $this->findBlockAttributes( $blocks, $formId );
	}

	/**
	 * Recursively find block attributes by form ID.
	 *
	 * @param array  $blocks Blocks array.
	 * @param string $formId Form ID to find.
	 * @return array
	 */
	private function findBlockAttributes( array $blocks, string $formId ): array {
		foreach ( $blocks as $block ) {
			if ( $block['blockName'] === 'swishfolio-core/swish-form' ) {
				if ( isset( $block['attrs']['formId'] ) && $block['attrs']['formId'] === $formId ) {
					return $block['attrs'];
				}
			}

			// Check inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = $this->findBlockAttributes( $block['innerBlocks'], $formId );
				if ( ! empty( $found ) ) {
					return $found;
				}
			}
		}

		return [];
	}

	/**
	 * Find email field from submission.
	 *
	 * @param array $definitions Field definitions.
	 * @param array $values      Field values.
	 * @return string
	 */
	private function findEmailField( array $definitions, array $values ): string {
		foreach ( $definitions as $field ) {
			if ( ( $field['type'] ?? '' ) === 'email' ) {
				$fieldId = $field['id'] ?? '';
				if ( isset( $values[ $fieldId ] ) && is_email( $values[ $fieldId ] ) ) {
					return $values[ $fieldId ];
				}
			}
		}
		return '';
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	private function getClientIp(): string {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			// Take first IP if comma-separated.
			$ip = explode( ',', $ip )[0];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return trim( $ip );
	}
}
