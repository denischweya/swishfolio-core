<?php
/**
 * ConvertKit Provider
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Email\Providers;

use SwishfolioCore\Email\AbstractEmailProvider;

class ConvertKitProvider extends AbstractEmailProvider {
	/**
	 * API base URL.
	 */
	private const API_URL = 'https://api.convertkit.com/v3';

	/**
	 * Get provider name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'ConvertKit';
	}

	/**
	 * Get provider slug.
	 *
	 * @return string
	 */
	public function getSlug(): string {
		return 'convertkit';
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
			[
				'key'   => 'api_secret',
				'label' => __( 'API Secret', 'swishfolio-core' ),
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
		$apiSecret = $this->credentials['api_secret'] ?? '';
		$response = $this->request( self::API_URL . '/account?api_secret=' . urlencode( $apiSecret ) );

		return $response !== false && isset( $response['primary_email_address'] );
	}

	/**
	 * Get lists (forms in ConvertKit).
	 *
	 * @return array
	 */
	public function getLists(): array {
		$apiKey = $this->credentials['api_key'] ?? '';
		$response = $this->request( self::API_URL . '/forms?api_key=' . urlencode( $apiKey ) );

		if ( $response === false ) {
			return [];
		}

		$lists = [];
		foreach ( $response['forms'] ?? [] as $form ) {
			$lists[] = [
				'id'   => (string) $form['id'],
				'name' => $form['name'],
			];
		}

		return $lists;
	}

	/**
	 * Subscribe email to form.
	 *
	 * @param string $email  Email address.
	 * @param array  $fields Additional fields.
	 * @param string $listId Form ID.
	 * @return bool
	 */
	public function subscribe( string $email, array $fields, string $listId ): bool {
		$apiKey = $this->credentials['api_key'] ?? '';
		$url = self::API_URL . "/forms/{$listId}/subscribe";

		$data = [
			'api_key' => $apiKey,
			'email'   => $email,
		];

		// Add first name if available.
		foreach ( $fields as $key => $value ) {
			if ( stripos( $key, 'name' ) !== false && stripos( $key, 'last' ) === false ) {
				$data['first_name'] = $value;
				break;
			}
		}

		$response = $this->request( $url, [
			'body' => wp_json_encode( $data ),
		], 'POST' );

		return $response !== false && isset( $response['subscription'] );
	}
}
