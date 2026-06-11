<?php
/**
 * Pricing REST Controller for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Rest
 */

namespace OpenProviderWooCommerce\Rest;

use OpenProviderWooCommerce\Support\RateLimiter;
use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Api\PricingService;

/**
 * Pricing REST Controller.
 */
class PricingController extends RestController {

	/**
	 * Pricing service.
	 *
	 * @var PricingService
	 */
	private PricingService $pricing_service;

	/**
	 * Constructor.
	 *
	 * @param RateLimiter    $rate_limiter Rate limiter.
	 * @param Settings       $settings Settings.
	 * @param Logger         $logger Logger.
	 * @param PricingService $pricing_service Pricing service.
	 */
	public function __construct(
		RateLimiter $rate_limiter,
		Settings $settings,
		Logger $logger,
		PricingService $pricing_service
	) {
		parent::__construct( $rate_limiter, $settings, $logger );
		$this->pricing_service = $pricing_service;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/pricing',
			array(
				array(
					'methods' => \WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_pricing' ),
					'permission_callback' => array( $this, 'pricing_permission_callback' ),
					'args' => array(
						'tld' => array(
							'required' => true,
							'type' => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'period' => array(
							'required' => false,
							'type' => 'integer',
							'default' => 1,
							'minimum' => 1,
							'maximum' => 10,
						),
						'operation' => array(
							'required' => false,
							'type' => 'string',
							'default' => 'create',
							'enum' => array( 'create', 'renew', 'transfer' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Get pricing endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_pricing( \WP_REST_Request $request ) {
		$tld = $request->get_param( 'tld' );
		$period = $request->get_param( 'period' );
		$operation = $request->get_param( 'operation' );

		// Validate TLD against allowed list.
		$allowed_tlds = $this->settings->get_allowed_tlds();
		if ( ! in_array( strtolower( $tld ), $allowed_tlds, true ) ) {
			return new \WP_Error(
				'opwc_tld_not_allowed',
				sprintf(
					/* translators: %s: TLD */
					__( '.%s is not available.', 'openprovider-woocommerce' ),
					$tld
				),
				array( 'status' => 422 )
			);
		}

		// Try cache first.
		$cache_key = 'opwc_price_' . md5( $tld . $period . $operation );
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		try {
			$price_data = $this->pricing_service->get_price( 'example', $tld, $operation, $period );

			// Cache for configured TTL.
			set_transient(
				$cache_key,
				$price_data,
				$this->settings->get_cache_ttl( 'pricing' )
			);

			return rest_ensure_response( $price_data );
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Pricing fetch failed for .' . $tld . ': ' . $e->getMessage() );
			return new \WP_Error(
				'opwc_pricing_failed',
				__( 'Unable to retrieve pricing.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Pricing permission callback with rate limiting.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error
	 */
	public function pricing_permission_callback( \WP_REST_Request $request ) {
		return $this->public_permission_callback( $request, 60, 60 ); // 60 requests per minute.
	}
}
