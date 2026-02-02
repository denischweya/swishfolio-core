<?php
/**
 * Project Type Taxonomy
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Taxonomies;

use SwishfolioCore\Abstracts\AbstractTaxonomy;

class ProjectTypeTaxonomy extends AbstractTaxonomy {
	/**
	 * Get the taxonomy slug.
	 *
	 * @return string
	 */
	public function getSlug(): string {
		return 'sfcore_project_type';
	}

	/**
	 * Get the post types this taxonomy applies to.
	 *
	 * @return array
	 */
	public function getPostTypes(): array {
		return array( 'sfcore_project' );
	}

	/**
	 * Get the taxonomy arguments.
	 *
	 * @return array
	 */
	public function getArgs(): array {
		return array(
			'labels'             => $this->getLabels(),
			'hierarchical'       => true,
			'public'             => true,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'show_in_nav_menus'  => true,
			'show_tagcloud'      => false,
			'show_in_rest'       => true,
			'rest_base'          => 'project-types',
			'rewrite'            => array( 'slug' => 'project-type' ),
		);
	}

	/**
	 * Get the taxonomy labels.
	 *
	 * @return array
	 */
	protected function getLabels(): array {
		return array(
			'name'              => _x( 'Project Types', 'taxonomy general name', 'swishfolio-core' ),
			'singular_name'     => _x( 'Project Type', 'taxonomy singular name', 'swishfolio-core' ),
			'search_items'      => __( 'Search Types', 'swishfolio-core' ),
			'all_items'         => __( 'All Types', 'swishfolio-core' ),
			'parent_item'       => __( 'Parent Type', 'swishfolio-core' ),
			'parent_item_colon' => __( 'Parent Type:', 'swishfolio-core' ),
			'edit_item'         => __( 'Edit Type', 'swishfolio-core' ),
			'update_item'       => __( 'Update Type', 'swishfolio-core' ),
			'add_new_item'      => __( 'Add New Type', 'swishfolio-core' ),
			'new_item_name'     => __( 'New Type Name', 'swishfolio-core' ),
			'menu_name'         => __( 'Types', 'swishfolio-core' ),
		);
	}
}
