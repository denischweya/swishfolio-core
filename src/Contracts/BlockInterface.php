<?php
/**
 * Block Interface.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Contracts;

/**
 * Interface for WordPress blocks.
 */
interface BlockInterface extends RegistrableInterface {
	/**
	 * Get the block name (without namespace).
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Get the path to the block directory.
	 *
	 * @return string
	 */
	public function getPath(): string;
}
