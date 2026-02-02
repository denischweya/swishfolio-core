<?php
/**
 * Form Entry Post Type
 *
 * Custom post type for storing form submissions.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Forms;

use SwishfolioCore\Abstracts\AbstractPostType;

class FormEntryPostType extends AbstractPostType {
	/**
	 * Post type slug.
	 */
	public const SLUG = 'swish_form_entry';

	/**
	 * Meta keys.
	 */
	public const META_FORM_ID = '_swish_form_id';
	public const META_FORM_TYPE = '_swish_form_type';
	public const META_SUBMISSION_IP = '_swish_submission_ip';
	public const META_SUBMISSION_EMAIL = '_swish_submission_email';
	public const META_ESP_SYNCED = '_swish_esp_synced';
	public const META_FORM_DATA = '_swish_form_data';

	/**
	 * Get the post type slug.
	 *
	 * @return string
	 */
	public function getSlug(): string {
		return self::SLUG;
	}

	/**
	 * Get post type labels.
	 *
	 * @return array
	 */
	public function getLabels(): array {
		return [
			'name'                  => _x( 'Form Entries', 'Post type general name', 'swishfolio-core' ),
			'singular_name'         => _x( 'Form Entry', 'Post type singular name', 'swishfolio-core' ),
			'menu_name'             => _x( 'Form Entries', 'Admin Menu text', 'swishfolio-core' ),
			'name_admin_bar'        => _x( 'Form Entry', 'Add New on Toolbar', 'swishfolio-core' ),
			'add_new'               => __( 'Add New', 'swishfolio-core' ),
			'add_new_item'          => __( 'Add New Entry', 'swishfolio-core' ),
			'new_item'              => __( 'New Entry', 'swishfolio-core' ),
			'edit_item'             => __( 'View Entry', 'swishfolio-core' ),
			'view_item'             => __( 'View Entry', 'swishfolio-core' ),
			'all_items'             => __( 'All Entries', 'swishfolio-core' ),
			'search_items'          => __( 'Search Entries', 'swishfolio-core' ),
			'not_found'             => __( 'No entries found.', 'swishfolio-core' ),
			'not_found_in_trash'    => __( 'No entries found in Trash.', 'swishfolio-core' ),
			'archives'              => __( 'Entry Archives', 'swishfolio-core' ),
			'filter_items_list'     => __( 'Filter entries list', 'swishfolio-core' ),
			'items_list_navigation' => __( 'Entries list navigation', 'swishfolio-core' ),
			'items_list'            => __( 'Entries list', 'swishfolio-core' ),
		];
	}

	/**
	 * Get post type arguments.
	 *
	 * @return array
	 */
	public function getArgs(): array {
		return [
			'labels'              => $this->getLabels(),
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => false, // We'll use custom admin pages.
			'show_in_menu'        => false,
			'query_var'           => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'capabilities'        => [
				'create_posts' => 'do_not_allow',
			],
			'map_meta_cap'        => true,
			'has_archive'         => false,
			'hierarchical'        => false,
			'supports'            => [ 'title' ],
			'show_in_rest'        => false,
		];
	}

	/**
	 * Create a new form entry.
	 *
	 * @param array $data Entry data.
	 * @return int|false Post ID on success, false on failure.
	 */
	public static function createEntry( array $data ) {
		$formId = $data['form_id'] ?? '';
		$formType = $data['form_type'] ?? 'contact';
		$fields = $data['fields'] ?? [];
		$email = $data['email'] ?? '';
		$ip = $data['ip'] ?? '';

		// Create title from submission.
		$title = ! empty( $email ) ? $email : sprintf(
			/* translators: %s: date/time */
			__( 'Submission on %s', 'swishfolio-core' ),
			current_time( 'mysql' )
		);

		$postId = wp_insert_post( [
			'post_type'    => self::SLUG,
			'post_title'   => $title,
			'post_content' => wp_json_encode( $fields ),
			'post_status'  => 'publish',
		] );

		if ( is_wp_error( $postId ) || ! $postId ) {
			return false;
		}

		// Save meta data.
		update_post_meta( $postId, self::META_FORM_ID, sanitize_text_field( $formId ) );
		update_post_meta( $postId, self::META_FORM_TYPE, sanitize_text_field( $formType ) );
		update_post_meta( $postId, self::META_SUBMISSION_EMAIL, sanitize_email( $email ) );
		update_post_meta( $postId, self::META_SUBMISSION_IP, self::hashIp( $ip ) );
		update_post_meta( $postId, self::META_FORM_DATA, $fields );
		update_post_meta( $postId, self::META_ESP_SYNCED, false );

		return $postId;
	}

	/**
	 * Hash an IP address for privacy.
	 *
	 * @param string $ip IP address.
	 * @return string Hashed IP.
	 */
	private static function hashIp( string $ip ): string {
		if ( empty( $ip ) ) {
			return '';
		}

		$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'swish-ip-salt';
		return hash( 'sha256', $ip . $salt );
	}

	/**
	 * Get entries for a specific form.
	 *
	 * @param string $formId Form ID.
	 * @param array  $args   Additional query args.
	 * @return array
	 */
	public static function getEntries( string $formId = '', array $args = [] ): array {
		$queryArgs = array_merge( [
			'post_type'      => self::SLUG,
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'orderby'        => 'date',
			'order'          => 'DESC',
		], $args );

		if ( ! empty( $formId ) ) {
			$queryArgs['meta_query'] = [
				[
					'key'   => self::META_FORM_ID,
					'value' => $formId,
				],
			];
		}

		$query = new \WP_Query( $queryArgs );
		return $query->posts;
	}

	/**
	 * Get entry count.
	 *
	 * @param string $formId Optional form ID to filter.
	 * @return int
	 */
	public static function getEntryCount( string $formId = '' ): int {
		$args = [
			'post_type'      => self::SLUG,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		];

		if ( ! empty( $formId ) ) {
			$args['meta_query'] = [
				[
					'key'   => self::META_FORM_ID,
					'value' => $formId,
				],
			];
		}

		$query = new \WP_Query( $args );
		return $query->found_posts;
	}

	/**
	 * Mark entry as synced to ESP.
	 *
	 * @param int $entryId Entry post ID.
	 * @return void
	 */
	public static function markAsSynced( int $entryId ): void {
		update_post_meta( $entryId, self::META_ESP_SYNCED, true );
	}
}
