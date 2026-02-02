<?php
/**
 * Project Category Taxonomy
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Taxonomies;

use SwishfolioCore\Abstracts\AbstractTaxonomy;

class ProjectCategoryTaxonomy extends AbstractTaxonomy {
	/**
	 * Get the taxonomy slug.
	 *
	 * @return string
	 */
	public function getSlug(): string {
		return 'sfcore_project_cat';
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
			'rest_base'          => 'project-categories',
			'rewrite'            => array( 'slug' => 'project-category' ),
		);
	}

	/**
	 * Get the taxonomy labels.
	 *
	 * @return array
	 */
	protected function getLabels(): array {
		return array(
			'name'              => _x( 'Project Categories', 'taxonomy general name', 'swishfolio-core' ),
			'singular_name'     => _x( 'Project Category', 'taxonomy singular name', 'swishfolio-core' ),
			'search_items'      => __( 'Search Categories', 'swishfolio-core' ),
			'all_items'         => __( 'All Categories', 'swishfolio-core' ),
			'parent_item'       => __( 'Parent Category', 'swishfolio-core' ),
			'parent_item_colon' => __( 'Parent Category:', 'swishfolio-core' ),
			'edit_item'         => __( 'Edit Category', 'swishfolio-core' ),
			'update_item'       => __( 'Update Category', 'swishfolio-core' ),
			'add_new_item'      => __( 'Add New Category', 'swishfolio-core' ),
			'new_item_name'     => __( 'New Category Name', 'swishfolio-core' ),
			'menu_name'         => __( 'Categories', 'swishfolio-core' ),
		);
	}
}
