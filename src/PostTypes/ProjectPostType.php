<?php
/**
 * Project Post Type
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\PostTypes;

use SwishfolioCore\Abstracts\AbstractPostType;

class ProjectPostType extends AbstractPostType {
	/**
	 * Project status options.
	 */
	public const STATUS_COMPLETED   = 'completed';
	public const STATUS_IN_PROGRESS = 'in-progress';
	public const STATUS_PLANNED     = 'planned';
	public const STATUS_ON_HOLD     = 'on-hold';

	/**
	 * Get the post type slug.
	 *
	 * @return string
	 */
	public function getSlug(): string {
		return 'sfcore_project';
	}

	/**
	 * Get the post type labels.
	 *
	 * @return array
	 */
	public function getLabels(): array {
		return array(
			'name'                  => _x( 'Projects', 'Post type general name', 'swishfolio-core' ),
			'singular_name'         => _x( 'Project', 'Post type singular name', 'swishfolio-core' ),
			'menu_name'             => _x( 'Projects', 'Admin Menu text', 'swishfolio-core' ),
			'add_new'               => __( 'Add New', 'swishfolio-core' ),
			'add_new_item'          => __( 'Add New Project', 'swishfolio-core' ),
			'edit_item'             => __( 'Edit Project', 'swishfolio-core' ),
			'new_item'              => __( 'New Project', 'swishfolio-core' ),
			'view_item'             => __( 'View Project', 'swishfolio-core' ),
			'view_items'            => __( 'View Projects', 'swishfolio-core' ),
			'search_items'          => __( 'Search Projects', 'swishfolio-core' ),
			'not_found'             => __( 'No projects found.', 'swishfolio-core' ),
			'not_found_in_trash'    => __( 'No projects found in Trash.', 'swishfolio-core' ),
			'all_items'             => __( 'All Projects', 'swishfolio-core' ),
			'archives'              => __( 'Project Archives', 'swishfolio-core' ),
			'attributes'            => __( 'Project Attributes', 'swishfolio-core' ),
			'insert_into_item'      => __( 'Insert into project', 'swishfolio-core' ),
			'uploaded_to_this_item' => __( 'Uploaded to this project', 'swishfolio-core' ),
			'featured_image'        => _x( 'Project Image', 'Overrides the "Featured Image"', 'swishfolio-core' ),
			'set_featured_image'    => _x( 'Set project image', 'Overrides "Set featured image"', 'swishfolio-core' ),
			'remove_featured_image' => _x( 'Remove project image', 'Overrides "Remove featured image"', 'swishfolio-core' ),
			'use_featured_image'    => _x( 'Use as project image', 'Overrides "Use as featured image"', 'swishfolio-core' ),
		);
	}

	/**
	 * Get the post type arguments.
	 *
	 * @return array
	 */
	public function getArgs(): array {
		return array(
			'labels'             => $this->getLabels(),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'projects' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-portfolio',
			'supports'           => $this->getSupports(),
			'show_in_rest'       => true,
			'rest_base'          => 'projects',
		);
	}

	/**
	 * Register the post type and meta fields.
	 *
	 * @return void
	 */
	public function register(): void {
		parent::register();
		$this->registerMetaFields();
		$this->registerMetaBox();
	}

	/**
	 * Register the meta box for classic editor.
	 *
	 * @return void
	 */
	private function registerMetaBox(): void {
		add_action( 'add_meta_boxes', array( $this, 'addMetaBox' ) );
		add_action( 'save_post_' . $this->getSlug(), array( $this, 'saveMetaBox' ), 10, 2 );
	}

	/**
	 * Add the project details meta box.
	 *
	 * @return void
	 */
	public function addMetaBox(): void {
		add_meta_box(
			'sfcore_project_details',
			__( 'Project Details', 'swishfolio-core' ),
			array( $this, 'renderMetaBox' ),
			$this->getSlug(),
			'side',
			'high'
		);
	}

	/**
	 * Render the meta box content.
	 *
	 * @param \WP_Post $post The post object.
	 * @return void
	 */
	public function renderMetaBox( \WP_Post $post ): void {
		wp_nonce_field( 'sfcore_project_meta', 'sfcore_project_meta_nonce' );

		$completion_date = get_post_meta( $post->ID, '_sfcore_completion_date', true );
		$project_status  = get_post_meta( $post->ID, '_sfcore_project_status', true ) ?: self::STATUS_IN_PROGRESS;
		$skills          = get_post_meta( $post->ID, '_sfcore_skills', true ) ?: array();
		$project_url     = get_post_meta( $post->ID, '_sfcore_project_url', true );
		$client_name     = get_post_meta( $post->ID, '_sfcore_client_name', true );

		$skills_string = is_array( $skills ) ? implode( ', ', $skills ) : '';
		?>
		<style>
			.sfcore-meta-field { margin-bottom: 12px; }
			.sfcore-meta-field label { display: block; font-weight: 600; margin-bottom: 4px; }
			.sfcore-meta-field input,
			.sfcore-meta-field select,
			.sfcore-meta-field textarea { width: 100%; }
			.sfcore-meta-field .description { font-size: 12px; color: #666; margin-top: 4px; }
		</style>

		<div class="sfcore-meta-field">
			<label for="sfcore_project_status"><?php esc_html_e( 'Status', 'swishfolio-core' ); ?></label>
			<select name="sfcore_project_status" id="sfcore_project_status">
				<?php foreach ( self::getStatusOptions() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $project_status, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="sfcore-meta-field">
			<label for="sfcore_completion_date"><?php esc_html_e( 'Completion Date', 'swishfolio-core' ); ?></label>
			<input type="date" name="sfcore_completion_date" id="sfcore_completion_date" value="<?php echo esc_attr( $completion_date ); ?>" />
		</div>

		<div class="sfcore-meta-field">
			<label for="sfcore_client_name"><?php esc_html_e( 'Client Name', 'swishfolio-core' ); ?></label>
			<input type="text" name="sfcore_client_name" id="sfcore_client_name" value="<?php echo esc_attr( $client_name ); ?>" />
		</div>

		<div class="sfcore-meta-field">
			<label for="sfcore_project_url"><?php esc_html_e( 'Project URL', 'swishfolio-core' ); ?></label>
			<input type="url" name="sfcore_project_url" id="sfcore_project_url" value="<?php echo esc_attr( $project_url ); ?>" placeholder="https://" />
		</div>

		<div class="sfcore-meta-field">
			<label for="sfcore_skills"><?php esc_html_e( 'Skills / Technologies', 'swishfolio-core' ); ?></label>
			<textarea name="sfcore_skills" id="sfcore_skills" rows="3"><?php echo esc_textarea( $skills_string ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Comma-separated list (e.g., React, PHP, WordPress)', 'swishfolio-core' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Save the meta box data.
	 *
	 * @param int      $post_id The post ID.
	 * @param \WP_Post $post    The post object.
	 * @return void
	 */
	public function saveMetaBox( int $post_id, \WP_Post $post ): void {
		// Verify nonce.
		if ( ! isset( $_POST['sfcore_project_meta_nonce'] ) || ! wp_verify_nonce( $_POST['sfcore_project_meta_nonce'], 'sfcore_project_meta' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save completion date.
		if ( isset( $_POST['sfcore_completion_date'] ) ) {
			update_post_meta( $post_id, '_sfcore_completion_date', sanitize_text_field( $_POST['sfcore_completion_date'] ) );
		}

		// Save project status.
		if ( isset( $_POST['sfcore_project_status'] ) ) {
			update_post_meta( $post_id, '_sfcore_project_status', $this->sanitizeProjectStatus( $_POST['sfcore_project_status'] ) );
		}

		// Save client name.
		if ( isset( $_POST['sfcore_client_name'] ) ) {
			update_post_meta( $post_id, '_sfcore_client_name', sanitize_text_field( $_POST['sfcore_client_name'] ) );
		}

		// Save project URL.
		if ( isset( $_POST['sfcore_project_url'] ) ) {
			update_post_meta( $post_id, '_sfcore_project_url', esc_url_raw( $_POST['sfcore_project_url'] ) );
		}

		// Save skills (convert comma-separated string to array).
		if ( isset( $_POST['sfcore_skills'] ) ) {
			$skills_string = sanitize_text_field( $_POST['sfcore_skills'] );
			$skills_array  = array_map( 'trim', explode( ',', $skills_string ) );
			$skills_array  = array_filter( $skills_array ); // Remove empty values.
			update_post_meta( $post_id, '_sfcore_skills', $skills_array );
		}
	}

	/**
	 * Register custom meta fields.
	 *
	 * @return void
	 */
	private function registerMetaFields(): void {
		// Completion Date
		register_post_meta(
			$this->getSlug(),
			'_sfcore_completion_date',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		// Project Status
		register_post_meta(
			$this->getSlug(),
			'_sfcore_project_status',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => self::STATUS_IN_PROGRESS,
				'sanitize_callback' => array( $this, 'sanitizeProjectStatus' ),
				'auth_callback'     => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		// Skills (array of skill names)
		register_post_meta(
			$this->getSlug(),
			'_sfcore_skills',
			array(
				'show_in_rest'  => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'string',
						),
					),
				),
				'single'        => true,
				'type'          => 'array',
				'default'       => array(),
				'auth_callback' => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		// Project URL (external link to live project)
		register_post_meta(
			$this->getSlug(),
			'_sfcore_project_url',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		// Client Name
		register_post_meta(
			$this->getSlug(),
			'_sfcore_client_name',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Sanitize project status value.
	 *
	 * @param string $value The value to sanitize.
	 * @return string
	 */
	public function sanitizeProjectStatus( string $value ): string {
		$allowed = array(
			self::STATUS_COMPLETED,
			self::STATUS_IN_PROGRESS,
			self::STATUS_PLANNED,
			self::STATUS_ON_HOLD,
		);

		return in_array( $value, $allowed, true ) ? $value : self::STATUS_IN_PROGRESS;
	}

	/**
	 * Get available project statuses.
	 *
	 * @return array
	 */
	public static function getStatusOptions(): array {
		return array(
			self::STATUS_COMPLETED   => __( 'Completed', 'swishfolio-core' ),
			self::STATUS_IN_PROGRESS => __( 'In Progress', 'swishfolio-core' ),
			self::STATUS_PLANNED     => __( 'Planned', 'swishfolio-core' ),
			self::STATUS_ON_HOLD     => __( 'On Hold', 'swishfolio-core' ),
		);
	}
}
