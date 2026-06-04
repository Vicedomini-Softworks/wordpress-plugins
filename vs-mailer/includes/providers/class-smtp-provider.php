<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Mailer_SMTP_Provider extends VS_Mailer_Mail_Provider {

	public static function send( $to, string $subject, string $message, $headers, $attachments ): bool {
		return false;
	}

	public static function configure( PHPMailer\PHPMailer\PHPMailer $phpmailer ): void {
		$host        = get_option( 'vs_mailer_smtp_host', '' );
		$port        = get_option( 'vs_mailer_smtp_port', 587 );
		$encryption  = get_option( 'vs_mailer_smtp_encryption', 'tls' );
		$auth        = get_option( 'vs_mailer_smtp_auth', 'yes' );
		$username    = get_option( 'vs_mailer_smtp_username', '' );
		$password    = vs_secrets_manager_get( 'vs_mailer_smtp_password' ) ?? '';

		if ( empty( $host ) ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host = $host;
		$phpmailer->Port = (int) $port;

		if ( 'none' !== $encryption && ! empty( $encryption ) ) {
			$phpmailer->SMTPSecure = $encryption;
		} else {
			$phpmailer->SMTPSecure = '';
		}

		if ( 'yes' === $auth && ! empty( $username ) ) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $username;
			$phpmailer->Password = $password;
		} else {
			$phpmailer->SMTPAuth = false;
		}

		$phpmailer->From = self::get_default_from_email();
		$phpmailer->FromName = self::get_default_from_name();
	}

	public static function test_connection(): array {
		$host = get_option( 'vs_mailer_smtp_host', '' );
		$port = (int) get_option( 'vs_mailer_smtp_port', 587 );

		if ( empty( $host ) ) {
			return array(
				'success' => false,
				'message' => __( 'SMTP host not configured.', 'vs-mailer' ),
			);
		}

		$connection = @fsockopen( $host, $port, $errno, $errstr, 10 );

		if ( $connection ) {
			fclose( $connection );
			return array(
				'success' => true,
				'message' => sprintf(
					__( 'Successfully connected to %s:%d.', 'vs-mailer' ),
					$host,
					$port
				),
			);
		}

		return array(
			'success' => false,
			'message' => sprintf(
				__( 'Connection to %s:%d failed: %s', 'vs-mailer' ),
				$host,
				$port,
				$errstr
			),
		);
	}
}
