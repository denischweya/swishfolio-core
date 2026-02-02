<?php
/**
 * Post Type Interface
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Contracts;

interface PostTypeInterface extends RegistrableInterface {
	/**
	 * Get the post type slug.
	 *
	 * @return string
	 */
	public function getSlug(): string;

	/**
	 * Get the post type arguments.
	 *
	 * @return array
	 */
	public function getArgs(): array;

	/**
	 * Get the post type labels.
	 *
	 * @return array
	 */
	public function getLabels(): array;
}
