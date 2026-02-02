<?php
/**
 * Abstract Post Type
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Abstracts;

use SwishfolioCore\Contracts\PostTypeInterface;

abstract class AbstractPostType implements PostTypeInterface {
	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public function register(): void {
		$args = apply_filters( "sfcore_{$this->getSlug()}_args", $this->getArgs() );
		register_post_type( $this->getSlug(), $args );
	}

	/**
	 * Get default supports.
	 *
	 * @return array
	 */
	protected function getSupports(): array {
		return array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' );
	}
}
