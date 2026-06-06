<?php
/**
 * Facebook provider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Feed_Facebook_Provider extends Social_Feed_Provider {

	protected function get_platform_slug(): string {
		return 'facebook';
	}

	public function get_display_name(): string {
		return 'Facebook';
	}

	public function fetch_posts( array $config, int $limit ): array {
		$creds = $this->get_credentials();
		if ( empty( $creds['access_token'] ) ) {
			return array();
		}

		$page_id = $creds['account_id'] ?? '';
		if ( empty( $page_id ) ) {
			return array();
		}

		$posts = array();
		$url   = add_query_arg(
			array(
				'fields'       => 'id,message,created_time,full_picture,permalink_url,type,from',
				'limit'        => $limit,
				'access_token' => $creds['access_token'],
			),
			'https://graph.facebook.com/v18.0/' . $page_id . '/posts'
		);

		$data = $this->http_get( $url );

		if ( is_wp_error( $data ) || ! isset( $data['data'] ) ) {
			return array();
		}

		foreach ( $data['data'] as $raw_post ) {
			$normalized = $this->normalize_post(
				array(
					'id'          => $raw_post['id'] ?? '',
					'type'        => $this->map_post_type( $raw_post['type'] ?? 'STATUS' ),
					'media_url'   => $raw_post['full_picture'] ?? '',
					'permalink'   => $raw_post['permalink_url'] ?? '',
					'caption'     => $raw_post['message'] ?? '',
					'username'    => $raw_post['from']['name'] ?? '',
					'timestamp'   => $raw_post['created_time'] ?? '',
					'profile_url' => 'https://facebook.com/' . ( $raw_post['from']['id'] ?? '' ),
				)
			);

			$posts[] = $normalized;
		}

		return $posts;
	}

	private function map_post_type( string $type ): string {
		$map = array(
			'PHOTO'  => 'image',
			'VIDEO'  => 'video',
			'LINK'   => 'link',
			'STATUS' => 'text',
			'COVER'  => 'image',
			'OFFER'  => 'link',
		);
		return $map[ $type ] ?? 'link';
	}

	public static function get_auth_url( string $state ): string {
		$creds     = get_option( 'social_feed_creds_facebook', array() );
		$client_id = $creds['client_id'] ?? '';

		$redirect_uri = rest_url( 'social-feed/v1/oauth/facebook/callback' );

		return add_query_arg(
			array(
				'client_id'     => $client_id,
				'redirect_uri'  => $redirect_uri,
				'response_type' => 'code',
				'scope'         => 'pages_read_engagement,pages_read_user_content',
				'state'         => $state,
			),
			'https://www.facebook.com/v18.0/dialog/oauth'
		);
	}

	public static function exchange_code( string $code ) {
		$creds = get_option( 'social_feed_creds_facebook', array() );

		// Step 1: Exchange code for short-lived token
		$response = wp_remote_get(
			add_query_arg(
				array(
					'client_id'     => $creds['client_id'] ?? '',
					'client_secret' => $creds['client_secret'] ?? '',
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'redirect_uri'  => rest_url( 'social-feed/v1/oauth/facebook/callback' ),
				),
				'https://graph.facebook.com/v18.0/oauth/access_token'
			)
		);

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'oauth_error', $body['error']['message'] ?? __( 'OAuth authorization failed.', 'social-feed' ) );
		}

		$short_lived_token = $body['access_token'] ?? '';

		// Step 2: Exchange for long-lived token
		$long_lived = self::exchange_for_long_lived( $short_lived_token );

		// Step 3: Get page token (user must have selected a page)
		$page_token = self::get_page_token( $long_lived['access_token'] ?? '' );

		return array(
			'access_token'  => $page_token['access_token'] ?? $long_lived['access_token'],
			'refresh_token' => '',
			'expires_in'    => $long_lived['expires_in'] ?? 5184000,
			'account_id'    => $page_token['page_id'] ?? '',
		);
	}

	private static function exchange_for_long_lived( string $token ): array {
		$creds = get_option( 'social_feed_creds_facebook', array() );

		$response = wp_remote_get(
			add_query_arg(
				array(
					'grant_type'        => 'fb_exchange_token',
					'client_id'         => $creds['client_id'] ?? '',
					'client_secret'     => $creds['client_secret'] ?? '',
					'fb_exchange_token' => $token,
				),
				'https://graph.facebook.com/v18.0/oauth/access_token'
			)
		);

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return array(
			'access_token' => $body['access_token'] ?? $token,
			'expires_in'   => $body['expires_in'] ?? 5184000,
		);
	}

	private static function get_page_token( string $user_token ): array {
		$response = wp_remote_get(
			add_query_arg(
				array(
					'fields'       => 'id,access_token',
					'access_token' => $user_token,
				),
				'https://graph.facebook.com/v18.0/me/accounts'
			)
		);

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['data'][0] ) ) {
			return array();
		}

		return $body['data'][0];
	}

	public function get_embed_html( string $url ): string {
		$response = wp_remote_get(
			add_query_arg(
				array(
					'url'    => $url,
					'format' => 'json',
				),
				'https://www.facebook.com/plugins/post/oembed.json/'
			)
		);

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
