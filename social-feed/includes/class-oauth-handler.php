<?php
/**
 * OAuth handler - manages OAuth flows for all platforms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Feed_OAuth_Handler {

	/**
	 * Register OAuth routes
	 */
	public static function register_routes(): void {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'social-feed/v1',
					'/oauth/(?P<platform>[\w]+)/authorize',
					array(
						'methods'             => 'GET',
						'callback'            => array( __CLASS__, 'handle_authorize' ),
						'permission_callback' => '__return_true',
						'args'                => array(
							'platform' => array(
								'validate_callback' => function ( $param ) {
									return in_array( $param, array( 'instagram', 'facebook', 'tiktok', 'x', 'threads', 'bluesky', 'youtube' ), true );
								},
							),
						),
					)
				);

				register_rest_route(
					'social-feed/v1',
					'/oauth/(?P<platform>[\w]+)/callback',
					array(
						'methods'             => 'GET',
						'callback'            => array( __CLASS__, 'handle_callback' ),
						'permission_callback' => '__return_true',
						'args'                => array(
							'platform' => array(
								'validate_callback' => function ( $param ) {
									return in_array( $param, array( 'instagram', 'facebook', 'tiktok', 'x', 'threads', 'bluesky', 'youtube' ), true );
								},
							),
						),
					)
				);
			}
		);
	}

	/**
	 * Handle authorization redirect
	 */
	public static function handle_authorize( WP_REST_Request $request ): WP_REST_Response {
		$platform = sanitize_key( $request->get_param( 'platform' ) );

		// Generate state nonce
		$state = wp_generate_uuid4();
		set_transient( 'social_feed_oauth_state_' . $state, $platform, 300 ); // 5 minute expiry

		$provider_class = self::get_provider_class( $platform );
		if ( ! $provider_class ) {
			return new WP_REST_Response( array( 'error' => __( 'Invalid platform.', 'social-feed' ) ), 400 );
		}

		$auth_url = $provider_class::get_auth_url( $state );

		return new WP_REST_Response(
			array(
				'success' => true,
				'url'     => $auth_url,
			),
			200
		);
	}

	/**
	 * Handle OAuth callback
	 */
	public static function handle_callback( WP_REST_Request $request ): WP_REST_Response {
		$platform = sanitize_key( $request->get_param( 'platform' ) );
		$code     = $request->get_param( 'code' );
		$state    = $request->get_param( 'state' );
		$error    = $request->get_param( 'error' );

		if ( $error ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $error,
				),
				400
			);
		}

		if ( empty( $code ) || empty( $state ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'Missing authorization code or state parameter.', 'social-feed' ),
				),
				400
			);
		}

		// Verify state
		$stored_platform = get_transient( 'social_feed_oauth_state_' . $state );
		if ( ! $stored_platform || $stored_platform !== $platform ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'Invalid OAuth state. Please try connecting again.', 'social-feed' ),
				),
				400
			);
		}

		delete_transient( 'social_feed_oauth_state_' . $state );

		$provider_class = self::get_provider_class( $platform );
		if ( ! $provider_class ) {
			return new WP_REST_Response( array( 'error' => __( 'Invalid platform.', 'social-feed' ) ), 400 );
		}

		// Exchange code for tokens
		$result = $provider_class::exchange_code( $code );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result->get_error_message(),
				),
				400
			);
		}

		// Save credentials
		Social_Feed_Provider::save_credentials( $platform, $result );

		return new WP_REST_Response(
			array(
				'success'  => true,
				'message'  => __( 'Account connected successfully.', 'social-feed' ),
				'redirect' => admin_url( 'admin.php?page=social-feed-platform-settings&platform=' . $platform . '&connected=1' ),
			),
			200
		);
	}

	/**
	 * Get provider class for platform
	 */
	private static function get_provider_class( string $platform ): ?string {
		$map = array(
			'instagram' => 'Social_Feed_Instagram_Provider',
			'facebook'  => 'Social_Feed_Facebook_Provider',
			'tiktok'    => 'Social_Feed_TikTok_Provider',
			'x'         => 'Social_Feed_X_Provider',
			'threads'   => 'Social_Feed_Threads_Provider',
			'bluesky'   => 'Social_Feed_Bluesky_Provider',
			'youtube'   => 'Social_Feed_YouTube_Provider',
		);

		$class = $map[ $platform ] ?? null;
		return $class && class_exists( $class ) ? $class : null;
	}

}
