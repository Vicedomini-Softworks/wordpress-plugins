<?php
/**
 * YouTube provider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Feed_YouTube_Provider extends Social_Feed_Provider {

	protected function get_platform_slug(): string {
		return 'youtube';
	}

	public function get_display_name(): string {
		return 'YouTube';
	}

	public function fetch_posts( array $config, int $limit ): array {
		$creds = $this->get_credentials();
		if ( empty( $creds['access_token'] ) ) {
			return array();
		}

		$channel_id = $creds['account_id'] ?? '';
		if ( empty( $channel_id ) ) {
			return array();
		}

		$posts = array();
		$uploads_playlist = self::get_uploads_playlist( $channel_id );

		if ( empty( $uploads_playlist ) ) {
			return array();
		}

		$url = add_query_arg(
			array(
				'part'       => 'snippet,contentDetails',
				'playlistId' => $uploads_playlist,
				'maxResults' => min( $limit, 50 ),
				'key'        => $creds['access_token'],
			),
			'https://www.googleapis.com/youtube/v3/playlistItems'
		);

		$data = $this->http_get( $url );

		if ( is_wp_error( $data ) || ! isset( $data['items'] ) ) {
			return array();
		}

		foreach ( $data['items'] as $item ) {
			$snippet = $item['snippet'] ?? array();
			$video_id = $snippet['resourceId']['videoId'] ?? '';

			$normalized = $this->normalize_post( array(
				'id'          => $video_id,
				'type'        => 'video',
				'media_url'   => $snippet['thumbnails']['high']['url'] ?? '',
				'permalink'   => 'https://youtube.com/watch?v=' . $video_id,
				'caption'     => $snippet['title'] ?? '',
				'username'    => $snippet['channelTitle'] ?? '',
				'timestamp'   => $snippet['publishedAt'] ?? '',
				'profile_url' => 'https://youtube.com/channel/' . $channel_id,
			) );

			$posts[] = $normalized;
		}

		return $posts;
	}

	private static function get_uploads_playlist( string $channel_id ): string {
		// YouTube automatically creates an uploads playlist with UU prefix
		return 'UU' . substr( $channel_id, 2 );
	}

	public static function get_auth_url( string $state ): string {
		$creds = get_option( 'social_feed_creds_youtube', array() );
		$client_id = $creds['client_id'] ?? '';

		$redirect_uri = rest_url( 'social-feed/v1/oauth/youtube/callback' );

		return add_query_arg(
			array(
				'client_id'     => $client_id,
				'redirect_uri'  => $redirect_uri,
				'response_type' => 'code',
				'scope'         => 'https://www.googleapis.com/auth/youtube.readonly',
				'state'         => $state,
				'access_type'   => 'offline',
			),
			'https://accounts.google.com/o/oauth2/v2/auth'
		);
	}

	public static function exchange_code( string $code ) {
		$creds = get_option( 'social_feed_creds_youtube', array() );

		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'headers' => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			'body' => array(
				'client_id'     => $creds['client_id'] ?? '',
				'client_secret' => $creds['client_secret'] ?? '',
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'redirect_uri'  => rest_url( 'social-feed/v1/oauth/youtube/callback' ),
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'oauth_error', $body['error_description'] ?? __( 'OAuth authorization failed.', 'social-feed' ) );
		}

		// Get channel ID
		$channel_id = self::get_channel_id( $body['access_token'] ?? '' );

		return array(
			'access_token'  => $body['access_token'] ?? '',
			'refresh_token' => $body['refresh_token'] ?? '',
			'expires_in'    => $body['expires_in'] ?? 3600,
			'account_id'    => $channel_id,
		);
	}

	private static function get_channel_id( string $token ): string {
		$headers = array(
			'Authorization' => 'Bearer ' . $token,
		);

		$response = wp_remote_get( add_query_arg( array(
			'part' => 'id',
		), 'https://www.googleapis.com/youtube/v3/channels?mine=true' ), array(
			'headers' => $headers,
		) );

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['items'][0]['id'] ?? '';
	}

	public function get_embed_html( string $url ): string {
		$video_id = self::extract_video_id( $url );
		if ( empty( $video_id ) ) {
			return '';
		}

		return '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . esc_attr( $video_id ) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
	}

	private static function extract_video_id( string $url ): string {
		preg_match( '/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s\/]+)/', $url, $matches );
		return $matches[1] ?? '';
	}

	protected function get_api_endpoint( string $endpoint ): string {
		return 'https://www.googleapis.com/youtube/v3/' . $endpoint;
	}

}
