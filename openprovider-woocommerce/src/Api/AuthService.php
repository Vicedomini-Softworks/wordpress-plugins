<?php
/**
 * Authentication Service for OpenProvider WooCommerce
 *
 * Handles OpenProvider API authentication and token management.
 *
 * @package OpenProviderWooCommerce\Api
 */

namespace OpenProviderWooCommerce\Api;

use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;

/**
 * Authentication Service class.
 */
class AuthService {

	/**
	 * Transient key for auth token.
	 */
	private const TOKEN_TRANSIENT = 'opwc_auth_token';

	/**
	 * HTTP client instance.
	 *
	 * @var HttpClientInterface
	 */
	private HttpClientInterface $http;

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param HttpClientInterface $http HTTP client.
	 * @param Settings            $settings Settings.
	 * @param Logger              $logger Logger.
	 */
	public function __construct(
		HttpClientInterface $http,
		Settings $settings,
		Logger $logger
	) {
		$this->http       = $http;
		$this->settings   = $settings;
		$this->logger     = $logger;
	}

	/**
	 * Get authentication token (cached or fresh).
	 *
	 * @return string Bearer token.
	 * @throws ApiException If authentication fails.
	 */
	public function get_token(): string {
		$cached = get_transient( self::TOKEN_TRANSIENT );

		if ( $cached && is_array( $cached ) && isset( $cached['token'] ) ) {
			$this->logger->debug( 'Using cached auth token' );
			return $cached['token'];
		}

		$this->logger->info( 'Fetching new auth token' );
		$response = $this->login();

		return $response['token'];
	}

	/**
	 * Perform login and get fresh token.
	 *
	 * @return array Token data with 'token' key.
	 * @throws ApiException If login fails.
	 */
	public function login(): array {
		$credentials = $this->get_credentials();

		if ( ! $credentials['username'] || ! $credentials['password'] ) {
			throw new ApiException(
				'OpenProvider credentials not configured',
				401,
				array( 'error' => 'credentials_missing' )
			);
		}

		$body = array(
			'username' => $credentials['username'],
			'password' => $credentials['password'],
		);

		// Add IP if configured.
		if ( $credentials['ip'] ) {
			$body['ip'] = $credentials['ip'];
		}

		try {
			$response = $this->http->request( 'POST', '/auth/login', array( 'body' => $body ) );
		} catch ( ApiException $e ) {
			// Invalidate token on 401 to force retry.
			if ( 401 === $e->getStatusCode() ) {
				$this->invalidate_token();
			}
			throw $e;
		}

		$token_data = $this->map_login_response( $response['body'] );

		// Cache token with TTL.
		$ttl = isset( $token_data['expires_in'] ) && $token_data['expires_in'] > 60
			? $token_data['expires_in'] - 60
			: 3300; // Default 55 minutes.

		set_transient(
			self::TOKEN_TRANSIENT,
			array(
				'token'    => $token_data['token'],
				'reseller' => $token_data['reseller_id'],
				'expires'  => time() + $ttl,
			),
			$ttl
		);

		$this->logger->info( 'Successfully authenticated with OpenProvider' );

		return $token_data;
	}

	/**
	 * Invalidate cached token.
	 */
	public function invalidate_token(): void {
		delete_transient( self::TOKEN_TRANSIENT );
		$this->logger->debug( 'Auth token invalidated' );
	}

	/**
	 * Get credentials from settings.
	 *
	 * @return array Credentials with username, password, ip.
	 */
	private function get_credentials(): array {
		return array(
			'username' => $this->settings->get_openprovider_username() ?? '',
			'password' => $this->settings->get_openprovider_password() ?? '',
			'ip'       => '', // IP can be added as a setting if needed.
		);
	}

	/**
	 * Map raw login response to normalized format.
	 *
	 * @param array $raw Raw API response.
	 * @return array Normalized token data.
	 */
	private function map_login_response( array $raw ): array {
		// Try different possible field names (API variations).
		$token = $raw['data']['token'] ?? $raw['token'] ?? $raw['data']['accessToken'] ?? '';
		$reseller_id = $raw['data']['resellerId'] ?? $raw['reseller_id'] ?? $raw['resellerId'] ?? '';
		$expires_in = $raw['data']['expiresIn'] ?? $raw['expires_in'] ?? $raw['expiresIn'] ?? 3600;

		if ( ! $token ) {
			throw new ApiException(
				'No token in authentication response',
				500,
				array( 'raw_response' => $raw )
			);
		}

		return array(
			'token'       => $token,
			'reseller_id' => $reseller_id ?: null,
			'expires_in'  => (int) $expires_in,
		);
	}
}
