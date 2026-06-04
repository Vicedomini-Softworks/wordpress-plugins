<?php
/**
 * Activation handler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Feed_Activator {

	public static function activate() {
		self::create_cache_directories();
		self::register_roles();
		flush_rewrite_rules();
	}

	private static function create_cache_directories() {
		$upload_dir = wp_upload_dir();
		$cache_dir  = $upload_dir['basedir'] . '/social-feed-cache/';

		if ( ! file_exists( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
		}

		$platforms = array( 'instagram', 'facebook', 'tiktok', 'x', 'threads', 'bluesky', 'youtube' );
		foreach ( $platforms as $platform ) {
			$platform_dir = $cache_dir . $platform . '/';
			if ( ! file_exists( $platform_dir ) ) {
				wp_mkdir_p( $platform_dir );
			}
		}

		// Add .htaccess to prevent direct access to cached files
		$htaccess_content = 'Options -Indexes
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteRule ^ - [F]
</IfModule>';
		$htaccess_path = $cache_dir . '.htaccess';
		if ( ! file_exists( $htaccess_path ) ) {
			file_put_contents( $htaccess_path, $htaccess_content );
		}
	}

	private static function register_roles() {
		// No custom roles needed - uses existing admin capabilities
	}

}
