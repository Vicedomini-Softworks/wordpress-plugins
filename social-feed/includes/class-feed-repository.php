<?php
/**
 * Feed repository - CRUD for feed configurations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Feed_Feed_Repository {

	private const OPTION_NAME = 'social_feed_feeds';

	/**
	 * Get all feeds
	 */
	public static function get_all(): array {
		$feeds = get_option( self::OPTION_NAME, array() );
		return is_array( $feeds ) ? $feeds : array();
	}

	/**
	 * Get a single feed by slug
	 */
	public static function get( string $slug ): ?array {
		$feeds = self::get_all();
		return isset( $feeds[ $slug ] ) ? $feeds[ $slug ] : null;
	}

	/**
	 * Save a feed
	 */
	public static function save( array $data ): bool {
		$slug = sanitize_key( $data['slug'] ?? '' );
		if ( empty( $slug ) ) {
			return false;
		}

		$feeds = self::get_all();

		$feeds[ $slug ] = array(
			'slug'        => $slug,
			'platform'    => sanitize_key( $data['platform'] ?? 'instagram' ),
			'mode'        => in_array( $data['mode'] ?? '', array( 'oauth', 'embed' ), true ) ? $data['mode'] : 'embed',
			'account'     => sanitize_text_field( $data['account'] ?? '' ),
			'display'     => self::sanitize_display( $data['display'] ?? array() ),
			'cache_hours' => max( 4, min( 48, intval( $data['cache_hours'] ?? 8 ) ) ),
		);

		return update_option( self::OPTION_NAME, $feeds, false );
	}

	/**
	 * Delete a feed
	 */
	public static function delete( string $slug ): bool {
		$feeds = self::get_all();
		if ( ! isset( $feeds[ $slug ] ) ) {
			return false;
		}

		unset( $feeds[ $slug ] );
		return update_option( self::OPTION_NAME, $feeds, false );
	}

	/**
	 * Check if a slug exists
	 */
	public static function exists( string $slug ): bool {
		$feeds = self::get_all();
		return isset( $feeds[ $slug ] );
	}

	/**
	 * Get available slugs
	 */
	public static function get_slugs(): array {
		return array_keys( self::get_all() );
	}

	/**
	 * Sanitize display settings
	 */
	private static function sanitize_display( array $display ): array {
		$defaults = array(
			'type'           => 'grid',
			'limit'          => 8,
			'theme'          => 'light',
			'show_media'     => true,
			'show_caption'   => true,
			'show_username'  => true,
			'show_timestamp' => true,
			'link_posts'     => true,
		);

		return array_merge(
			$defaults,
			array_filter(
				$display,
				function ( $value ) {
					return null !== $value;
				}
			)
		);
	}

	/**
	 * Validate feed data before save
	 */
	public static function validate( array $data ): array {
		$errors = array();

		if ( empty( $data['slug'] ) || ! preg_match( '/^[a-z0-9-]+$/', $data['slug'] ) ) {
			$errors[] = __( 'Invalid slug. Use lowercase letters, numbers, and hyphens only.', 'social-feed' );
		}

		$platforms = array( 'instagram', 'facebook', 'tiktok', 'x', 'threads', 'bluesky', 'youtube' );
		if ( ! in_array( $data['platform'] ?? '', $platforms, true ) ) {
			$errors[] = __( 'Invalid platform.', 'social-feed' );
		}

		if ( isset( $data['display']['limit'] ) ) {
			$limit = intval( $data['display']['limit'] );
			if ( $limit < 1 || $limit > 48 ) {
				$errors[] = __( 'Limit must be between 1 and 48.', 'social-feed' );
			}
		}

		if ( isset( $data['cache_hours'] ) ) {
			$cache = intval( $data['cache_hours'] );
			if ( $cache < 4 || $cache > 48 ) {
				$errors[] = __( 'Cache duration must be between 4 and 48 hours.', 'social-feed' );
			}
		}

		return $errors;
	}
}
