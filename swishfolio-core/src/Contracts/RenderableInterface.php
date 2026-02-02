<?php
/**
 * Renderable Interface.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Contracts;

/**
 * Interface for blocks with server-side rendering.
 */
interface RenderableInterface {
	/**
	 * Render the block.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 * @return string Rendered HTML.
	 */
	public function render( array $attributes, string $content ): string;
}
