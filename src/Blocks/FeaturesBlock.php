<?php
/**
 * Features Block.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

use SwishfolioCore\Abstracts\AbstractBlock;

/**
 * Features block - Bento-grid style features section.
 */
class FeaturesBlock extends AbstractBlock {
	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'features';
	}
}
