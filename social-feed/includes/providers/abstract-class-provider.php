<?php
/**
 * Abstract provider base class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Social_Feed_Provider {

	protected string $platform;

	public function __construct() {
		$this->platform = $this->get_platform_slug();
	}

	/**
	 * Get platform slug
	 */
	abstract protected function get_platform_slug(): string;

	/**
	 * Get display name for platform
	 */
	abstract public function get_display_name(): string;

	/**
	 * Fetch posts from platform (OAuth mode)
	 *
	 * @param array $config Feed configuration
	 * @param int $limit Max posts to fetch
	 * @return array Normalized post format
	 */
	abstract public function fetch_posts( array $config, int $limit ): array;

	/**
	 * Get OAuth authorization URL
	 *
	 * @param string $state State nonce
	 * @return string Authorization URL
	 */
	abstract public static function get_auth_url( string $state ): string;

	/**
	 * Exchange authorization code for tokens
	 *
	 * @param string $code Authorization code
	 * @return array { access_token, refresh_token, expires_in, account_id }
	 */
	/**
	 * @return array|WP_Error
	 */
	abstract public static function exchange_code( string $code );

	/**
	 * Get oEmbed HTML for a post/profile URL
	 *
	 * @param string $url Post or profile URL
	 * @return string HTML embed code
	 */
	abstract public function get_embed_html( string $url ): string;

	/**
	 * Get credentials for this platform.
	 * Sensitive fields fetched from VS Secrets Manager; metadata from wp_options.
	 */
	protected function get_credentials(): array {
		$p    = $this->platform;
		$meta = get_option( 'social_feed_meta_' . $p, array() );

		return array(
			'client_id'     => vs_secrets_manager_get( 'social_feed_' . $p . '_client_id' ) ?? '',
			'client_secret' => vs_secrets_manager_get( 'social_feed_' . $p . '_client_secret' ) ?? '',
			'access_token'  => vs_secrets_manager_get( 'social_feed_' . $p . '_access_token' ) ?? '',
			'refresh_token' => vs_secrets_manager_get( 'social_feed_' . $p . '_refresh_token' ) ?? '',
			'token_expiry'  => $meta['token_expiry'] ?? 0,
			'connected_at'  => $meta['connected_at'] ?? 0,
			'account_id'    => $meta['account_id'] ?? '',
			'cache_reset_at'=> $meta['cache_reset_at'] ?? 0,
		);
	}

	/**
	 * Save OAuth tokens after successful exchange.
	 * Sensitive tokens → VS Secrets Manager; metadata → wp_options.
	 */
	public static function save_credentials( string $platform, array $tokens ): bool {
		$sensitive = array(
			'access_token'  => $tokens['access_token'] ?? '',
			'refresh_token' => $tokens['refresh_token'] ?? '',
		);

		foreach ( $sensitive as $field => $value ) {
			if ( ! empty( $value ) ) {
				VS_Secrets_Manager_Secret_Manager::set(
					'social_feed_' . $platform . '_' . $field,
					$value,
					array( 'title' => 'Social Feed ' . $platform . ' ' . $field, 'provider' => 'db' )
				);
			}
		}

		$meta_option = 'social_feed_meta_' . $platform;
		$current_meta = get_option( $meta_option, array() );

		return update_option(
			$meta_option,
			array(
				'token_expiry'  => time() + ( $tokens['expires_in'] ?? 3600 ),
				'connected_at'  => time(),
				'account_id'    => $tokens['account_id'] ?? '',
				'cache_reset_at'=> $current_meta['cache_reset_at'] ?? 0,
			),
			false
		);
	}

	/**
	 * Check if connected via OAuth
	 */
	public function is_connected(): bool {
		$meta  = get_option( 'social_feed_meta_' . $this->platform, array() );
		$token = vs_secrets_manager_get( 'social_feed_' . $this->platform . '_access_token' );
		return ! empty( $token ) && ( $meta['token_expiry'] ?? 0 ) > time();
	}

	/**
	 * Normalize post to standard format
	 */
	protected function normalize_post( array $raw_post ): array {
		return array(
			'id'           => $raw_post['id'] ?? '',
			'platform'     => $this->platform,
			'type'         => $raw_post['type'] ?? 'image',
			'media_url'    => $raw_post['media_url'] ?? '',
			'original_url' => $raw_post['permalink'] ?? '',
			'caption'      => $raw_post['caption'] ?? '',
			'username'     => $raw_post['username'] ?? '',
			'timestamp'    => $raw_post['timestamp'] ?? '',
			'profile_url'  => $raw_post['profile_url'] ?? '',
			'embed_html'   => '',
		);
	}

	/**
	 * Make HTTP request with error handling
	 *
	 * @return array|WP_Error
	 */
	protected function http_get( string $url, array $args = array() ) {
		$defaults = array(
			'timeout' => 15,
			'headers' => array(),
		);

		$response = wp_remote_get( $url, wp_parse_args( $args, $defaults ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 ) {
			$body = wp_remote_retrieve_body( $response );
			error_log( 'Social Feed HTTP Error ' . $code . ' from ' . $url . ': ' . $body );
			return new WP_Error(
				'http_error',
				/* translators: %d: HTTP status code */
				sprintf( __( 'HTTP error %d from the platform API.', 'social-feed' ), $code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return is_array( $data ) ? $data : $body;
	}

	/**
	 * Get platform-specific API endpoint
	 */
	abstract protected function get_api_endpoint( string $endpoint ): string;

}
