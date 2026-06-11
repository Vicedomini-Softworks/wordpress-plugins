<?php
/**
 * Transfer REST Controller for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Rest
 */

namespace OpenProviderWooCommerce\Rest;

use OpenProviderWooCommerce\Support\RateLimiter;
use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Api\TransferService;
use OpenProviderWooCommerce\Api\ApiException;
use OpenProviderWooCommerce\WooCommerce\DomainRepository;
use OpenProviderWooCommerce\WooCommerce\TransferIntegration;

/**
 * Transfer REST Controller.
 */
class TransferController extends RestController {

	/**
	 * Transfer service.
	 *
	 * @var TransferService
	 */
	private TransferService $transfer_service;

	/**
	 * Transfer integration.
	 *
	 * @var TransferIntegration
	 */
	private TransferIntegration $transfer_integration;

	/**
	 * Domain repository.
	 *
	 * @var DomainRepository
	 */
	private DomainRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param RateLimiter         $rate_limiter Rate limiter.
	 * @param Settings            $settings Settings.
	 * @param Logger              $logger Logger.
	 * @param TransferService     $transfer_service Transfer service.
	 * @param TransferIntegration $transfer_integration Transfer integration.
	 * @param DomainRepository    $repository Domain repository.
	 */
	public function __construct(
		RateLimiter $rate_limiter,
		Settings $settings,
		Logger $logger,
		TransferService $transfer_service,
		TransferIntegration $transfer_integration,
		DomainRepository $repository
	) {
		parent::__construct( $rate_limiter, $settings, $logger );
		$this->transfer_service     = $transfer_service;
		$this->transfer_integration = $transfer_integration;
		$this->repository           = $repository;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/transfer/check',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'check_transfer' ),
					'permission_callback' => array( $this, 'check_permission_callback' ),
					'args'                => array(
						'domain_name' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'tld'         => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/transfer/cart/add',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_to_cart' ),
					'permission_callback' => array( $this, 'cart_add_permission_callback' ),
					'args'                => array(
						'domain_name' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'tld'         => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'auth_code'   => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/transfer/status/(?P<transfer_id>[\w-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_transfer_status' ),
					'permission_callback' => array( $this, 'status_permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/transfer/complete',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'complete_transfer' ),
					'permission_callback' => array( $this, 'complete_permission_callback' ),
					'args'                => array(
						'transfer_id' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'auth_code'   => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Check transfer eligibility endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function check_transfer( \WP_REST_Request $request ) {
		$domain_name = $request->get_param( 'domain_name' );
		$tld         = $request->get_param( 'tld' );

		$validation = $this->validate_domain( $domain_name, $tld );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		try {
			$result = $this->transfer_service->check_transfer( $domain_name, $tld );
		} catch ( ApiException $e ) {
			$this->logger->error( 'Transfer check failed: ' . $e->getMessage() );

			return new \WP_Error(
				'opwc_transfer_check_failed',
				__( 'Unable to check transfer eligibility, please try again.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Add domain transfer to cart endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function add_to_cart( \WP_REST_Request $request ) {
		$domain_name = $request->get_param( 'domain_name' );
		$tld         = $request->get_param( 'tld' );
		$auth_code   = $request->get_param( 'auth_code' );

		$validation = $this->validate_domain( $domain_name, $tld );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$cart_item_key = $this->transfer_integration->add_transfer_to_cart( $domain_name, $tld, $auth_code );

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
	 * Get transfer status endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_transfer_status( \WP_REST_Request $request ) {
		$transfer_id = $request->get_param( 'transfer_id' );

		try {
			$result = $this->transfer_service->get_transfer_status( $transfer_id );
		} catch ( ApiException $e ) {
			$this->logger->error( 'Transfer status check failed: ' . $e->getMessage() );

			return new \WP_Error(
				'opwc_transfer_status_failed',
				__( 'Unable to retrieve transfer status.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Complete transfer endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function complete_transfer( \WP_REST_Request $request ) {
		$transfer_id = $request->get_param( 'transfer_id' );
		$auth_code   = $request->get_param( 'auth_code' );

		try {
			$result = $this->transfer_service->complete_transfer( $transfer_id, $auth_code );
		} catch ( ApiException $e ) {
			$this->logger->error( 'Transfer completion failed: ' . $e->getMessage() );

			return new \WP_Error(
				'opwc_transfer_complete_failed',
				__( 'Unable to complete transfer.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		if ( $result['success'] ) {
			$domain = $this->repository->find_by_transfer_id( $transfer_id );
			if ( $domain ) {
				$this->repository->update_transfer_status( (int) $domain->id, 'in_progress' );
			}
		}

		return rest_ensure_response( $result );
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

	/**
	 * Status permission callback. Public, but logged-in users may only view their own transfers.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error
	 */
	public function status_permission_callback( \WP_REST_Request $request ) {
		$rate_check = $this->public_permission_callback( $request, 30, 60 ); // 30 requests per minute.
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$transfer_id = $request->get_param( 'transfer_id' );
		$domain      = $this->repository->find_by_transfer_id( $transfer_id );

		if ( $domain && $domain->customer_id ) {
			if ( ! is_user_logged_in() ) {
				return new \WP_Error(
					'opwc_not_logged_in',
					__( 'You must be logged in to view this transfer.', 'openprovider-woocommerce' ),
					array( 'status' => 401 )
				);
			}

			if ( get_current_user_id() !== (int) $domain->customer_id && ! current_user_can( 'manage_woocommerce' ) ) {
				return new \WP_Error(
					'opwc_forbidden',
					__( 'You do not have permission to view this transfer.', 'openprovider-woocommerce' ),
					array( 'status' => 403 )
				);
			}
		}

		return true;
	}

	/**
	 * Complete permission callback. Logged-in user must own the transfer.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error
	 */
	public function complete_permission_callback( \WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'opwc_not_logged_in',
				__( 'You must be logged in to complete a transfer.', 'openprovider-woocommerce' ),
				array( 'status' => 401 )
			);
		}

		$transfer_id = $request->get_param( 'transfer_id' );
		$domain      = $this->repository->find_by_transfer_id( $transfer_id );

		if ( ! $domain ) {
			return new \WP_Error(
				'opwc_transfer_not_found',
				__( 'Transfer not found.', 'openprovider-woocommerce' ),
				array( 'status' => 404 )
			);
		}

		if ( get_current_user_id() !== (int) $domain->customer_id && ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error(
				'opwc_forbidden',
				__( 'You do not have permission to complete this transfer.', 'openprovider-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		return $this->public_permission_callback( $request, 5, 60 ); // 5 requests per minute.
	}
}
