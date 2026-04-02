<?php
/**
 * Bento Cards Block.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

use SwishfolioCore\Abstracts\AbstractBlock;

/**
 * Bento Cards block - Bento-grid style card layout.
 */
class BentoCardsBlock extends AbstractBlock {
	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'bento-cards';
	}
}
