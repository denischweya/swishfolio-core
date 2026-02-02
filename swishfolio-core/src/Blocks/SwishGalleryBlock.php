<?php
/**
 * Swish Gallery Block
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

use SwishfolioCore\Abstracts\AbstractBlock;

/**
 * Swish Gallery block - displays images in a customizable gallery with lightbox and carousel.
 */
class SwishGalleryBlock extends AbstractBlock {
	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'swish-gallery';
	}
}
