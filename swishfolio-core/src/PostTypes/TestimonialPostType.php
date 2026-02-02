<?php
/**
 * Testimonial Post Type
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\PostTypes;

use SwishfolioCore\Abstracts\AbstractPostType;

class TestimonialPostType extends AbstractPostType {
	/**
	 * Get the post type slug.
	 *
	 * @return string
	 */
	public function getSlug(): string {
		return 'sfcore_testimonial';
	}

	/**
	 * Get the post type labels.
	 *
	 * @return array
	 */
	public function getLabels(): array {
		return array(
			'name'                  => _x( 'Testimonials', 'Post type general name', 'swishfolio-core' ),
			'singular_name'         => _x( 'Testimonial', 'Post type singular name', 'swishfolio-core' ),
			'menu_name'             => _x( 'Testimonials', 'Admin Menu text', 'swishfolio-core' ),
			'add_new'               => __( 'Add New', 'swishfolio-core' ),
			'add_new_item'          => __( 'Add New Testimonial', 'swishfolio-core' ),
			'edit_item'             => __( 'Edit Testimonial', 'swishfolio-core' ),
			'new_item'              => __( 'New Testimonial', 'swishfolio-core' ),
			'view_item'             => __( 'View Testimonial', 'swishfolio-core' ),
			'view_items'            => __( 'View Testimonials', 'swishfolio-core' ),
			'search_items'          => __( 'Search Testimonials', 'swishfolio-core' ),
			'not_found'             => __( 'No testimonials found.', 'swishfolio-core' ),
			'not_found_in_trash'    => __( 'No testimonials found in Trash.', 'swishfolio-core' ),
			'all_items'             => __( 'All Testimonials', 'swishfolio-core' ),
			'archives'              => __( 'Testimonial Archives', 'swishfolio-core' ),
			'attributes'            => __( 'Testimonial Attributes', 'swishfolio-core' ),
			'featured_image'        => _x( 'Author Photo', 'Overrides the "Featured Image"', 'swishfolio-core' ),
			'set_featured_image'    => _x( 'Set author photo', 'Overrides "Set featured image"', 'swishfolio-core' ),
			'remove_featured_image' => _x( 'Remove author photo', 'Overrides "Remove featured image"', 'swishfolio-core' ),
			'use_featured_image'    => _x( 'Use as author photo', 'Overrides "Use as featured image"', 'swishfolio-core' ),
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
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 6,
			'menu_icon'          => 'dashicons-format-quote',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'show_in_rest'       => true,
			'rest_base'          => 'testimonials',
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
	}

	/**
	 * Register custom meta fields.
	 *
	 * @return void
	 */
	private function registerMetaFields(): void {
		register_post_meta(
			$this->getSlug(),
			'_sfcore_author_name',
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			$this->getSlug(),
			'_sfcore_author_title',
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			$this->getSlug(),
			'_sfcore_company',
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			$this->getSlug(),
			'_sfcore_rating',
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'integer',
				'default'       => 5,
				'auth_callback' => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
