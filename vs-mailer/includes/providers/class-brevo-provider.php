<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Mailer_Brevo_Provider extends VS_Mailer_Mail_Provider {

	const API_URL = 'https://api.brevo.com/v3/smtp/email';

	public static function send( $to, string $subject, string $message, $headers, $attachments ): bool {
		$api_key = vs_secrets_manager_get( 'vs_mailer_brevo_api_key' );

		if ( empty( $api_key ) ) {
			return false;
		}

		$parsed_headers = self::parse_headers( $headers );
		$from           = self::get_from_header( $parsed_headers );
		$content_type   = self::get_content_type( $parsed_headers );
		$to_addresses   = self::normalize_to_array( $to );

		$payload = array(
			'sender'  => array(
				'name'  => $from['name'],
				'email' => $from['email'],
			),
			'to'      => array(),
			'subject' => $subject,
			'headers' => array(
				'X-Mailer' => 'VS-Mailer',
			),
		);

		foreach ( $to_addresses as $address ) {
			$payload['to'][] = array( 'email' => trim( $address ) );
		}

		if ( isset( $parsed_headers['cc'] ) ) {
			$cc_addresses  = explode( ',', $parsed_headers['cc'] );
			$payload['cc'] = array();
			foreach ( $cc_addresses as $address ) {
				$payload['cc'][] = array( 'email' => trim( $address ) );
			}
		}

		if ( isset( $parsed_headers['bcc'] ) ) {
			$bcc_addresses  = explode( ',', $parsed_headers['bcc'] );
			$payload['bcc'] = array();
			foreach ( $bcc_addresses as $address ) {
				$payload['bcc'][] = array( 'email' => trim( $address ) );
			}
		}

		if ( 'text/html' === $content_type ) {
			$payload['htmlContent'] = $message;
		} else {
			$payload['textContent'] = $message;
		}

		if ( ! empty( $attachments ) ) {
			$payload['attachment'] = array();
			foreach ( (array) $attachments as $file ) {
				$file_content = @file_get_contents( $file );
				if ( false !== $file_content ) {
					$payload['attachment'][] = array(
						'name'    => basename( $file ),
						'content' => base64_encode( $file_content ),
					);
				}
			}

			if ( empty( $payload['attachment'] ) ) {
				unset( $payload['attachment'] );
			}
		}

		$response = wp_remote_post(
			self::API_URL,
			array(
				'headers' => array(
					'api-key'      => $api_key,
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$status = wp_remote_retrieve_response_code( $response );
		return $status >= 200 && $status < 300;
	}

	public static function test_connection(): array {
		$api_key = vs_secrets_manager_get( 'vs_mailer_brevo_api_key' );

		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Brevo API key not configured.', 'vs-mailer' ),
			);
		}

		$response = wp_remote_get(
			'https://api.brevo.com/v3/account',
			array(
				'headers' => array(
					'api-key' => $api_key,
					'Accept'  => 'application/json',
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
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$name = $body['email'] ?? '';
			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: %s: Brevo account email */
					__( 'Connected as %s.', 'vs-mailer' ),
					$name
				),
			);
		}

		return array(
			'success' => false,
			'message' => sprintf(
				/* translators: %d: HTTP status code */
				__( 'Brevo API error (HTTP %d).', 'vs-mailer' ),
				$status
			),
		);
	}
}
