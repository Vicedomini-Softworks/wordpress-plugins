<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Secrets_Manager_Encryption {

	private static function get_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	public static function encrypt( string $value ): string {
		if ( '' === $value ) {
			return $value;
		}

		$key = self::get_key();
		$iv  = openssl_random_pseudo_bytes( 16 );

		$encrypted = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			return $value;
		}

		return base64_encode( $iv . $encrypted );
	}

	public static function decrypt( string $value ): string {
		if ( '' === $value ) {
			return $value;
		}

		$decoded = base64_decode( $value, true );

		if ( false === $decoded || strlen( $decoded ) <= 16 ) {
			return $value;
		}

		$iv        = substr( $decoded, 0, 16 );
		$encrypted = substr( $decoded, 16 );

		if ( strlen( $iv ) !== 16 ) {
			return $value;
		}

		$key       = self::get_key();
		$decrypted = openssl_decrypt( $encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $decrypted ) {
			return $value;
		}

		return $decrypted;
	}
}
