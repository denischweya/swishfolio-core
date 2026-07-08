<?php
/**
 * Swish Slide Block.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

use SwishfolioCore\Abstracts\AbstractBlock;

/**
 * Swish Slide block - a single slide inside Swish Slider.
 */
class SwishSlideBlock extends AbstractBlock {
	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'swish-slide';
	}
}
