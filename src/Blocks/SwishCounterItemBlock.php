<?php
/**
 * Swish Counter Item Block.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

use SwishfolioCore\Abstracts\AbstractBlock;

/**
 * Counter item - a single animated stat inside Swish Counter.
 */
class SwishCounterItemBlock extends AbstractBlock {
	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'swish-counter-item';
	}
}
