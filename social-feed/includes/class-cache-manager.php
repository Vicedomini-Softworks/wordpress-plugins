<?php
/**
 * Cache manager for feed data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Feed_Cache_Manager {

	/**
	 * Get cached feed data
	 */
	public static function get( string $feed_slug ): ?array {
		$cache_key = 'social_feed_cache_' . $feed_slug;
		$data      = get_transient( $cache_key );

		return false !== $data ? $data : null;
	}

	/**
	 * Set cached feed data
	 */
	public static function set( string $feed_slug, array $data, int $hours ): bool {
		$cache_key = 'social_feed_cache_' . $feed_slug;
		$expiration = $hours * HOUR_IN_SECONDS;

		return set_transient( $cache_key, $data, $expiration );
	}

	/**
	 * Delete cached feed data
	 */
	public static function delete( string $feed_slug ): bool {
		$cache_key = 'social_feed_cache_' . $feed_slug;
		return delete_transient( $cache_key );
	}

	/**
	 * Clear all cache for a platform
	 */
	public static function clear_platform( string $platform ): void {
		global $wpdb;

		// Clear feed caches
		$pattern = $wpdb->esc_like( 'social_feed_cache_' ) . '%';
		$keys    = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		foreach ( $keys as $key ) {
			delete_option( $key );
		}

		// Clear media cache files
		$cache_dir = wp_upload_dir()['basedir'] . '/social-feed-cache/' . $platform . '/';
		self::clear_directory( $cache_dir );
	}

	/**
	 * Clear media cache files for a specific feed
	 */
	public static function clear_media_cache( string $feed_slug ): void {
		$cache_dir = wp_upload_dir()['basedir'] . '/social-feed-cache/';

		if ( ! is_dir( $cache_dir ) ) {
			return;
		}

		$files = glob( $cache_dir . '*' . DIRECTORY_SEPARATOR . $feed_slug . '*');
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
		}
	}

	/**
	 * Clear a directory of all files
	 */
	private static function clear_directory( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = glob( $dir . '*');
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_dir( $file ) ) {
					self::clear_directory( $file );
					wp_delete_file( $file );
				} else {
					wp_delete_file( $file );
				}
			}
		}
	}

	/**
	 * Get cache age in hours
	 */
	public static function get_cache_age( string $feed_slug ): float {
		$cache_key = 'social_feed_cache_' . $feed_slug;
		$created   = get_option( '_transient_' . $cache_key );

		if ( false === $created ) {
			return 0;
		}

		$age_seconds = time() - $created;
		return round( $age_seconds / HOUR_IN_SECONDS, 1 );
	}

	/**
	 * Check if cache is still valid
	 */
	public static function is_valid( string $feed_slug, int $max_hours ): bool {
		$age = self::get_cache_age( $feed_slug );
		return $age <= $max_hours;
	}

}
