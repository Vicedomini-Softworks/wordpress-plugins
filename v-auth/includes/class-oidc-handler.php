<?php
/**
 * OIDC handler — drives the authorization-code flow via REST routes.
 *
 * Modeled on Social Feed's OAuth handler: a `state` nonce is minted and
 * stashed in a transient before redirecting to the provider, then verified
 * on callback before the code is exchanged for tokens. ID token signature
 * verification uses the provider's published JWKS via firebase/php-jwt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class V_Auth_OIDC_Handler {

	const STATE_TRANSIENT_PREFIX = 'v_auth_oidc_state_';
	const STATE_TTL              = 300; // 5 minutes.

	public static function register_routes(): void {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'v-auth/v1',
					'/oidc/(?P<provider>[\w-]+)/authorize',
					array(
						'methods'             => 'GET',
						'callback'            => array( __CLASS__, 'handle_authorize' ),
						'permission_callback' => '__return_true',
					)
				);

				register_rest_route(
					'v-auth/v1',
					'/oidc/(?P<provider>[\w-]+)/callback',
					array(
						'methods'             => 'GET',
						'callback'            => array( __CLASS__, 'handle_callback' ),
						'permission_callback' => '__return_true',
					)
				);
			}
		);
	}

	/**
	 * Build the redirect URL for the start of the flow and send the browser there.
	 */
	public static function handle_authorize( WP_REST_Request $request ) {
		$provider_id = sanitize_key( $request->get_param( 'provider' ) );
		$provider    = V_Auth_Provider_Store::get( $provider_id );

		if ( ! $provider ) {
			return new WP_REST_Response( array( 'error' => __( 'Unknown identity provider.', 'v-auth' ) ), 404 );
		}

		$discovery = self::discover( $provider['issuer'] );
		if ( is_wp_error( $discovery ) ) {
			return new WP_REST_Response( array( 'error' => $discovery->get_error_message() ), 502 );
		}

		$state = wp_generate_uuid4();
		$nonce = wp_generate_uuid4();
		set_transient(
			self::STATE_TRANSIENT_PREFIX . $state,
			array(
				'provider' => $provider_id,
				'nonce'    => $nonce,
			),
			self::STATE_TTL
		);

		$authorize_url = add_query_arg(
			array(
				'client_id'     => rawurlencode( $provider['client_id'] ),
				'redirect_uri'  => rawurlencode( self::redirect_uri( $provider_id ) ),
				'response_type' => 'code',
				'scope'         => rawurlencode( $provider['scopes'] ?? 'openid email profile' ),
				'state'         => $state,
				'nonce'         => $nonce,
			),
			$discovery['authorization_endpoint']
		);

		wp_redirect( $authorize_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Handle the redirect back from the provider: verify state, exchange the
	 * code for tokens, validate the ID token, and hand claims to the user mapper.
	 */
	public static function handle_callback( WP_REST_Request $request ) {
		$provider_id  = sanitize_key( $request->get_param( 'provider' ) );
		$code         = $request->get_param( 'code' );
		$state        = $request->get_param( 'state' );
		$provider_err = $request->get_param( 'error' );

		if ( $provider_err ) {
			self::redirect_to_login( array( 'v_auth_error' => sanitize_text_field( (string) $provider_err ) ) );
		}

		if ( empty( $code ) || empty( $state ) ) {
			self::redirect_to_login( array( 'v_auth_error' => 'missing_code' ) );
		}

		$stored = get_transient( self::STATE_TRANSIENT_PREFIX . $state );
		if ( ! is_array( $stored ) || ( $stored['provider'] ?? '' ) !== $provider_id ) {
			self::redirect_to_login( array( 'v_auth_error' => 'invalid_state' ) );
		}
		delete_transient( self::STATE_TRANSIENT_PREFIX . $state );

		$provider = V_Auth_Provider_Store::get( $provider_id );
		if ( ! $provider ) {
			self::redirect_to_login( array( 'v_auth_error' => 'unknown_provider' ) );
		}

		$discovery = self::discover( $provider['issuer'] );
		if ( is_wp_error( $discovery ) ) {
			self::redirect_to_login( array( 'v_auth_error' => 'discovery_failed' ) );
		}

		$tokens = self::exchange_code( $discovery['token_endpoint'], $provider_id, $provider, (string) $code );
		if ( is_wp_error( $tokens ) ) {
			self::redirect_to_login( array( 'v_auth_error' => 'token_exchange_failed' ) );
		}

		$claims = self::validate_id_token(
			(string) ( $tokens['id_token'] ?? '' ),
			$discovery,
			$provider,
			(string) $stored['nonce']
		);
		if ( is_wp_error( $claims ) ) {
			self::redirect_to_login( array( 'v_auth_error' => 'invalid_id_token' ) );
		}

		$user = V_Auth_User_Mapper::login_from_claims( $provider_id, $claims );
		if ( is_wp_error( $user ) ) {
			self::redirect_to_login( array( 'v_auth_error' => 'login_failed' ) );
		}

		wp_redirect( admin_url() ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Fetch and cache the provider's OpenID discovery document.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private static function discover( string $issuer ) {
		$cache_key = 'v_auth_discovery_' . md5( $issuer );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get( trailingslashit( $issuer ) . '.well-known/openid-configuration', array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['authorization_endpoint'] ) || empty( $body['token_endpoint'] ) ) {
			return new WP_Error( 'v_auth_discovery_invalid', __( 'The identity provider returned an invalid discovery document.', 'v-auth' ) );
		}

		set_transient( $cache_key, $body, HOUR_IN_SECONDS );

		return $body;
	}

	/**
	 * @param array<string, mixed> $provider
	 * @return array<string, mixed>|WP_Error
	 */
	private static function exchange_code( string $token_endpoint, string $provider_id, array $provider, string $code ) {
		$response = wp_remote_post(
			$token_endpoint,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => array(
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'redirect_uri'  => self::redirect_uri( $provider_id ),
					'client_id'     => $provider['client_id'],
					'client_secret' => $provider['client_secret'],
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( wp_remote_retrieve_response_code( $response ) >= 400 ) {
			return new WP_Error( 'v_auth_token_http_error', __( 'The identity provider rejected the token request.', 'v-auth' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['id_token'] ) ) {
			return new WP_Error( 'v_auth_token_invalid', __( 'The identity provider did not return an ID token.', 'v-auth' ) );
		}

		return $body;
	}

	/**
	 * Verify the ID token's signature (via the provider's JWKS), issuer,
	 * audience, and nonce, returning its claims.
	 *
	 * @param array<string, mixed> $discovery
	 * @param array<string, mixed> $provider
	 * @return array<string, mixed>|WP_Error
	 */
	private static function validate_id_token( string $id_token, array $discovery, array $provider, string $expected_nonce ) {
		if ( empty( $id_token ) || empty( $discovery['jwks_uri'] ) ) {
			return new WP_Error( 'v_auth_id_token_missing', __( 'Missing ID token or JWKS endpoint.', 'v-auth' ) );
		}

		$jwks_response = wp_remote_get( $discovery['jwks_uri'], array( 'timeout' => 15 ) );
		if ( is_wp_error( $jwks_response ) ) {
			return $jwks_response;
		}

		$jwks = json_decode( wp_remote_retrieve_body( $jwks_response ), true );
		if ( ! is_array( $jwks ) ) {
			return new WP_Error( 'v_auth_jwks_invalid', __( 'The identity provider returned an invalid JWKS document.', 'v-auth' ) );
		}

		try {
			$claims = (array) JWT::decode( $id_token, JWK::parseKeySet( $jwks ) );
		} catch ( \Exception $e ) {
			return new WP_Error( 'v_auth_id_token_signature', __( 'ID token signature verification failed.', 'v-auth' ) );
		}

		if ( ( $claims['iss'] ?? '' ) !== $discovery['issuer'] ) {
			return new WP_Error( 'v_auth_id_token_issuer', __( 'ID token issuer mismatch.', 'v-auth' ) );
		}

		$audience  = $claims['aud'] ?? '';
		$audiences = is_array( $audience ) ? $audience : array( $audience );
		if ( ! in_array( $provider['client_id'], $audiences, true ) ) {
			return new WP_Error( 'v_auth_id_token_audience', __( 'ID token audience mismatch.', 'v-auth' ) );
		}

		if ( ( $claims['nonce'] ?? '' ) !== $expected_nonce ) {
			return new WP_Error( 'v_auth_id_token_nonce', __( 'ID token nonce mismatch.', 'v-auth' ) );
		}

		return $claims;
	}

	private static function redirect_uri( string $provider_id ): string {
		return rest_url( 'v-auth/v1/oidc/' . $provider_id . '/callback' );
	}

	private static function redirect_to_login( array $query_args ): void {
		wp_redirect( add_query_arg( $query_args, wp_login_url() ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}
}
