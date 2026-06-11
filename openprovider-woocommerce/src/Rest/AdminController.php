<?php
/**
 * Admin REST Controller for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Rest
 */

namespace OpenProviderWooCommerce\Rest;

use OpenProviderWooCommerce\Support\RateLimiter;
use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Api\AuthService;

/**
 * Admin REST Controller.
 */
class AdminController extends RestController {

	/**
	 * Auth service.
	 *
	 * @var AuthService
	 */
	private AuthService $auth_service;

	/**
	 * Constructor.
	 *
	 * @param RateLimiter    $rate_limiter Rate limiter.
	 * @param Settings       $settings Settings.
	 * @param Logger         $logger Logger.
	 * @param AuthService    $auth_service Auth service.
	 */
	public function __construct(
		RateLimiter $rate_limiter,
		Settings $settings,
		Logger $logger,
		AuthService $auth_service
	) {
		parent::__construct( $rate_limiter, $settings, $logger );
		$this->auth_service = $auth_service;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/admin/cache/flush',
			array(
				array(
					'methods' => \WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'flush_cache' ),
					'permission_callback' => array( $this, 'admin_permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/admin/test-connection',
			array(
				array(
					'methods' => \WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'test_connection' ),
					'permission_callback' => array( $this, 'admin_permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/admin/domains',
			array(
				array(
					'methods' => \WP_REST_Server::READABLE,
					'callback' => array( $this, 'list_domains' ),
					'permission_callback' => array( $this, 'admin_permission_callback' ),
					'args' => array(
						'page' => array(
							'default' => 1,
							'type' => 'integer',
						),
						'per_page' => array(
							'default' => 20,
							'type' => 'integer',
						),
						'status' => array(
							'type' => 'string',
							'enum' => array( 'pending', 'registered', 'failed' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Flush cache endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function flush_cache( \WP_REST_Request $request ) {
		// Clear auth token.
		delete_transient( 'opwc_auth_token' );

		// Clear search transients (prefix-based).
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_opwc_search_' ) . '%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_opwc_search_' ) . '%'
			)
		);

		// Clear pricing transients.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_opwc_price_' ) . '%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_opwc_price_' ) . '%'
			)
		);

		$this->logger->info( 'Admin cache flushed' );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Cache flushed successfully.', 'openprovider-woocommerce' ),
			)
		);
	}

	/**
	 * Test connection endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function test_connection( \WP_REST_Request $request ) {
		try {
			$result = $this->auth_service->login();
			$this->logger->info( 'Admin connection test successful' );

			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Connection successful.', 'openprovider-woocommerce' ),
					'data' => array(
						'reseller_id' => $result['reseller_id'] ?? null,
					),
				)
			);
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Admin connection test failed: ' . $e->getMessage() );

			return new \WP_Error(
				'opwc_connection_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Connection failed: %s', 'openprovider-woocommerce' ),
					$e->getMessage()
				),
				array( 'status' => 401 )
			);
		}
	}

	/**
	 * List domains endpoint (admin).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function list_domains( \WP_REST_Request $request ) {
		global $wpdb;

		$page = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );
		$status = $request->get_param( 'status' );

		$table = $wpdb->prefix . 'op_domains';

		// Build WHERE clause.
		$where = '1=1';
		if ( $status ) {
			$where .= $wpdb->prepare( ' AND status = %s', $status );
		}

		// Get total count.
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE {$where}"
		);

		// Get domains.
		$domains = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				( $page - 1 ) * $per_page
			),
			ARRAY_A
		);

		return rest_ensure_response(
			array(
				'domains' => $domains,
				'total' => $total,
				'pages' => (int) ceil( $total / $per_page ),
				'current_page' => $page,
			)
		);
	}
}
