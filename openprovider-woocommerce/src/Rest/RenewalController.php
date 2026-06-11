<?php
/**
 * Renewal REST Controller for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Rest
 */

namespace OpenProviderWooCommerce\Rest;

use OpenProviderWooCommerce\Support\RateLimiter;
use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Api\RenewalService;
use OpenProviderWooCommerce\Api\ApiException;
use OpenProviderWooCommerce\WooCommerce\DomainRepository;
use OpenProviderWooCommerce\WooCommerce\RenewalIntegration;

/**
 * Renewal REST Controller.
 */
class RenewalController extends RestController {

	/**
	 * Domain repository.
	 *
	 * @var DomainRepository
	 */
	private DomainRepository $repository;

	/**
	 * Renewal integration.
	 *
	 * @var RenewalIntegration
	 */
	private RenewalIntegration $renewal_integration;

	/**
	 * Renewal service.
	 *
	 * @var RenewalService
	 */
	private RenewalService $renewal_service;

	/**
	 * Constructor.
	 *
	 * @param RateLimiter        $rate_limiter Rate limiter.
	 * @param Settings           $settings Settings.
	 * @param Logger             $logger Logger.
	 * @param DomainRepository   $repository Domain repository.
	 * @param RenewalIntegration $renewal_integration Renewal integration.
	 * @param RenewalService     $renewal_service Renewal service.
	 */
	public function __construct(
		RateLimiter $rate_limiter,
		Settings $settings,
		Logger $logger,
		DomainRepository $repository,
		RenewalIntegration $renewal_integration,
		RenewalService $renewal_service
	) {
		parent::__construct( $rate_limiter, $settings, $logger );
		$this->repository          = $repository;
		$this->renewal_integration = $renewal_integration;
		$this->renewal_service     = $renewal_service;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/domain/(?P<id>\d+)/renewal-price',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_renewal_price' ),
					'permission_callback' => array( $this, 'owner_permission_callback' ),
					'args'                => array(
						'period' => array(
							'required' => false,
							'type'     => 'integer',
							'default'  => 1,
							'minimum'  => 1,
							'maximum'  => 10,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/domain/(?P<id>\d+)/renew',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_renewal_to_cart' ),
					'permission_callback' => array( $this, 'owner_permission_callback' ),
					'args'                => array(
						'period' => array(
							'required' => false,
							'type'     => 'integer',
							'default'  => 1,
							'minimum'  => 1,
							'maximum'  => 10,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/domain/(?P<id>\d+)/auto-renew',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'set_auto_renew' ),
					'permission_callback' => array( $this, 'owner_permission_callback' ),
					'args'                => array(
						'enabled' => array(
							'required' => true,
							'type'     => 'boolean',
						),
					),
				),
			)
		);
	}

	/**
	 * Get renewal price endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_renewal_price( \WP_REST_Request $request ) {
		$domain = $this->repository->find( (int) $request->get_param( 'id' ) );
		$period = (int) $request->get_param( 'period' );

		try {
			$price_data = $this->renewal_service->get_renewal_price( $domain->domain_name, $domain->tld, $period );
		} catch ( ApiException $e ) {
			$this->logger->error( 'Failed to get renewal price: ' . $e->getMessage() );

			return new \WP_Error(
				'opwc_renewal_price_failed',
				__( 'Unable to retrieve renewal price.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( $price_data );
	}

	/**
	 * Add domain renewal to cart endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function add_renewal_to_cart( \WP_REST_Request $request ) {
		$domain_id = (int) $request->get_param( 'id' );
		$period    = (int) $request->get_param( 'period' );

		$cart_item_key = $this->renewal_integration->add_renewal_to_cart( $domain_id, $period );

		if ( is_wp_error( $cart_item_key ) ) {
			return $cart_item_key;
		}

		$cart_fragment = apply_filters( 'woocommerce_get_cart_fragment', '' );

		return rest_ensure_response(
			array(
				'success'       => true,
				'cart_item_key' => $cart_item_key,
				'cart_fragment' => $cart_fragment,
			)
		);
	}

	/**
	 * Set auto-renew endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_auto_renew( \WP_REST_Request $request ) {
		$domain_id = (int) $request->get_param( 'id' );
		$enabled   = (bool) $request->get_param( 'enabled' );

		$this->repository->set_auto_renew( $domain_id, $enabled );

		return rest_ensure_response(
			array(
				'success'    => true,
				'auto_renew' => $enabled,
			)
		);
	}

	/**
	 * Permission callback: current user must own the domain or manage WooCommerce.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error
	 */
	public function owner_permission_callback( \WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'opwc_not_logged_in',
				__( 'You must be logged in to manage this domain.', 'openprovider-woocommerce' ),
				array( 'status' => 401 )
			);
		}

		$domain = $this->repository->find( (int) $request->get_param( 'id' ) );

		if ( ! $domain ) {
			return new \WP_Error(
				'opwc_domain_not_found',
				__( 'Domain not found.', 'openprovider-woocommerce' ),
				array( 'status' => 404 )
			);
		}

		if ( get_current_user_id() !== (int) $domain->customer_id && ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error(
				'opwc_forbidden',
				__( 'You do not have permission to manage this domain.', 'openprovider-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		return $this->public_permission_callback( $request, 30, 60 );
	}
}
