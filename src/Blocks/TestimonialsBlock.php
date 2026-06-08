<?php
/**
 * Testimonials Block.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

use SwishfolioCore\Abstracts\AbstractBlock;

/**
 * Testimonials parent block. Holds a grid of testimonial-card InnerBlocks;
 * registered from block.json with a static save (no PHP render).
 */
class TestimonialsBlock extends AbstractBlock {
	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'testimonials';
	}
}
