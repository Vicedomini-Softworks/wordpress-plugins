<?php
/**
 * Instagram provider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Feed_Instagram_Provider extends Social_Feed_Provider {

	protected function get_platform_slug(): string {
		return 'instagram';
	}

	public function get_display_name(): string {
		return 'Instagram';
	}

	public function fetch_posts( array $config, int $limit ): array {
		$creds = $this->get_credentials();
		if ( empty( $creds['access_token'] ) ) {
			return array();
		}

		$account_id = $creds['account_id'] ?? '';
		if ( empty( $account_id ) ) {
			return array();
		}

		$posts = array();
		$url   = add_query_arg(
			array(
				'fields'   => 'id,caption,media_type,media_url,permalink,timestamp,username',
				'limit'    => $limit,
				'access_token' => $creds['access_token'],
			),
			'https://graph.facebook.com/v18.0/' . $account_id . '/media'
		);

		$data = $this->http_get( $url );

		if ( is_wp_error( $data ) || ! isset( $data['data'] ) ) {
			return array();
		}

		foreach ( $data['data'] as $raw_post ) {
			$normalized = $this->normalize_post( array(
				'id'          => $raw_post['id'] ?? '',
				'type'        => $this->map_media_type( $raw_post['media_type'] ?? 'IMAGE' ),
				'media_url'   => $raw_post['media_url'] ?? '',
				'permalink'   => $raw_post['permalink'] ?? '',
				'caption'     => $raw_post['caption'] ?? '',
				'username'    => $raw_post['username'] ?? '',
				'timestamp'   => $raw_post['timestamp'] ?? '',
				'profile_url' => 'https://instagram.com/' . ( $raw_post['username'] ?? '' ),
			) );

			$posts[] = $normalized;
		}

		return $posts;
	}

	private function map_media_type( string $type ): string {
		$map = array(
			'IMAGE'       => 'image',
			'VIDEO'       => 'video',
			'CAROUSEL_ALBUM' => 'carousel',
		);
		return $map[ $type ] ?? 'image';
	}

	public static function get_auth_url( string $state ): string {
		$creds = get_option( 'social_feed_creds_instagram', array() );
		$client_id = $creds['client_id'] ?? '';

		$redirect_uri = rest_url( 'social-feed/v1/oauth/instagram/callback' );

		return add_query_arg(
			array(
				'client_id'     => $client_id,
				'redirect_uri'  => $redirect_uri,
				'response_type' => 'code',
				'scope'         => 'instagram_basic,instagram_content',
				'state'         => $state,
			),
			'https://api.instagram.com/oauth/authorize'
		);
	}

	public static function exchange_code( string $code ) {
		$creds = get_option( 'social_feed_creds_instagram', array() );

		$response = wp_remote_post( 'https://api.instagram.com/oauth/access_token', array(
			'body' => array(
				'client_id'     => $creds['client_id'] ?? '',
				'client_secret' => $creds['client_secret'] ?? '',
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'redirect_uri'  => rest_url( 'social-feed/v1/oauth/instagram/callback' ),
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'oauth_error', $body['error']['message'] ?? __( 'OAuth authorization failed.', 'social-feed' ) );
		}

		// Exchange short-lived token for long-lived token
		$long_lived = self::exchange_short_lived_token( $body['access_token'] ?? '' );

		// Get user ID
		$user_id = self::get_user_id( $long_lived['access_token'] ?? '' );

		return array(
			'access_token'  => $long_lived['access_token'] ?? '',
			'refresh_token' => '',
			'expires_in'    => $long_lived['expires_in'] ?? 5184000,
			'account_id'    => $user_id,
		);
	}

	private static function exchange_short_lived_token( string $token ): array {
		$creds = get_option( 'social_feed_creds_instagram', array() );

		$response = wp_remote_get( add_query_arg( array(
			'grant_type'        => 'ig_exchange_token',
			'client_secret'     => $creds['client_secret'] ?? '',
			'access_token'      => $token,
		), 'https://graph.facebook.com/v18.0/oauth/access_token' ) );

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return array(
			'access_token' => $body['access_token'] ?? $token,
			'expires_in'   => $body['expires_in'] ?? 5184000,
		);
	}

	private static function get_user_id( string $token ): string {
		$response = wp_remote_get( add_query_arg( array(
			'fields'       => 'id',
			'access_token' => $token,
		), 'https://graph.facebook.com/v18.0/me' ) );

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['id'] ?? '';
	}

	public function get_embed_html( string $url ): string {
		$response = wp_remote_get( add_query_arg( array(
			'url'      => $url,
			'format'   => 'json',
		), 'https://api.instagram.com/oembed/' ) );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['html'] ?? '';
	}

	protected function get_api_endpoint( string $endpoint ): string {
		return 'https://graph.facebook.com/v18.0/' . $endpoint;
	}

}
