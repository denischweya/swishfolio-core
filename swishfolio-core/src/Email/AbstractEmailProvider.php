<?php
/**
 * Abstract Email Provider
 *
 * Base class for ESP adapters.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Email;

use SwishfolioCore\Services\EncryptionService;

abstract class AbstractEmailProvider implements EmailProviderInterface {
	/**
	 * API credentials.
	 *
	 * @var array
	 */
	protected array $credentials = [];

	/**
	 * Last error message.
	 *
	 * @var string
	 */
	protected string $lastError = '';

	/**
	 * Encryption service.
	 *
	 * @var EncryptionService
	 */
	protected EncryptionService $encryption;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->encryption = new EncryptionService();
	}

	/**
	 * Set credentials.
	 *
	 * @param array $credentials Credentials array.
	 * @return void
	 */
	public function setCredentials( array $credentials ): void {
		$this->credentials = [];

		foreach ( $credentials as $key => $value ) {
			// Decrypt if encrypted.
			if ( ! empty( $value ) && $this->encryption->isEncrypted( $value ) ) {
				$this->credentials[ $key ] = $this->encryption->decrypt( $value );
			} else {
				$this->credentials[ $key ] = $value;
			}
		}
	}

	/**
	 * Get the last error message.
	 *
	 * @return string
	 */
	public function getLastError(): string {
		return $this->lastError;
	}

	/**
	 * Make an HTTP request.
	 *
	 * @param string $url     Request URL.
	 * @param array  $args    Request arguments.
	 * @param string $method  HTTP method.
	 * @return array|false Response data or false on failure.
	 */
	protected function request( string $url, array $args = [], string $method = 'GET' ) {
		$defaults = [
			'method'  => $method,
			'timeout' => 30,
			'headers' => [
				'Content-Type' => 'application/json',
			],
		];

		$args = wp_parse_args( $args, $defaults );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->lastError = $response->get_error_message();
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 400 ) {
			$this->lastError = $this->parseErrorResponse( $data, $code );
			return false;
		}

		return $data;
	}

	/**
	 * Parse error response.
	 *
	 * @param array|null $data Response data.
	 * @param int        $code HTTP status code.
	 * @return string Error message.
	 */
	protected function parseErrorResponse( ?array $data, int $code ): string {
		if ( isset( $data['error'] ) ) {
			return is_string( $data['error'] ) ? $data['error'] : ( $data['error']['message'] ?? 'Unknown error' );
		}

		if ( isset( $data['message'] ) ) {
			return $data['message'];
		}

		return sprintf( 'HTTP Error %d', $code );
	}
}
