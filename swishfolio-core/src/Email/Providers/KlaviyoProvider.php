<?php
/**
 * Klaviyo Provider
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Email\Providers;

use SwishfolioCore\Email\AbstractEmailProvider;

class KlaviyoProvider extends AbstractEmailProvider {
	/**
	 * API base URL.
	 */
	private const API_URL = 'https://a.klaviyo.com/api';

	/**
	 * Get provider name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'Klaviyo';
	}

	/**
	 * Get provider slug.
	 *
	 * @return string
	 */
	public function getSlug(): string {
		return 'klaviyo';
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
				'label' => __( 'Private API Key', 'swishfolio-core' ),
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
		$response = $this->request( self::API_URL . '/accounts/', [
			'headers' => $this->getAuthHeaders(),
		] );

		return $response !== false;
	}

	/**
	 * Get lists.
	 *
	 * @return array
	 */
	public function getLists(): array {
		$response = $this->request( self::API_URL . '/lists/', [
			'headers' => $this->getAuthHeaders(),
		] );

		if ( $response === false ) {
			return [];
		}

		$lists = [];
		foreach ( $response['data'] ?? [] as $list ) {
			$lists[] = [
				'id'   => $list['id'],
				'name' => $list['attributes']['name'] ?? $list['id'],
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
		$url = self::API_URL . '/lists/' . $listId . '/relationships/profiles/';

		// First, create or update the profile.
		$profileData = [
			'data' => [
				'type'       => 'profile',
				'attributes' => [
					'email' => $email,
				],
			],
		];

		// Add name fields if available.
		foreach ( $fields as $key => $value ) {
			if ( stripos( $key, 'name' ) !== false && stripos( $key, 'last' ) === false ) {
				$profileData['data']['attributes']['first_name'] = $value;
			} elseif ( stripos( $key, 'last' ) !== false ) {
				$profileData['data']['attributes']['last_name'] = $value;
			}
		}

		// Create profile.
		$profileResponse = $this->request( self::API_URL . '/profiles/', [
			'headers' => $this->getAuthHeaders(),
			'body'    => wp_json_encode( $profileData ),
		], 'POST' );

		$profileId = $profileResponse['data']['id'] ?? null;

		if ( ! $profileId ) {
			// Try to get existing profile by email.
			$profileId = $this->getProfileIdByEmail( $email );
		}

		if ( ! $profileId ) {
			return false;
		}

		// Add profile to list.
		$subscribeData = [
			'data' => [
				[
					'type' => 'profile',
					'id'   => $profileId,
				],
			],
		];

		$response = $this->request( $url, [
			'headers' => $this->getAuthHeaders(),
			'body'    => wp_json_encode( $subscribeData ),
		], 'POST' );

		return $response !== false;
	}

	/**
	 * Get profile ID by email.
	 *
	 * @param string $email Email address.
	 * @return string|null
	 */
	private function getProfileIdByEmail( string $email ): ?string {
		$url = self::API_URL . '/profiles/?filter=equals(email,"' . urlencode( $email ) . '")';

		$response = $this->request( $url, [
			'headers' => $this->getAuthHeaders(),
		] );

		return $response['data'][0]['id'] ?? null;
	}

	/**
	 * Get auth headers.
	 *
	 * @return array
	 */
	private function getAuthHeaders(): array {
		return [
			'Authorization' => 'Klaviyo-API-Key ' . ( $this->credentials['api_key'] ?? '' ),
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'revision'      => '2024-02-15',
		];
	}
}
