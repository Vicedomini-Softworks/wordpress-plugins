<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Mailer_Logger {

	const MAX_LOG_ENTRIES = 100;

	public static function log( string $to, string $subject, string $mailer, bool $success, string $error_message = '' ): void {
		if ( 'yes' !== get_option( 'vs_mailer_log_emails', 'no' ) ) {
			return;
		}

		$log   = get_option( 'vs_mailer_log', array() );
		$entry = array(
			'to'            => $to,
			'subject'       => $subject,
			'mailer'        => $mailer,
			'success'       => $success,
			'error_message' => $error_message,
			'time'          => current_time( 'mysql' ),
		);

		array_unshift( $log, $entry );

		if ( count( $log ) > self::MAX_LOG_ENTRIES ) {
			$log = array_slice( $log, 0, self::MAX_LOG_ENTRIES );
		}

		update_option( 'vs_mailer_log', $log, false );
	}

	public static function get_log(): array {
		return get_option( 'vs_mailer_log', array() );
	}

	public static function clear(): void {
		update_option( 'vs_mailer_log', array(), false );
	}

	public static function is_enabled(): bool {
		return 'yes' === get_option( 'vs_mailer_log_emails', 'no' );
	}
}
