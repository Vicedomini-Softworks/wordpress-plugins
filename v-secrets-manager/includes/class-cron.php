<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Secrets_Manager_Cron {

	public static function schedule(): void {
		if ( ! wp_next_scheduled( 'vs_secrets_manager_rotate_secrets' ) ) {
			wp_schedule_event( time(), 'daily', 'vs_secrets_manager_rotate_secrets' );
		}
	}

	public static function rotate_secrets(): void {
		global $wpdb;

		$secrets = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}vsecrets_secrets WHERE status = 'active' AND last_rotated IS NOT NULL"
		);

		foreach ( $secrets as $secret ) {
			do_action( 'vs_secrets_manager_rotate_single', $secret->name, $secret->provider );
		}
	}
}
