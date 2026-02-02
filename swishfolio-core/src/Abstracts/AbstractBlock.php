<?php
/**
 * Abstract Block.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Abstracts;

use SwishfolioCore\Contracts\BlockInterface;
use SwishfolioCore\Contracts\RenderableInterface;

/**
 * Base class for all blocks.
 */
abstract class AbstractBlock implements BlockInterface {
	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	abstract public function getName(): string;

	/**
	 * Get the path to the block directory.
	 *
	 * @return string
	 */
	public function getPath(): string {
		return SFCORE_PLUGIN_DIR . 'blocks/' . $this->getName();
	}

	/**
	 * Register the block with WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		$args = $this->getBlockArgs();

		// Allow filtering of block args (OCP).
		$args = apply_filters( 'sfcore_block_' . $this->getName() . '_args', $args );

		register_block_type( $this->getPath(), $args );
	}

	/**
	 * Get block registration arguments.
	 *
	 * @return array
	 */
	protected function getBlockArgs(): array {
		$args = [];

		// If this block implements RenderableInterface, add the render callback.
		if ( $this instanceof RenderableInterface ) {
			$args['render_callback'] = [ $this, 'render' ];
		}

		return $args;
	}

	/**
	 * Get the block wrapper attributes.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $extra      Extra classes/attributes.
	 * @return string
	 */
	protected function getWrapperAttributes( array $attributes, array $extra = [] ): string {
		$classes = [ 'sfcore-block', 'sfcore-' . $this->getName() ];

		if ( ! empty( $extra['class'] ) ) {
			$classes[] = $extra['class'];
		}

		return get_block_wrapper_attributes( [
			'class' => implode( ' ', $classes ),
		] );
	}
}
