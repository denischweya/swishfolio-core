<?php
/**
 * Card Block.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

use SwishfolioCore\Abstracts\AbstractBlock;

/**
 * Card block - displays icon, title, description, and link.
 */
class CardBlock extends AbstractBlock {
	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'card';
	}
}
