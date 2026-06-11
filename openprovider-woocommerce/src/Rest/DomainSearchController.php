<?php
/**
 * Domain Search REST Controller for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Rest
 */

namespace OpenProviderWooCommerce\Rest;

use OpenProviderWooCommerce\Support\RateLimiter;
use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Api\DomainService;
use OpenProviderWooCommerce\Api\PricingService;
use OpenProviderWooCommerce\WooCommerce\DomainProductFactory;

/**
 * Domain Search REST Controller.
 */
class DomainSearchController extends RestController {

	/**
	 * Domain service.
	 *
	 * @var DomainService
	 */
	private DomainService $domain_service;

	/**
	 * Pricing service.
	 *
	 * @var PricingService
	 */
	private PricingService $pricing_service;

	/**
	 * Domain product factory.
	 *
	 * @var DomainProductFactory
	 */
	private DomainProductFactory $product_factory;

	/**
	 * Constructor.
	 *
	 * @param RateLimiter        $rate_limiter Rate limiter.
	 * @param Settings           $settings Settings.
	 * @param Logger             $logger Logger.
	 * @param DomainService      $domain_service Domain service.
	 * @param PricingService     $pricing_service Pricing service.
	 * @param DomainProductFactory $product_factory Domain product factory.
	 */
	public function __construct(
		RateLimiter $rate_limiter,
		Settings $settings,
		Logger $logger,
		DomainService $domain_service,
		PricingService $pricing_service,
		DomainProductFactory $product_factory
	) {
		parent::__construct( $rate_limiter, $settings, $logger );
		$this->domain_service = $domain_service;
		$this->pricing_service = $pricing_service;
		$this->product_factory = $product_factory;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/search',
			array(
				array(
					'methods' => \WP_REST_Server::READABLE,
					'callback' => array( $this, 'search_domains' ),
					'permission_callback' => array( $this, 'search_permission_callback' ),
					'args' => array(
						'query' => array(
							'required' => true,
							'type' => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'wp_validate_string_not_empty',
						),
						'tlds' => array(
							'required' => false,
							'type' => 'array',
							'items' => array( 'type' => 'string' ),
							'default' => array(),
						),
						'period' => array(
							'required' => false,
							'type' => 'integer',
							'default' => 1,
							'minimum' => 1,
							'maximum' => 10,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/check',
			array(
				array(
					'methods' => \WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'check_domains' ),
					'permission_callback' => array( $this, 'check_permission_callback' ),
					'args' => array(
						'domains' => array(
							'required' => true,
							'type' => 'array',
							'max_items' => 10,
							'items' => array(
								'type' => 'object',
								'properties' => array(
									'name' => array( 'type' => 'string' ),
									'extension' => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/cart/add',
			array(
				array(
					'methods' => \WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'add_to_cart' ),
					'permission_callback' => array( $this, 'cart_add_permission_callback' ),
					'args' => array(
						'domain_name' => array(
							'required' => true,
							'type' => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'tld' => array(
							'required' => true,
							'type' => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'registration_period' => array(
							'required' => false,
							'type' => 'integer',
							'default' => 1,
							'minimum' => 1,
							'maximum' => 10,
						),
					),
				),
			)
		);
	}

	/**
	 * Search domains endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function search_domains( \WP_REST_Request $request ) {
		$query = $request->get_param( 'query' );
		$tlds_param = $request->get_param( 'tlds' );
		$period = $request->get_param( 'period' );

		// Get allowed TLDs.
		$allowed_tlds = $this->settings->get_allowed_tlds();

		// Use requested TLDs if provided, otherwise use all allowed.
		$tlds = ! empty( $tlds_param ) && is_array( $tlds_param )
			? array_intersect( array_map( 'strtolower', $tlds_param ), $allowed_tlds )
			: $allowed_tlds;

		if ( empty( $tlds ) ) {
			return rest_ensure_response( array( 'domains' => array() ) );
		}

		// Build domains array for bulk check.
		$domains_to_check = array();
		foreach ( $tlds as $tld ) {
			$domains_to_check[] = array(
				'name' => strtolower( sanitize_text_field( $query ) ),
				'extension' => strtolower( sanitize_text_field( $tld ) ),
			);
		}

		try {
			$results = $this->domain_service->check_bulk( $domains_to_check );
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Domain search failed: ' . $e->getMessage() );
			return new \WP_Error(
				'opwc_search_failed',
				__( 'Domain search unavailable, please try again.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		// Enrich results with pricing for available domains.
		$response_domains = array();
		foreach ( $results as $result ) {
			$is_available = $result['available'] ?? false;
			$price = $result['price'] ?? 0;
			$is_premium = $result['premium'] ?? false;

			// Get pricing for non-available or missing price.
			if ( ! $is_available || ( $is_available && 0 === $price ) ) {
				try {
					$price_data = $this->pricing_service->get_price(
						$result['name'],
						$result['extension'],
						'create',
						$period
					);
					$price = $price_data['price'];
					$is_premium = $price_data['premium'] || $is_premium;
				} catch ( \Throwable $e ) {
					$this->logger->debug( 'Pricing fetch failed for ' . $result['name'] . '.' . $result['extension'] );
				}
			}

			$response_domains[] = array(
				'name' => $result['name'],
				'tld' => $result['extension'],
				'available' => $is_available,
				'premium' => $is_premium,
				'price' => $is_available ? $price : null,
				'currency' => $is_available ? ( $result['currency'] ?? get_woocommerce_currency() ) : null,
			);
		}

		return rest_ensure_response( array( 'domains' => $response_domains ) );
	}

	/**
	 * Check domains endpoint (bulk).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function check_domains( \WP_REST_Request $request ) {
		$domains = $request->get_param( 'domains' );

		if ( empty( $domains ) || ! is_array( $domains ) ) {
			return new \WP_Error(
				'opwc_invalid_request',
				__( 'No domains provided.', 'openprovider-woocommerce' ),
				array( 'status' => 400 )
			);
		}

		// Validate each domain.
		foreach ( $domains as $domain ) {
			if ( empty( $domain['name'] ) || empty( $domain['extension'] ) ) {
				return new \WP_Error(
					'opwc_invalid_request',
					__( 'Invalid domain format.', 'openprovider-woocommerce' ),
					array( 'status' => 422 )
				);
			}

			$validation = $this->validate_domain( $domain['name'], $domain['extension'] );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}
		}

		try {
			$results = $this->domain_service->check_bulk( $domains );
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Domain check failed: ' . $e->getMessage() );
			return new \WP_Error(
				'opwc_check_failed',
				__( 'Domain check unavailable, please try again.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( array( 'domains' => $results ) );
	}

	/**
	 * Add domain to cart endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function add_to_cart( \WP_REST_Request $request ) {
		$domain_name = $request->get_param( 'domain_name' );
		$tld = $request->get_param( 'tld' );
		$period = $request->get_param( 'registration_period' );

		// Validate domain.
		$validation = $this->validate_domain( $domain_name, $tld );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Re-check availability and price server-side.
		try {
			$check_result = $this->domain_service->check( $domain_name, $tld );
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'opwc_availability_check_failed',
				__( 'Unable to verify domain availability.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		$is_available = $check_result['available'] ?? false;
		$is_premium = $check_result['premium'] ?? false;

		if ( ! $is_available ) {
			// Check if it's a 409 conflict (already registered).
			$status = $check_result['status'] ?? '';
			if ( in_array( $status, array( 'active', 'taken', 'registered' ), true ) ) {
				return new \WP_Error(
					'opwc_domain_taken',
					sprintf(
						/* translators: %s: domain name */
						__( '%s.%s is already taken, try a different name.', 'openprovider-woocommerce' ),
						$domain_name,
						$tld
					),
					array( 'status' => 409 )
				);
			}

			return new \WP_Error(
				'opwc_domain_unavailable',
				sprintf(
					/* translators: %s: domain name */
					__( '%s.%s is not available for registration.', 'openprovider-woocommerce' ),
					$domain_name,
					$tld
				),
				array( 'status' => 422 )
			);
		}

		// Get price.
		try {
			$price_data = $this->pricing_service->get_price( $domain_name, $tld, 'create', $period );
			$price = $price_data['price'];
			$currency = $price_data['currency'];
			$is_premium = $is_premium || $price_data['premium'];
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Pricing fetch failed: ' . $e->getMessage() );
			return new \WP_Error(
				'opwc_pricing_failed',
				__( 'Unable to retrieve domain price.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		// Add to cart.
		$cart_item_key = $this->product_factory->add_domain_to_cart(
			$domain_name,
			$tld,
			$period,
			$price,
			$currency,
			$is_premium
		);

		if ( is_wp_error( $cart_item_key ) ) {
			return $cart_item_key;
		}

		// Get cart fragment for AJAX update.
		$cart_fragment = apply_filters( 'woocommerce_get_cart_fragment', '' );

		return rest_ensure_response(
			array(
				'success' => true,
				'cart_item_key' => $cart_item_key,
				'cart_fragment' => $cart_fragment,
				'domain' => array(
					'name' => $domain_name,
					'tld' => $tld,
					'period' => $period,
					'price' => $price,
					'currency' => $currency,
					'premium' => $is_premium,
				),
			)
		);
	}

	/**
	 * Search permission callback with rate limiting.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error
	 */
	public function search_permission_callback( \WP_REST_Request $request ) {
		return $this->public_permission_callback( $request, 30, 60 ); // 30 requests per minute.
	}

	/**
	 * Check permission callback with rate limiting.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error
	 */
	public function check_permission_callback( \WP_REST_Request $request ) {
		return $this->public_permission_callback( $request, 20, 60 ); // 20 requests per minute.
	}

	/**
	 * Cart add permission callback with rate limiting.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error
	 */
	public function cart_add_permission_callback( \WP_REST_Request $request ) {
		return $this->public_permission_callback( $request, 10, 60 ); // 10 requests per minute.
	}
}
