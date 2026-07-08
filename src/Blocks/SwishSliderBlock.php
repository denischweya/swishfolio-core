<?php
/**
 * Swish Slider Block.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

use SwishfolioCore\Abstracts\AbstractBlock;

/**
 * Swish Slider block - continuous horizontal marquee of slides.
 */
class SwishSliderBlock extends AbstractBlock {
	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'swish-slider';
	}
}
