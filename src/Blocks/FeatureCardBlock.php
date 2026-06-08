<?php
/**
 * Feature Card Block.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

use SwishfolioCore\Abstracts\AbstractBlock;

/**
 * Feature Card block - a single card inside a Features block.
 */
class FeatureCardBlock extends AbstractBlock {
	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'feature-card';
	}
}
