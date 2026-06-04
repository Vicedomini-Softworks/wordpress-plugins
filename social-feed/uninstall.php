<?php
/**
 * Uninstall - fires when plugin is deleted from WP admin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$platforms = array( 'instagram', 'facebook', 'tiktok', 'x', 'threads', 'bluesky', 'youtube' );

// Remove all plugin options
delete_option( 'social_feed_feeds' );
foreach ( $platforms as $platform ) {
	delete_option( 'social_feed_meta_' . $platform );
}

// Remove secrets from VS Secrets Manager (if active)
if ( function_exists( 'vs_secrets_manager_get' ) && class_exists( 'VS_Secrets_Manager_Secret_Manager' ) ) {
	$secret_fields = array( 'client_id', 'client_secret', 'access_token', 'refresh_token' );
	foreach ( $platforms as $platform ) {
		foreach ( $secret_fields as $field ) {
			VS_Secrets_Manager_Secret_Manager::delete( 'social_feed_' . $platform . '_' . $field );
		}
	}
}

// Remove all transients
global $wpdb;
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_social_feed_%' OR option_name LIKE '_transient_timeout_social_feed_%'"
);

// Remove media cache directory
$upload_dir = wp_upload_dir();
$cache_dir  = $upload_dir['basedir'] . '/social-feed-cache/';

if ( is_dir( $cache_dir ) ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $cache_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $path ) {
		if ( $path->isFile() ) {
			wp_delete_file( $path->getPathname() );
		} elseif ( $path->isDir() ) {
			rmdir( $path->getPathname() );
		}
	}

	rmdir( $cache_dir );
}
