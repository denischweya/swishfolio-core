<?php
/**
 * Swish Counter Block.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

use SwishfolioCore\Abstracts\AbstractBlock;

/**
 * Swish Counter parent block. Holds a row of swish-counter-item children
 * and shared visual styling via CSS custom properties.
 */
class SwishCounterBlock extends AbstractBlock {
	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'swish-counter';
	}
}
