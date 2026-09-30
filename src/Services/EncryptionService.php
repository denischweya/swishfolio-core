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
	 * Marks the delimiter-free storage format.
	 *
	 * The original format was `base64( iv . '::' . ciphertext )`, split on the
	 * first '::'. That corrupted roughly 1 in 254 credentials, for two reasons:
	 *
	 *  - an IV containing '::' (about 1 in 4,000), and — the dominant one —
	 *  - an IV whose last byte is a single ':' (about 1 in 256), because
	 *    `iv . '::'` then contains '::' one byte early, truncating the IV to 15
	 *    bytes so the value decrypted to garbage.
	 *
	 * 96% of those failures returned a mangled NON-EMPTY string, which passes
	 * every `empty()` check downstream. The IV for aes-256-cbc is always exactly
	 * 16 bytes, so the fix is a fixed offset and no delimiter at all — a
	 * collision becomes impossible rather than merely unlikely.
	 */
	public const PREFIX = 'sfe2:';

	/**
	 * IV length for the cipher above, in bytes.
	 */
	private const IV_LENGTH = 16;

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
	 * @return string The encrypted value, prefixed and base64 encoded.
	 */
	public function encrypt( string $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$iv = openssl_random_pseudo_bytes( self::IV_LENGTH );

		// OPENSSL_RAW_DATA keeps the ciphertext binary so the IV can sit in front
		// of it at a fixed offset.
		$encrypted = openssl_encrypt( $value, self::METHOD, $this->getKey(), OPENSSL_RAW_DATA, $iv );

		if ( $encrypted === false ) {
			return '';
		}

		return self::PREFIX . base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt a value, in either storage format.
	 *
	 * @param string $value The encrypted value.
	 * @return string The decrypted value, or '' when it cannot be read.
	 */
	public function decrypt( string $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		if ( strpos( $value, self::PREFIX ) === 0 ) {
			return $this->decryptCurrent( substr( $value, strlen( self::PREFIX ) ) );
		}

		return $this->decryptLegacy( $value );
	}

	/**
	 * Whether a value is in the superseded delimiter format.
	 *
	 * Lets a migration find credentials worth re-encrypting.
	 *
	 * @param string $value The stored value.
	 * @return bool
	 */
	public function isLegacy( string $value ): bool {
		if ( empty( $value ) || strpos( $value, self::PREFIX ) === 0 ) {
			return false;
		}

		$decoded = base64_decode( $value, true );

		return $decoded !== false && strpos( $decoded, '::' ) !== false;
	}

	/**
	 * Check if a value is encrypted, in either format.
	 *
	 * @param string $value The value to check.
	 * @return bool
	 */
	public function isEncrypted( string $value ): bool {
		if ( empty( $value ) ) {
			return false;
		}

		return strpos( $value, self::PREFIX ) === 0 || $this->isLegacy( $value );
	}

	/**
	 * Read the delimiter-free format.
	 *
	 * @param string $payload Base64 of iv . ciphertext.
	 * @return string
	 */
	private function decryptCurrent( string $payload ): string {
		$data = base64_decode( $payload, true );

		if ( $data === false || strlen( $data ) <= self::IV_LENGTH ) {
			return '';
		}

		$iv        = substr( $data, 0, self::IV_LENGTH );
		$encrypted = substr( $data, self::IV_LENGTH );
		$decrypted = openssl_decrypt( $encrypted, self::METHOD, $this->getKey(), OPENSSL_RAW_DATA, $iv );

		return $decrypted !== false ? $decrypted : '';
	}

	/**
	 * Read the superseded delimiter format.
	 *
	 * A value written with an IV ending in ':' cannot be recovered — the IV is
	 * genuinely gone. Such a value must read as ABSENT rather than as the
	 * plausible-looking garbage the old code returned, so the IV length is
	 * checked before attempting anything.
	 *
	 * @param string $value Base64 of iv . '::' . base64 ciphertext.
	 * @return string
	 */
	private function decryptLegacy( string $value ): string {
		$data = base64_decode( $value, true );

		if ( $data === false ) {
			return '';
		}

		$parts = explode( '::', $data, 2 );

		if ( count( $parts ) !== 2 || strlen( $parts[0] ) !== self::IV_LENGTH ) {
			return '';
		}

		$decrypted = openssl_decrypt( $parts[1], self::METHOD, $this->getKey(), 0, $parts[0] );

		return $decrypted !== false ? $decrypted : '';
	}
}
