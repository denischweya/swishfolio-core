<?php
/**
 * Abstract Taxonomy
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Abstracts;

use SwishfolioCore\Contracts\TaxonomyInterface;

abstract class AbstractTaxonomy implements TaxonomyInterface {
	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public function register(): void {
		$args = apply_filters( "sfcore_{$this->getSlug()}_args", $this->getArgs() );
		register_taxonomy( $this->getSlug(), $this->getPostTypes(), $args );
	}
}
