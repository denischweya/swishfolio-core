<?php
/**
 * Registrable Interface.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Contracts;

/**
 * Interface for anything that registers with WordPress.
 */
interface RegistrableInterface {
	/**
	 * Register with WordPress.
	 *
	 * @return void
	 */
	public function register(): void;
}
