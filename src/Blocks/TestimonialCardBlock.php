<?php
/**
 * Testimonial Card Block.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

use SwishfolioCore\Abstracts\AbstractBlock;

/**
 * Testimonial Card block - a single quote inside the Testimonials block.
 */
class TestimonialCardBlock extends AbstractBlock {
	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'testimonial-card';
	}
}
