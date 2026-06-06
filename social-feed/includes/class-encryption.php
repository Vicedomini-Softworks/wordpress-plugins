<?php
/**
 * Encryption handler for API credentials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Feed_Encryption {

	private static function get_key(): string {
		// Use WordPress AUTH_KEY if available, otherwise generate salt
		if ( defined( 'AUTH_KEY' ) && AUTH_KEY ) {
			return AUTH_KEY;
		}
		return wp_salt( 'auth' );
	}

	public static function encrypt( string $value ): string {
		if ( empty( $value ) ) {
			return $value;
		}

		$key = self::get_key();
		$iv  = openssl_random_pseudo_bytes( 16 );

		if ( false === $iv ) {
			return $value; // Fallback to plaintext if encryption fails
		}

		$encrypted = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			return $value; // Fallback to plaintext if encryption fails
		}

		return base64_encode( $iv . '::' . $encrypted );
	}

	public static function decrypt( string $value ): string {
		if ( empty( $value ) || ! is_string( $value ) ) {
			return $value;
		}

		// Check if value is encrypted (base64 encoded with :: separator)
		$decoded = base64_decode( $value, true );
		if ( false === $decoded || strpos( $decoded, '::' ) === false ) {
			return $value; // Not encrypted, return as-is
		}

		$parts = explode( '::', $decoded, 2 );
		if ( count( $parts ) !== 2 ) {
			return $value; // Invalid format
		}

		[$iv, $encrypted] = $parts;

		// Ensure IV is 16 bytes
		if ( strlen( $iv ) !== 16 ) {
			return $value;
		}

		$key       = self::get_key();
		$decrypted = openssl_decrypt( $encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $decrypted ) {
			return $value; // Fallback to ciphertext if decryption fails
		}

		return $decrypted;
	}

	/**
	 * Register encryption filters for a specific platform option
	 */
	public static function register_for_platform( string $platform ): void {
		$option_name = 'social_feed_creds_' . $platform;

		// Encrypt on save
		add_filter(
			'pre_update_option_' . $option_name,
			function ( $new_value, $old_value ) use ( $option_name ) {
				if ( $new_value === $old_value ) {
					return $new_value;
				}

				if ( ! is_array( $new_value ) ) {
					return $new_value;
				}

				$encrypt_fields = array( 'client_id', 'client_secret', 'access_token', 'refresh_token' );

				foreach ( $encrypt_fields as $field ) {
					if ( isset( $new_value[ $field ] ) ) {
						// Only encrypt if not already encrypted
						$is_encrypted = self::is_encrypted( $new_value[ $field ] );
						if ( ! $is_encrypted && ! empty( $new_value[ $field ] ) ) {
							$new_value[ $field ] = self::encrypt( $new_value[ $field ] );
						}
					}
				}

				return $new_value;
			},
			10,
			2
		);

		// Decrypt on read
		add_filter(
			'option_' . $option_name,
			function ( $value ) {
				if ( ! is_array( $value ) ) {
					return $value;
				}

				$decrypt_fields = array( 'client_id', 'client_secret', 'access_token', 'refresh_token' );

				foreach ( $decrypt_fields as $field ) {
					if ( isset( $value[ $field ] ) ) {
						$value[ $field ] = self::decrypt( $value[ $field ] );
					}
				}

				return $value;
			}
		);
	}

	/**
	 * Check if a value appears to be encrypted
	 */
	private static function is_encrypted( string $value ): bool {
		$decoded = base64_decode( $value, true );
		return false !== $decoded && strpos( $decoded, '::' ) !== false;
	}
}
