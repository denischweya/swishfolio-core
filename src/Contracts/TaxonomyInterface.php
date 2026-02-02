<?php
/**
 * Taxonomy Interface
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Contracts;

interface TaxonomyInterface extends RegistrableInterface {
	/**
	 * Get the taxonomy slug.
	 *
	 * @return string
	 */
	public function getSlug(): string;

	/**
	 * Get the post types this taxonomy applies to.
	 *
	 * @return array
	 */
	public function getPostTypes(): array;

	/**
	 * Get the taxonomy arguments.
	 *
	 * @return array
	 */
	public function getArgs(): array;
}
