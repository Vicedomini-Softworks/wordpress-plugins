<?php
/**
 * X (Twitter) provider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Feed_X_Provider extends Social_Feed_Provider {

	protected function get_platform_slug(): string {
		return 'x';
	}

	public function get_display_name(): string {
		return 'X';
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
				'max_results'  => min( $limit, 100 ),
				'expansions'   => 'author_id',
				'tweet.fields' => 'created_at,public_metrics,entities',
			),
			'https://api.x.com/2/users/' . $user_id . '/tweets'
		);

		$headers = array(
			'Authorization' => 'Bearer ' . $creds['access_token'],
		);

		$data = $this->http_get( $url, array( 'headers' => $headers ) );

		if ( is_wp_error( $data ) || ! isset( $data['data'] ) ) {
			return array();
		}

		$users    = isset( $data['includes']['users'][0] ) ? $data['includes']['users'][0] : null;
		$username = $users['username'] ?? '';

		foreach ( $data['data'] as $raw_post ) {
			$media_url = '';
			if ( isset( $raw_post['entities']['media'][0] ) ) {
				$media_url = $raw_post['entities']['media'][0]['url'] ?? '';
			}

			$normalized = $this->normalize_post(
				array(
					'id'          => $raw_post['id'] ?? '',
					'type'        => empty( $media_url ) ? 'text' : 'image',
					'media_url'   => $media_url,
					'permalink'   => 'https://x.com/' . $username . '/status/' . $raw_post['id'],
					'caption'     => $raw_post['text'] ?? '',
					'username'    => '@' . $username,
					'timestamp'   => $raw_post['created_at'] ?? '',
					'profile_url' => 'https://x.com/' . $username,
				)
			);

			$posts[] = $normalized;
		}

		return $posts;
	}

	public static function get_auth_url( string $state ): string {
		$creds     = get_option( 'social_feed_creds_x', array() );
		$client_id = $creds['client_id'] ?? '';

		$redirect_uri = rest_url( 'social-feed/v1/oauth/x/callback' );

		return add_query_arg(
			array(
				'client_id'     => $client_id,
				'redirect_uri'  => $redirect_uri,
				'response_type' => 'code',
				'scope'         => 'tweet.read users.read offline.access',
				'state'         => $state,
			),
			'https://twitter.com/i/oauth2/authorize'
		);
	}

	public static function exchange_code( string $code ) {
		$creds = get_option( 'social_feed_creds_x', array() );

		$redirect_uri = rest_url( 'social-feed/v1/oauth/x/callback' );

		$response = wp_remote_post(
			'https://api.x.com/2/oauth2/token',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode(
						$creds['client_id'] . ':' . $creds['client_secret']
					),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'code'          => $code,
					'grant_type'    => 'authorization_code',
					'code_verifier' => 'verifier',
					'redirect_uri'  => $redirect_uri,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['errors'] ) ) {
			return new WP_Error( 'oauth_error', $body['errors'][0]['message'] ?? __( 'OAuth authorization failed.', 'social-feed' ) );
		}

		$user_info = self::get_user_info( $body['access_token'] ?? '' );

		return array(
			'access_token'  => $body['access_token'] ?? '',
			'refresh_token' => $body['refresh_token'] ?? '',
			'expires_in'    => $body['expires_in'] ?? 7200,
			'account_id'    => $user_info['id'] ?? '',
		);
	}

	private static function get_user_info( string $token ): array {
		$headers = array(
			'Authorization' => 'Bearer ' . $token,
		);

		$response = wp_remote_get(
			'https://api.x.com/2/users/me',
			array(
				'headers' => $headers,
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
				'https://publish.twitter.com/oembed'
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['html'] ?? '';
	}

	protected function get_api_endpoint( string $endpoint ): string {
		return 'https://api.x.com/2/' . $endpoint;
	}
}
