<?php
/**
 * Crypto class for OpenProvider WooCommerce
 *
 * AES-256-CBC encryption/decryption for credential storage.
 * Mirrors v-secrets-manager encryption pattern.
 *
 * @package OpenProviderWooCommerce\Support
 */

namespace OpenProviderWooCommerce\Support;

/**
 * Crypto class.
 */
class Crypto {

	/**
	 * Cipher method.
	 */
	private const CIPHER = 'AES-256-CBC';

	/**
	 * Get encryption key from WordPress salt.
	 *
	 * @return string 32-byte key for AES-256.
	 */
	private static function get_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	/**
	 * Encrypt a value.
	 *
	 * @param string $value Value to encrypt.
	 * @return string Base64-encoded IV + encrypted data, or empty string on failure.
	 */
	public static function encrypt( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$key = self::get_key();
		$iv  = openssl_random_pseudo_bytes( 16 );

		if ( false === $iv ) {
			return '';
		}

		$encrypted = openssl_encrypt( $value, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			return '';
		}

		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt a value.
	 *
	 * @param string $value Base64-encoded IV + encrypted data.
	 * @return string Decrypted value, or original value on failure.
	 */
	public static function decrypt( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$decoded = base64_decode( $value, true );

		if ( false === $decoded || strlen( $decoded ) <= 16 ) {
			return '';
		}

		$iv        = substr( $decoded, 0, 16 );
		$encrypted = substr( $decoded, 16 );

		if ( strlen( $iv ) !== 16 ) {
			return '';
		}

		$key       = self::get_key();
		$decrypted = openssl_decrypt( $encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $decrypted ) {
			return '';
		}

		return $decrypted;
	}
}
