<?php
/**
 * Mailchimp Provider
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Email\Providers;

use SwishfolioCore\Email\AbstractEmailProvider;

class MailchimpProvider extends AbstractEmailProvider {
	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private string $apiUrl = '';

	/**
	 * Get provider name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'Mailchimp';
	}

	/**
	 * Get provider slug.
	 *
	 * @return string
	 */
	public function getSlug(): string {
		return 'mailchimp';
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
	 * Set credentials and determine data center.
	 *
	 * @param array $credentials Credentials.
	 * @return void
	 */
	public function setCredentials( array $credentials ): void {
		parent::setCredentials( $credentials );

		// Mailchimp API key format: xxxx-dc1
		$apiKey = $this->credentials['api_key'] ?? '';
		if ( strpos( $apiKey, '-' ) !== false ) {
			$dc = substr( strrchr( $apiKey, '-' ), 1 );
			$this->apiUrl = "https://{$dc}.api.mailchimp.com/3.0";
		}
	}

	/**
	 * Test connection.
	 *
	 * @return bool
	 */
	public function testConnection(): bool {
		if ( empty( $this->apiUrl ) ) {
			$this->lastError = __( 'Invalid API key format.', 'swishfolio-core' );
			return false;
		}

		$response = $this->request( $this->apiUrl . '/ping', [
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
		$response = $this->request( $this->apiUrl . '/lists?count=100', [
			'headers' => $this->getAuthHeaders(),
		] );

		if ( $response === false ) {
			return [];
		}

		$lists = [];
		foreach ( $response['lists'] ?? [] as $list ) {
			$lists[] = [
				'id'   => $list['id'],
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
		$subscriberHash = md5( strtolower( $email ) );
		$url = $this->apiUrl . "/lists/{$listId}/members/{$subscriberHash}";

		$data = [
			'email_address' => $email,
			'status_if_new' => 'subscribed',
			'status'        => 'subscribed',
		];

		// Map fields to Mailchimp merge fields.
		$mergeFields = [];
		foreach ( $fields as $key => $value ) {
			if ( stripos( $key, 'name' ) !== false || $key === 'FNAME' ) {
				$mergeFields['FNAME'] = $value;
			} elseif ( stripos( $key, 'last' ) !== false || $key === 'LNAME' ) {
				$mergeFields['LNAME'] = $value;
			}
		}

		if ( ! empty( $mergeFields ) ) {
			$data['merge_fields'] = $mergeFields;
		}

		$response = $this->request( $url, [
			'headers' => $this->getAuthHeaders(),
			'body'    => wp_json_encode( $data ),
		], 'PUT' );

		return $response !== false;
	}

	/**
	 * Get auth headers.
	 *
	 * @return array
	 */
	private function getAuthHeaders(): array {
		return [
			'Authorization' => 'Basic ' . base64_encode( 'anystring:' . ( $this->credentials['api_key'] ?? '' ) ),
			'Content-Type'  => 'application/json',
		];
	}
}
