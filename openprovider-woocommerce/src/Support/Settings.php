<?php
/**
 * Settings class for OpenProvider WooCommerce
 *
 * Typed accessor over wp_options for plugin configuration.
 *
 * @package OpenProviderWooCommerce\Support
 */

namespace OpenProviderWooCommerce\Support;

/**
 * Settings class.
 */
class Settings {

	/**
	 * Option key for plugin settings.
	 */
	private const OPTION_KEY = 'opwc_settings';

	/**
	 * Get allowed TLDs.
	 *
	 * @return array List of allowed TLDs (without dot).
	 */
	public function get_allowed_tlds(): array {
		$settings = $this->get_all();
		return array_filter(
			array_map( 'trim', (array) ( $settings['allowed_tlds'] ?? [] ) ),
			static fn( $tld ) => '' !== $tld
		);
	}

	/**
	 * Get default registration period in years.
	 *
	 * @return int Default registration period (1-10).
	 */
	public function get_default_registration_period(): int {
		$settings = $this->get_all();
		$period   = (int) ( $settings['default_registration_period'] ?? 1 );
		return max( 1, min( 10, $period ) );
	}

	/**
	 * Get cache TTL in seconds.
	 *
	 * @param string $type Cache type: 'search', 'pricing', or 'token'.
	 * @return int Cache TTL in seconds.
	 */
	public function get_cache_ttl( string $type ): int {
		$settings = $this->get_all();
		return (int) ( $settings[ "cache_ttl_{$type}" ] ?? $this->defaults()[ "cache_ttl_{$type}" ] );
	}

	/**
	 * Check if debug logging is enabled.
	 *
	 * @return bool True if debug logging enabled.
	 */
	public function is_debug_enabled(): bool {
		$settings = $this->get_all();
		return ! empty( $settings['debug_logging_enabled'] );
	}

	/**
	 * Get API base URL.
	 *
	 * @return string API base URL (sandbox or production).
	 */
	public function get_api_base_url(): string {
		$settings = $this->get_all();
		return 'sandbox' === ( $settings['api_environment'] ?? 'sandbox' )
			? 'https://api.cte.openprovider.eu/v1beta'
			: 'https://api.openprovider.eu/v1beta';
	}

	/**
	 * Check if sandbox mode is enabled.
	 *
	 * @return bool True if sandbox mode enabled.
	 */
	public function is_sandbox(): bool {
		$settings = $this->get_all();
		return 'sandbox' === ( $settings['api_environment'] ?? 'sandbox' );
	}

	/**
	 * Get OpenProvider username.
	 *
	 * @return string|null OpenProvider username or null if not configured.
	 */
	public function get_openprovider_username(): ?string {
		$settings = $this->get_all();

		// Try v-secrets-manager first.
		$secret_name = $settings['secret_name_username'] ?? '';
		if ( $secret_name && function_exists( 'vs_secrets_manager_get' ) ) {
			$value = vs_secrets_manager_get( $secret_name );
			if ( null !== $value && '' !== $value ) {
				return $value;
			}
		}

		// Fallback to encrypted local option.
		$encrypted = $settings['local_username_encrypted'] ?? '';
		if ( $encrypted ) {
			$decrypted = Crypto::decrypt( $encrypted );
			if ( '' !== $decrypted ) {
				return $decrypted;
			}
		}

		return null;
	}

	/**
	 * Get OpenProvider password.
	 *
	 * @return string|null OpenProvider password or null if not configured.
	 */
	public function get_openprovider_password(): ?string {
		$settings = $this->get_all();

		// Try v-secrets-manager first.
		$secret_name = $settings['secret_name_password'] ?? '';
		if ( $secret_name && function_exists( 'vs_secrets_manager_get' ) ) {
			$value = vs_secrets_manager_get( $secret_name );
			if ( null !== $value && '' !== $value ) {
				return $value;
			}
		}

		// Fallback to encrypted local option.
		$encrypted = $settings['local_password_encrypted'] ?? '';
		if ( $encrypted ) {
			$decrypted = Crypto::decrypt( $encrypted );
			if ( '' !== $decrypted ) {
				return $decrypted;
			}
		}

		return null;
	}

	/**
	 * Get secret name for username.
	 *
	 * @return string Secret name for username.
	 */
	public function get_secret_name_username(): string {
		$settings = $this->get_all();
		return $settings['secret_name_username'] ?? '';
	}

	/**
	 * Get secret name for password.
	 *
	 * @return string Secret name for password.
	 */
	public function get_secret_name_password(): string {
		$settings = $this->get_all();
		return $settings['secret_name_password'] ?? '';
	}

	/**
	 * Get admin notification email.
	 *
	 * @return string Admin notification email.
	 */
	public function get_admin_notification_email(): string {
		$settings = $this->get_all();
		return $settings['admin_notification_email'] ?? get_option( 'admin_email' );
	}

	/**
	 * Get premium markup percentage.
	 *
	 * @return int Premium markup percentage (default 20).
	 */
	public function get_premium_markup_percent(): int {
		$settings = $this->get_all();
		return (int) ( $settings['premium_markup_percent'] ?? 20 );
	}

	/**
	 * Get premium markup cap percentage.
	 *
	 * @return int Premium markup cap percentage (default 50).
	 */
	public function get_premium_markup_cap(): int {
		$settings = $this->get_all();
		return (int) ( $settings['premium_markup_cap'] ?? 50 );
	}

	/**
	 * Get premium rounding mode.
	 *
	 * @return string Rounding mode: 'nearest_99', 'nearest_1', or 'nearest_5'.
	 */
	public function get_premium_rounding_mode(): string {
		$settings = $this->get_all();
		$mode     = $settings['premium_rounding_mode'] ?? 'nearest_99';
		return in_array( $mode, [ 'nearest_99', 'nearest_1', 'nearest_5' ], true ) ? $mode : 'nearest_99';
	}

	/**
	 * Update settings.
	 *
	 * @param array $values Settings values to update.
	 * @return bool True on success.
	 */
	public function update( array $values ): bool {
		$current  = $this->get_all();
		$updated  = wp_parse_args( $values, $current );
		return update_option( self::OPTION_KEY, $this->sanitize( $updated ) );
	}

	/**
	 * Get all settings.
	 *
	 * @return array All settings with defaults applied.
	 */
	public function all(): array {
		return array_merge( $this->defaults(), $this->get_all() );
	}

	/**
	 * Get raw settings from database.
	 *
	 * @return array Raw settings from database.
	 */
	private function get_all(): array {
		$settings = get_option( self::OPTION_KEY, [] );
		return is_array( $settings ) ? $settings : [];
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings.
	 */
	private function defaults(): array {
		return array(
			'api_environment'                 => 'sandbox',
			'secret_name_username'            => '',
			'secret_name_password'            => '',
			'local_username_encrypted'        => '',
			'local_password_encrypted'        => '',
			'allowed_tlds'                    => [ 'com', 'net', 'org', 'it', 'eu' ],
			'default_registration_period'     => 1,
			'cache_ttl_search'                => 300,
			'cache_ttl_pricing'               => 43200,
			'debug_logging_enabled'           => false,
			'admin_notification_email'        => '',
			'premium_markup_percent'          => 20,
			'premium_markup_cap'              => 50,
			'premium_rounding_mode'           => 'nearest_99',
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $settings Settings to sanitize.
	 * @return array Sanitized settings.
	 */
	private function sanitize( array $settings ): array {
		return array(
			'api_environment'                 => in_array( $settings['api_environment'] ?? 'sandbox', [ 'sandbox', 'production' ], true )
				? $settings['api_environment']
				: 'sandbox',
			'secret_name_username'            => sanitize_text_field( $settings['secret_name_username'] ?? '' ),
			'secret_name_password'            => sanitize_text_field( $settings['secret_name_password'] ?? '' ),
			'local_username_encrypted'        => sanitize_text_field( $settings['local_username_encrypted'] ?? '' ),
			'local_password_encrypted'        => sanitize_text_field( $settings['local_password_encrypted'] ?? '' ),
			'allowed_tlds'                    => array_map( 'sanitize_text_field', (array) ( $settings['allowed_tlds'] ?? [] ) ),
			'default_registration_period'     => max( 1, min( 10, (int) ( $settings['default_registration_period'] ?? 1 ) ) ),
			'cache_ttl_search'                => max( 60, (int) ( $settings['cache_ttl_search'] ?? 300 ) ),
			'cache_ttl_pricing'               => max( 300, (int) ( $settings['cache_ttl_pricing'] ?? 43200 ) ),
			'debug_logging_enabled'           => ! empty( $settings['debug_logging_enabled'] ),
			'admin_notification_email'        => is_email( $settings['admin_notification_email'] ?? '' ) ? $settings['admin_notification_email'] : '',
			'premium_markup_percent'          => max( 0, min( 100, (int) ( $settings['premium_markup_percent'] ?? 20 ) ) ),
			'premium_markup_cap'              => max( 0, min( 200, (int) ( $settings['premium_markup_cap'] ?? 50 ) ) ),
			'premium_rounding_mode'           => in_array( $settings['premium_rounding_mode'] ?? 'nearest_99', [ 'nearest_99', 'nearest_1', 'nearest_5' ], true )
				? $settings['premium_rounding_mode']
				: 'nearest_99',
		);
	}
}
