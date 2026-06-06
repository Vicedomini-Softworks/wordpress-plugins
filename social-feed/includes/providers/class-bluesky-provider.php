<?php
/**
 * Bluesky provider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Feed_Bluesky_Provider extends Social_Feed_Provider {

	protected function get_platform_slug(): string {
		return 'bluesky';
	}

	public function get_display_name(): string {
		return 'Bluesky';
	}

	public function fetch_posts( array $config, int $limit ): array {
		$creds = $this->get_credentials();
		if ( empty( $creds['access_token'] ) ) {
			return array();
		}

		$handle = $creds['account_id'] ?? '';
		if ( empty( $handle ) ) {
			return array();
		}

		$posts = array();
		$url   = add_query_arg(
			array(
				'actor' => $handle,
				'limit' => $limit,
			),
			'https://public.api.bsky.app/xrpc/app.bsky.feed.getAuthorFeed'
		);

		$headers = array(
			'Authorization' => 'Bearer ' . $creds['access_token'],
		);

		$data = $this->http_get( $url, array( 'headers' => $headers ) );

		if ( is_wp_error( $data ) || ! isset( $data['feed'] ) ) {
			return array();
		}

		foreach ( $data['feed'] as $feed_item ) {
			$post   = $feed_item['post'] ?? array();
			$record = $post['record'] ?? array();
			$author = $post['author'] ?? array();

			$caption   = $record['text'] ?? '';
			$media_url = '';

			// Check for images
			if ( isset( $record['embed']['images'] ) ) {
				$media_url = $record['embed']['images'][0]['fullsize'] ?? '';
			}

			$normalized = $this->normalize_post(
				array(
					'id'          => $post['uri'] ?? '',
					'type'        => empty( $media_url ) ? 'text' : 'image',
					'media_url'   => $media_url,
					'permalink'   => 'https://bsky.app/profile/' . $handle . '/post/' . self::extract_rkey( $post['uri'] ?? '' ),
					'caption'     => $caption,
					'username'    => '@' . ( $author['handle'] ?? $handle ),
					'timestamp'   => $record['createdAt'] ?? '',
					'profile_url' => 'https://bsky.app/profile/' . $handle,
				)
			);

			$posts[] = $normalized;
		}

		return $posts;
	}

	private static function extract_rkey( string $uri ): string {
		preg_match( '/\/post\/(.+)$/', $uri, $matches );
		return $matches[1] ?? '';
	}

	public static function get_auth_url( string $state ): string {
		$creds     = get_option( 'social_feed_creds_bluesky', array() );
		$client_id = $creds['client_id'] ?? '';

		$redirect_uri = rest_url( 'social-feed/v1/oauth/bluesky/callback' );

		return add_query_arg(
			array(
				'client_id'     => $client_id,
				'redirect_uri'  => $redirect_uri,
				'response_type' => 'code',
				'scope'         => 'atproto transition:generic',
				'state'         => $state,
			),
			'https://bsky.app/oauth'
		);
	}

	public static function exchange_code( string $code ) {
		$creds = get_option( 'social_feed_creds_bluesky', array() );

		$response = wp_remote_post(
			'https://bsky.app/xrpc/com.atproto.server.createAccessToken',
			array(
				'body' => array(
					'client_id'     => $creds['client_id'] ?? '',
					'client_secret' => $creds['client_secret'] ?? '',
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'redirect_uri'  => rest_url( 'social-feed/v1/oauth/bluesky/callback' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'oauth_error', $body['error_description'] ?? __( 'OAuth authorization failed.', 'social-feed' ) );
		}

		// Get handle
		$handle = self::get_handle( $body['access_token'] ?? '' );

		return array(
			'access_token'  => $body['access_token'] ?? '',
			'refresh_token' => '',
			'expires_in'    => 86400,
			'account_id'    => $handle,
		);
	}

	private static function get_handle( string $token ): string {
		$headers = array(
			'Authorization' => 'Bearer ' . $token,
		);

		$response = wp_remote_get(
			'https://public.api.bsky.app/xrpc/com.atproto.identity.resolveHandle',
			array(
				'headers' => $headers,
			)
		);

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['handle'] ?? '';
	}

	public function get_embed_html( string $url ): string {
		// Bluesky does not have official oEmbed - render simple card
		$handle = str_replace( 'https://bsky.app/profile/', '', $url );
		$rkey   = self::extract_rkey( $url );

		return '<div class="bluesky-embed" data-handle="' . esc_attr( $handle ) . '" data-rkey="' . esc_attr( $rkey ) . '"></div>';
	}

	protected function get_api_endpoint( string $endpoint ): string {
		return 'https://public.api.bsky.app/xrpc/' . $endpoint;
	}
}
