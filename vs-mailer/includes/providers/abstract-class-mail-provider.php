<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class VS_Mailer_Mail_Provider {

	abstract public static function send( $to, string $subject, string $message, $headers, $attachments ): bool;

	abstract public static function test_connection(): array;

	protected static function parse_headers( $headers ): array {
		if ( ! is_array( $headers ) ) {
			$headers = explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
		}

		$parsed = array();
		foreach ( $headers as $header ) {
			$parts = explode( ':', $header, 2 );
			if ( 2 === count( $parts ) ) {
				$key            = strtolower( trim( $parts[0] ) );
				$parsed[ $key ] = trim( $parts[1] );
			}
		}

		return $parsed;
	}

	protected static function get_content_type( array $parsed_headers ): string {
		if ( isset( $parsed_headers['content-type'] ) ) {
			$type = strtolower( $parsed_headers['content-type'] );
			if ( false !== strpos( $type, 'text/html' ) ) {
				return 'text/html';
			}
		}
		return 'text/plain';
	}

	protected static function normalize_to_array( $to ): array {
		if ( is_string( $to ) ) {
			return explode( ',', $to );
		}
		return is_array( $to ) ? $to : array( (string) $to );
	}

	protected static function get_from_header( array $parsed_headers ): array {
		if ( ! isset( $parsed_headers['from'] ) ) {
			return array(
				'email' => self::get_default_from_email(),
				'name'  => self::get_default_from_name(),
			);
		}

		$from = $parsed_headers['from'];
		if ( preg_match( '/(.+)\s*<(.+@.+)>/', $from, $m ) ) {
			return array(
				'name'  => trim( $m[1] ),
				'email' => trim( $m[2] ),
			);
		}

		return array(
			'email' => trim( $from ),
			'name'  => '',
		);
	}

	protected static function get_default_from_email(): string {
		$email = get_option( 'vs_mailer_from_email', '' );
		return ! empty( $email ) ? $email : get_bloginfo( 'admin_email' );
	}

	protected static function get_default_from_name(): string {
		$name = get_option( 'vs_mailer_from_name', '' );
		return ! empty( $name ) ? $name : get_bloginfo( 'name' );
	}
}
