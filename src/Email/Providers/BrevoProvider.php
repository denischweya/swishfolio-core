<?php
/**
 * Brevo (formerly Sendinblue) Provider
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Email\Providers;

use SwishfolioCore\Email\AbstractEmailProvider;

class BrevoProvider extends AbstractEmailProvider {
	/**
	 * API base URL.
	 */
	private const API_URL = 'https://api.brevo.com/v3';

	/**
	 * Get provider name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'Brevo';
	}

	/**
	 * Get provider slug.
	 *
	 * @return string
	 */
	public function getSlug(): string {
		return 'brevo';
	}

	/**
	 * Get required fields.
	 *
	 * @return array
	 */
	public function getRequiredFields(): array {
		return [
			[
				'key'   => 'api_key',
				'label' => __( 'API Key', 'swishfolio-core' ),
				'type'  => 'password',
			],
		];
	}

	/**
	 * Test connection.
	 *
	 * @return bool
	 */
	public function testConnection(): bool {
		$response = $this->request( self::API_URL . '/account', [
			'headers' => $this->getAuthHeaders(),
		] );

		return $response !== false && isset( $response['email'] );
	}

	/**
	 * Get lists.
	 *
	 * @return array
	 */
	public function getLists(): array {
		$response = $this->request( self::API_URL . '/contacts/lists?limit=50', [
			'headers' => $this->getAuthHeaders(),
		] );

		if ( $response === false ) {
			return [];
		}

		$lists = [];
		foreach ( $response['lists'] ?? [] as $list ) {
			$lists[] = [
				'id'   => (string) $list['id'],
				'name' => $list['name'],
			];
		}

		return $lists;
	}

	/**
	 * Subscribe email to list.
	 *
	 * @param string $email  Email address.
	 * @param array  $fields Additional fields.
	 * @param string $listId List ID.
	 * @return bool
	 */
	public function subscribe( string $email, array $fields, string $listId ): bool {
		$contactData = [
			'email'            => $email,
			'listIds'          => [ (int) $listId ],
			'updateEnabled'    => true,
		];

		// Add attributes.
		$attributes = [];
		foreach ( $fields as $key => $value ) {
			if ( stripos( $key, 'name' ) !== false && stripos( $key, 'last' ) === false ) {
				$attributes['FIRSTNAME'] = $value;
			} elseif ( stripos( $key, 'last' ) !== false ) {
				$attributes['LASTNAME'] = $value;
			}
		}

		if ( ! empty( $attributes ) ) {
			$contactData['attributes'] = $attributes;
		}

		$response = $this->request( self::API_URL . '/contacts', [
			'headers' => $this->getAuthHeaders(),
			'body'    => wp_json_encode( $contactData ),
		], 'POST' );

		// Brevo returns 201 on success, 204 if contact already exists.
		return $response !== false || $this->lastError === '';
	}

	/**
	 * Get auth headers.
	 *
	 * @return array
	 */
	private function getAuthHeaders(): array {
		return [
			'api-key'      => $this->credentials['api_key'] ?? '',
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		];
	}
}
