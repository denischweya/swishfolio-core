<?php
/**
 * ActiveCampaign Provider
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Email\Providers;

use SwishfolioCore\Email\AbstractEmailProvider;

class ActiveCampaignProvider extends AbstractEmailProvider {
	/**
	 * Get provider name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'ActiveCampaign';
	}

	/**
	 * Get provider slug.
	 *
	 * @return string
	 */
	public function getSlug(): string {
		return 'activecampaign';
	}

	/**
	 * Get required fields.
	 *
	 * @return array
	 */
	public function getRequiredFields(): array {
		return [
			[
				'key'   => 'api_url',
				'label' => __( 'API URL', 'swishfolio-core' ),
				'type'  => 'text',
			],
			[
				'key'   => 'api_key',
				'label' => __( 'API Key', 'swishfolio-core' ),
				'type'  => 'password',
			],
		];
	}

	/**
	 * Get API URL.
	 *
	 * @return string
	 */
	private function getApiUrl(): string {
		$url = $this->credentials['api_url'] ?? '';
		return rtrim( $url, '/' ) . '/api/3';
	}

	/**
	 * Test connection.
	 *
	 * @return bool
	 */
	public function testConnection(): bool {
		$response = $this->request( $this->getApiUrl() . '/users/me', [
			'headers' => $this->getAuthHeaders(),
		] );

		return $response !== false && isset( $response['user'] );
	}

	/**
	 * Get lists.
	 *
	 * @return array
	 */
	public function getLists(): array {
		$response = $this->request( $this->getApiUrl() . '/lists?limit=100', [
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
			'contact' => [
				'email' => $email,
			],
		];

		// Add name fields.
		foreach ( $fields as $key => $value ) {
			if ( stripos( $key, 'name' ) !== false && stripos( $key, 'last' ) === false ) {
				$contactData['contact']['firstName'] = $value;
			} elseif ( stripos( $key, 'last' ) !== false ) {
				$contactData['contact']['lastName'] = $value;
			} elseif ( stripos( $key, 'phone' ) !== false || stripos( $key, 'tel' ) !== false ) {
				$contactData['contact']['phone'] = $value;
			}
		}

		// Create or update contact.
		$contactResponse = $this->request( $this->getApiUrl() . '/contact/sync', [
			'headers' => $this->getAuthHeaders(),
			'body'    => wp_json_encode( $contactData ),
		], 'POST' );

		$contactId = $contactResponse['contact']['id'] ?? null;

		if ( ! $contactId ) {
			return false;
		}

		// Add contact to list.
		$listData = [
			'contactList' => [
				'list'    => (int) $listId,
				'contact' => (int) $contactId,
				'status'  => 1, // 1 = subscribed
			],
		];

		$response = $this->request( $this->getApiUrl() . '/contactLists', [
			'headers' => $this->getAuthHeaders(),
			'body'    => wp_json_encode( $listData ),
		], 'POST' );

		return $response !== false;
	}

	/**
	 * Get auth headers.
	 *
	 * @return array
	 */
	private function getAuthHeaders(): array {
		return [
			'Api-Token'    => $this->credentials['api_key'] ?? '',
			'Content-Type' => 'application/json',
		];
	}
}
