<?php
/**
 * REST Base Controller class for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Rest
 */

namespace OpenProviderWooCommerce\Rest;

use OpenProviderWooCommerce\Support\RateLimiter;
use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;

/**
 * REST Base Controller class.
 */
abstract class RestController {

	/**
	 * API namespace.
	 */
	protected const NAMESPACE = 'openprovider-woocommerce/v1';

	/**
	 * Rate limiter.
	 *
	 * @var RateLimiter
	 */
	protected RateLimiter $rate_limiter;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	protected Settings $settings;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	protected Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param RateLimiter $rate_limiter Rate limiter.
	 * @param Settings    $settings Settings.
	 * @param Logger      $logger Logger.
	 */
	public function __construct(
		RateLimiter $rate_limiter,
		Settings $settings,
		Logger $logger
	) {
		$this->rate_limiter = $rate_limiter;
		$this->settings = $settings;
		$this->logger = $logger;
	}

	/**
	 * Register REST routes.
	 */
	abstract public function register_routes(): void;

	/**
	 * Check public permission with rate limiting.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @param int              $max_requests Max requests allowed.
	 * @param int              $window_seconds Time window in seconds.
	 * @return true|\WP_Error
	 */
	protected function public_permission_callback( \WP_REST_Request $request, int $max_requests, int $window_seconds ) {
		$bucket_key = $this->rate_limiter->bucket_key_for_request( $request );

		if ( ! $this->rate_limiter->check( $bucket_key, $max_requests, $window_seconds ) ) {
			return new \WP_Error(
				'opwc_rate_limited',
				__( 'Too many requests. Please try again later.', 'openprovider-woocommerce' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Check admin permission.
	 *
	 * @return bool
	 */
	protected function admin_permission_callback(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Validate domain format.
	 *
	 * @param string $domain_name Domain name.
	 * @param string $tld TLD.
	 * @return true|\WP_Error
	 */
	protected function validate_domain( string $domain_name, string $tld ) {
		// Basic RFC 1035 validation.
		$pattern = '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/i';

		if ( ! preg_match( $pattern, $domain_name ) ) {
			return new \WP_Error(
				'opwc_invalid_domain',
				__( 'Please enter a valid domain name.', 'openprovider-woocommerce' ),
				array( 'status' => 422 )
			);
		}

		if ( ! preg_match( '/^[a-z]{2,}$/i', $tld ) ) {
			return new \WP_Error(
				'opwc_invalid_tld',
				__( 'Please enter a valid TLD.', 'openprovider-woocommerce' ),
				array( 'status' => 422 )
			);
		}

		// Check against allowed TLDs.
		$allowed_tlds = $this->settings->get_allowed_tlds();
		if ( ! in_array( strtolower( $tld ), $allowed_tlds, true ) ) {
			return new \WP_Error(
				'opwc_tld_not_allowed',
				sprintf(
					/* translators: %s: TLD */
					__( '.%s is not available for registration.', 'openprovider-woocommerce' ),
					$tld
				),
				array( 'status' => 422 )
			);
		}

		return true;
	}
}
