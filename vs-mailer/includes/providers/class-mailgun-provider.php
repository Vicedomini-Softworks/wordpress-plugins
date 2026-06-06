<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Mailer_Mailgun_Provider extends VS_Mailer_Mail_Provider {

	public static function send( $to, string $subject, string $message, $headers, $attachments ): bool {
		$api_key = vs_secrets_manager_get( 'vs_mailer_mailgun_api_key' );
		$domain  = get_option( 'vs_mailer_mailgun_domain', '' );
		$region  = get_option( 'vs_mailer_mailgun_region', 'us' );

		if ( empty( $api_key ) || empty( $domain ) ) {
			return false;
		}

		$base_url = 'eu' === $region
			? 'https://api.eu.mailgun.net/v3/' . $domain . '/messages'
			: 'https://api.mailgun.net/v3/' . $domain . '/messages';

		$parsed_headers = self::parse_headers( $headers );
		$from           = self::get_from_header( $parsed_headers );
		$content_type   = self::get_content_type( $parsed_headers );
		$to_addresses   = self::normalize_to_array( $to );

		$body = array(
			'from'    => ! empty( $from['name'] )
				? $from['name'] . ' <' . $from['email'] . '>'
				: $from['email'],
			'to'      => implode( ',', array_map( 'trim', $to_addresses ) ),
			'subject' => $subject,
		);

		if ( 'text/html' === $content_type ) {
			$body['html'] = $message;
		} else {
			$body['text'] = $message;
		}

		if ( isset( $parsed_headers['cc'] ) ) {
			$body['cc'] = $parsed_headers['cc'];
		}

		if ( isset( $parsed_headers['bcc'] ) ) {
			$body['bcc'] = $parsed_headers['bcc'];
		}

		if ( ! empty( $attachments ) ) {
			return self::send_with_attachments( $base_url, $api_key, $body, $attachments );
		}

		$response = wp_remote_post(
			$base_url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( 'api:' . $api_key ),
				),
				'body'    => $body,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$status = wp_remote_retrieve_response_code( $response );
		return $status >= 200 && $status < 300;
	}

	private static function send_with_attachments( string $base_url, string $api_key, array $body, array $attachments ): bool {
		$boundary = wp_generate_password( 24, false );
		$crlf     = "\r\n";
		$payload  = '';

		foreach ( $body as $key => $value ) {
			$payload .= '--' . $boundary . $crlf;
			$payload .= 'Content-Disposition: form-data; name="' . $key . '"' . $crlf . $crlf;
			$payload .= $value . $crlf;
		}

		foreach ( (array) $attachments as $file ) {
			$file_content = @file_get_contents( $file );
			if ( false === $file_content ) {
				continue;
			}
			$filename = basename( $file );
			$payload .= '--' . $boundary . $crlf;
			$payload .= 'Content-Disposition: form-data; name="attachment"; filename="' . $filename . '"' . $crlf;
			$payload .= 'Content-Type: application/octet-stream' . $crlf . $crlf;
			$payload .= $file_content . $crlf;
		}

		$payload .= '--' . $boundary . '--' . $crlf;

		$response = wp_remote_post(
			$base_url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( 'api:' . $api_key ),
					'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
				),
				'body'    => $payload,
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$status = wp_remote_retrieve_response_code( $response );
		return $status >= 200 && $status < 300;
	}

	public static function test_connection(): array {
		$api_key = vs_secrets_manager_get( 'vs_mailer_mailgun_api_key' );
		$domain  = get_option( 'vs_mailer_mailgun_domain', '' );

		if ( empty( $api_key ) || empty( $domain ) ) {
			return array(
				'success' => false,
				'message' => __( 'Mailgun API key or domain not configured.', 'vs-mailer' ),
			);
		}

		$region = get_option( 'vs_mailer_mailgun_region', 'us' );
		$url    = 'eu' === $region
			? 'https://api.eu.mailgun.net/v3/domains'
			: 'https://api.mailgun.net/v3/domains';

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( 'api:' . $api_key ),
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$status = wp_remote_retrieve_response_code( $response );

		if ( 200 === $status ) {
			return array(
				'success' => true,
				'message' => __( 'Successfully connected to Mailgun.', 'vs-mailer' ),
			);
		}

		return array(
			'success' => false,
			'message' => sprintf(
				/* translators: %d: HTTP status code */
				__( 'Mailgun API error (HTTP %d).', 'vs-mailer' ),
				$status
			),
		);
	}
}
