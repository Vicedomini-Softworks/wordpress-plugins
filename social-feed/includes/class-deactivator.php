<?php
/**
 * Deactivation handler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Feed_Deactivator {

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
