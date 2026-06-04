<?php
/**
 * Media downloader - fetches remote images to local cache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Feed_Media_Downloader {

	/**
	 * Download remote image to local cache
	 *
	 * @param string $url Remote image URL
	 * @param string $platform Platform name
	 * @param string $feed_slug Feed identifier
	 * @return array|false {
	 *     @type string $local_url Local URL
	 *     @type string $local_path Local path
	 *     @type string $filename Filename
	 * }
	 */
	public static function download( string $url, string $platform, string $feed_slug ) {
		// Validate URL
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			error_log( 'Social Feed: Invalid URL for download: ' . $url );
			return false;
		}

		// Check if already cached
		$cache_key = 'social_feed_media_' . md5( $url );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		// Download file
		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			error_log( 'Social Feed: Failed to download image: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			error_log( 'Social Feed: Image download failed with status ' . $code );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			error_log( 'Social Feed: Empty image response' );
			return false;
		}

		// Generate filename
		$filename = sanitize_file_name( $feed_slug . '-' . md5( $url ) . '.' . self::get_extension( $url, $response ) );

		// Save to upload directory
		$upload_dir = wp_upload_dir();
		$cache_dir  = $upload_dir['basedir'] . '/social-feed-cache/' . $platform . '/';

		if ( ! file_exists( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
		}

		$file_path = $cache_dir . $filename;

		if ( false === file_put_contents( $file_path, $body ) ) {
			error_log( 'Social Feed: Failed to save image to ' . $file_path );
			return false;
		}

		$local_url = $upload_dir['baseurl'] . '/social-feed-cache/' . $platform . '/' . $filename;

		$result = array(
			'local_url'  => $local_url,
			'local_path' => $file_path,
			'filename'   => $filename,
		);

		// Cache the result for 24 hours
		set_transient( $cache_key, $result, DAY_IN_SECONDS );

		return $result;
	}

	/**
	 * Get file extension from URL or content type
	 */
	private static function get_extension( string $url, array $response ): string {
		// Try to get from URL first
		$path = parse_url( $url, PHP_URL_PATH );
		if ( $path ) {
			$ext = pathinfo( $path, PATHINFO_EXTENSION );
			if ( in_array( strtolower( $ext ), array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov' ), true ) ) {
				return $ext;
			}
		}

		// Fall back to content type
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		switch ( $content_type ) {
			case 'image/jpeg':
				return 'jpg';
			case 'image/png':
				return 'png';
			case 'image/gif':
				return 'gif';
			case 'image/webp':
				return 'webp';
			case 'video/mp4':
				return 'mp4';
			case 'video/quicktime':
				return 'mov';
			default:
				return 'jpg';
		}
	}

	/**
	 * Get local URL for media, downloading if necessary
	 */
	public static function get_or_download( string $url, string $platform, string $feed_slug ) {
		// If already local, return as-is
		if ( false !== strpos( $url, site_url() ) || false !== strpos( $url, content_url() ) ) {
			return array(
				'local_url'  => $url,
				'local_path' => str_replace( site_url(), ABSPATH, $url ),
				'filename'   => basename( $url ),
			);
		}

		return self::download( $url, $platform, $feed_slug );
	}

}
