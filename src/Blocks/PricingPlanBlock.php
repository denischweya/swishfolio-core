<?php
/**
 * Pricing Plan Block.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

use SwishfolioCore\Abstracts\AbstractBlock;

/**
 * Pricing Plan block - a single plan inside the Pricing Table.
 */
class PricingPlanBlock extends AbstractBlock {
	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'pricing-plan';
	}
}
