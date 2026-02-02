<?php
/**
 * Encryption Service
 *
 * Handles encryption and decryption of sensitive data like API keys.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Services;

class EncryptionService {
	/**
	 * Encryption method.
	 */
	private const METHOD = 'aes-256-cbc';

	/**
	 * Get the encryption key derived from WordPress salts.
	 *
	 * @return string
	 */
	private function getKey(): string {
		$salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'swishfolio-default-key';
		return hash( 'sha256', $salt, true );
	}

	/**
	 * Encrypt a value.
	 *
	 * @param string $value The value to encrypt.
	 * @return string The encrypted value (base64 encoded).
	 */
	public function encrypt( string $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::METHOD ) );
		$encrypted = openssl_encrypt( $value, self::METHOD, $this->getKey(), 0, $iv );

		if ( $encrypted === false ) {
			return '';
		}

		// Combine IV and encrypted data, then base64 encode.
		return base64_encode( $iv . '::' . $encrypted );
	}

	/**
	 * Decrypt a value.
	 *
	 * @param string $value The encrypted value (base64 encoded).
	 * @return string The decrypted value.
	 */
	public function decrypt( string $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$data = base64_decode( $value );
		if ( $data === false ) {
			return '';
		}

		$parts = explode( '::', $data, 2 );
		if ( count( $parts ) !== 2 ) {
			return '';
		}

		list( $iv, $encrypted ) = $parts;
		$decrypted = openssl_decrypt( $encrypted, self::METHOD, $this->getKey(), 0, $iv );

		return $decrypted !== false ? $decrypted : '';
	}

	/**
	 * Check if a value is encrypted.
	 *
	 * @param string $value The value to check.
	 * @return bool
	 */
	public function isEncrypted( string $value ): bool {
		if ( empty( $value ) ) {
			return false;
		}

		$decoded = base64_decode( $value, true );
		if ( $decoded === false ) {
			return false;
		}

		return strpos( $decoded, '::' ) !== false;
	}
}
