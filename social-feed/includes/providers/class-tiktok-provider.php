<?php
/**
 * TikTok provider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Feed_TikTok_Provider extends Social_Feed_Provider {

	protected function get_platform_slug(): string {
		return 'tiktok';
	}

	public function get_display_name(): string {
		return 'TikTok';
	}

	public function fetch_posts( array $config, int $limit ): array {
		$creds = $this->get_credentials();
		if ( empty( $creds['access_token'] ) ) {
			return array();
		}

		$user_id = $creds['account_id'] ?? '';
		if ( empty( $user_id ) ) {
			return array();
		}

		$posts = array();
		$url   = add_query_arg(
			array(
				'access_token' => $creds['access_token'],
				'user_id'      => $user_id,
				'max_count'    => min( $limit, 60 ),
			),
			'https://open.tiktokapis.com/v2/video/list/'
		);

		$data = $this->http_get( $url );

		if ( is_wp_error( $data ) || ! isset( $data['data'] ) ) {
			return array();
		}

		foreach ( $data['data'] as $raw_post ) {
			$normalized = $this->normalize_post(
				array(
					'id'          => $raw_post['id'] ?? '',
					'type'        => 'video',
					'media_url'   => $raw_post['cover_url'] ?? '',
					'permalink'   => $raw_post['share_url'] ?? '',
					'caption'     => $raw_post['description'] ?? '',
					'username'    => $raw_post['author_username'] ?? '',
					'timestamp'   => $raw_post['create_time'] ?? '',
					'profile_url' => 'https://tiktok.com/@' . ( $raw_post['author_username'] ?? '' ),
				)
			);

			$posts[] = $normalized;
		}

		return $posts;
	}

	public static function get_auth_url( string $state ): string {
		$creds     = get_option( 'social_feed_creds_tiktok', array() );
		$client_id = $creds['client_id'] ?? '';

		$redirect_uri = rest_url( 'social-feed/v1/oauth/tiktok/callback' );

		return add_query_arg(
			array(
				'client_key'    => $client_id,
				'redirect_uri'  => $redirect_uri,
				'response_type' => 'code',
				'scope'         => 'user.info.basic,video.list',
				'state'         => $state,
			),
			'https://www.tiktok.com/v2/auth/authorize/'
		);
	}

	public static function exchange_code( string $code ) {
		$creds = get_option( 'social_feed_creds_tiktok', array() );

		$response = wp_remote_post(
			'https://open.tiktokapis.com/v2/oauth/token/',
			array(
				'body' => array(
					'client_key'    => $creds['client_id'] ?? '',
					'client_secret' => $creds['client_secret'] ?? '',
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'redirect_uri'  => rest_url( 'social-feed/v1/oauth/tiktok/callback' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'oauth_error', $body['error']['message'] ?? __( 'OAuth authorization failed.', 'social-feed' ) );
		}

		$user_info = self::get_user_info( $body['data']['access_token'] ?? '' );

		return array(
			'access_token'  => $body['data']['access_token'] ?? '',
			'refresh_token' => $body['data']['refresh_token'] ?? '',
			'expires_in'    => $body['data']['expires_in'] ?? 86400,
			'account_id'    => $user_info['user_id'] ?? '',
		);
	}

	private static function get_user_info( string $token ): array {
		$response = wp_remote_get(
			add_query_arg(
				array(
					'fields'       => 'open_id,union_id,avatar_url_display,display_name,username',
					'access_token' => $token,
				),
				'https://open.tiktokapis.com/v2/user/info/'
			)
		);

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['data'] ?? array();
	}

	public function get_embed_html( string $url ): string {
		$response = wp_remote_get(
			add_query_arg(
				array(
					'url'    => $url,
					'format' => 'json',
				),
				'https://www.tiktok.com/oembed'
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['html'] ?? '';
	}

	protected function get_api_endpoint( string $endpoint ): string {
		return 'https://open.tiktokapis.com/v2/' . $endpoint;
	}
}
