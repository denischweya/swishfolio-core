<?php
/**
 * Email Provider Interface
 *
 * Contract for ESP (Email Service Provider) adapters.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Email;

interface EmailProviderInterface {
	/**
	 * Get the provider name.
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Get the provider slug.
	 *
	 * @return string
	 */
	public function getSlug(): string;

	/**
	 * Get the required fields for this provider.
	 *
	 * @return array Array of field definitions with 'key', 'label', 'type'.
	 */
	public function getRequiredFields(): array;

	/**
	 * Set the API credentials.
	 *
	 * @param array $credentials Provider credentials.
	 * @return void
	 */
	public function setCredentials( array $credentials ): void;

	/**
	 * Test the connection to the provider.
	 *
	 * @return bool True if connection successful.
	 */
	public function testConnection(): bool;

	/**
	 * Get available lists/audiences from the provider.
	 *
	 * @return array Array of lists with 'id' and 'name'.
	 */
	public function getLists(): array;

	/**
	 * Subscribe an email to a list.
	 *
	 * @param string $email  Email address.
	 * @param array  $fields Additional fields (name, etc.).
	 * @param string $listId List/audience ID.
	 * @return bool True if successful.
	 */
	public function subscribe( string $email, array $fields, string $listId ): bool;

	/**
	 * Get the last error message.
	 *
	 * @return string
	 */
	public function getLastError(): string;
}
