<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Secrets_Manager_Deactivator {

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'vs_secrets_manager_rotate_secrets' );
	}
}
