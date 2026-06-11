<?php
/**
 * DNS REST Controller for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Rest
 */

namespace OpenProviderWooCommerce\Rest;

use OpenProviderWooCommerce\Support\RateLimiter;
use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Api\DnsService;
use OpenProviderWooCommerce\Api\ApiException;
use OpenProviderWooCommerce\WooCommerce\DomainRepository;

/**
 * DNS REST Controller.
 */
class DnsController extends RestController {

	/**
	 * DNS service.
	 *
	 * @var DnsService
	 */
	private DnsService $dns_service;

	/**
	 * Domain repository.
	 *
	 * @var DomainRepository
	 */
	private DomainRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param RateLimiter      $rate_limiter Rate limiter.
	 * @param Settings         $settings Settings.
	 * @param Logger           $logger Logger.
	 * @param DnsService       $dns_service DNS service.
	 * @param DomainRepository $repository Domain repository.
	 */
	public function __construct(
		RateLimiter $rate_limiter,
		Settings $settings,
		Logger $logger,
		DnsService $dns_service,
		DomainRepository $repository
	) {
		parent::__construct( $rate_limiter, $settings, $logger );
		$this->dns_service = $dns_service;
		$this->repository  = $repository;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/domain/(?P<id>\d+)/nameservers',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_nameservers' ),
					'permission_callback' => array( $this, 'owner_permission_callback' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_nameservers' ),
					'permission_callback' => array( $this, 'owner_permission_callback' ),
					'args'                => array(
						'nameservers' => array(
							'required' => false,
							'type'     => 'array',
							'items'    => array( 'type' => 'string' ),
							'default'  => array(),
						),
						'reset'       => array(
							'required' => false,
							'type'     => 'boolean',
							'default'  => false,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/domain/(?P<id>\d+)/dns-records',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_dns_records' ),
					'permission_callback' => array( $this, 'owner_permission_callback' ),
					'args'                => array(
						'type' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dns/record',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_dns_record' ),
					'permission_callback' => array( $this, 'record_owner_permission_callback' ),
					'args'                => $this->get_record_args( true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dns/record/(?P<record_id>[\w-]+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_dns_record' ),
					'permission_callback' => array( $this, 'record_owner_permission_callback' ),
					'args'                => $this->get_record_args( false ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_dns_record' ),
					'permission_callback' => array( $this, 'record_owner_permission_callback' ),
					'args'                => array(
						'domain_id' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				),
			)
		);
	}

	/**
	 * Shared argument schema for DNS record endpoints.
	 *
	 * @param bool $require_domain Whether 'domain_id' is required.
	 * @return array Argument schema.
	 */
	private function get_record_args( bool $require_domain ): array {
		return array(
			'domain_id' => array(
				'required' => $require_domain,
				'type'     => 'integer',
			),
			'type'      => array(
				'required'          => $require_domain,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'name'      => array(
				'required'          => $require_domain,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'value'     => array(
				'required'          => $require_domain,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'ttl'       => array(
				'required' => false,
				'type'     => 'integer',
				'default'  => 3600,
			),
			'priority'  => array(
				'required' => false,
				'type'     => 'integer',
			),
			'weight'    => array(
				'required' => false,
				'type'     => 'integer',
			),
			'port'      => array(
				'required' => false,
				'type'     => 'integer',
			),
		);
	}

	/**
	 * Get nameservers endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_nameservers( \WP_REST_Request $request ) {
		$domain = $this->repository->find( (int) $request->get_param( 'id' ) );

		try {
			$result = $this->dns_service->get_nameservers( (string) $domain->openprovider_domain_id );
		} catch ( ApiException $e ) {
			$this->logger->error( 'Failed to get nameservers: ' . $e->getMessage() );

			return new \WP_Error(
				'opwc_nameservers_failed',
				__( 'Unable to retrieve nameservers.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Update nameservers endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_nameservers( \WP_REST_Request $request ) {
		$domain = $this->repository->find( (int) $request->get_param( 'id' ) );
		$reset  = (bool) $request->get_param( 'reset' );

		try {
			if ( $reset ) {
				$result = $this->dns_service->reset_nameservers( (string) $domain->openprovider_domain_id );
			} else {
				$nameservers = array_map( 'sanitize_text_field', (array) $request->get_param( 'nameservers' ) );
				$result      = $this->dns_service->update_nameservers( (string) $domain->openprovider_domain_id, $nameservers );
			}
		} catch ( ApiException $e ) {
			$this->logger->error( 'Failed to update nameservers: ' . $e->getMessage() );

			return new \WP_Error(
				'opwc_nameservers_update_failed',
				__( 'Unable to update nameservers.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get DNS records endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_dns_records( \WP_REST_Request $request ) {
		$domain = $this->repository->find( (int) $request->get_param( 'id' ) );
		$type   = $request->get_param( 'type' );

		try {
			$records = $this->dns_service->get_dns_records( (string) $domain->openprovider_domain_id, ! empty( $type ) ? $type : null );
		} catch ( ApiException $e ) {
			$this->logger->error( 'Failed to get DNS records: ' . $e->getMessage() );

			return new \WP_Error(
				'opwc_dns_records_failed',
				__( 'Unable to retrieve DNS records.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'records'         => $records,
				'supported_types' => $this->dns_service->get_supported_types(),
			)
		);
	}

	/**
	 * Add DNS record endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function add_dns_record( \WP_REST_Request $request ) {
		$domain = $this->repository->find( (int) $request->get_param( 'domain_id' ) );

		try {
			$result = $this->dns_service->add_dns_record(
				array(
					'domain_id' => (string) $domain->openprovider_domain_id,
					'type'      => $request->get_param( 'type' ),
					'name'      => $request->get_param( 'name' ),
					'value'     => $request->get_param( 'value' ),
					'ttl'       => $request->get_param( 'ttl' ),
					'priority'  => $request->get_param( 'priority' ),
					'weight'    => $request->get_param( 'weight' ),
					'port'      => $request->get_param( 'port' ),
				)
			);
		} catch ( ApiException $e ) {
			$this->logger->error( 'Failed to add DNS record: ' . $e->getMessage() );

			return new \WP_Error(
				'opwc_dns_record_add_failed',
				__( 'Unable to add DNS record.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Update DNS record endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_dns_record( \WP_REST_Request $request ) {
		$record_id = $request->get_param( 'record_id' );

		try {
			$result = $this->dns_service->update_dns_record(
				$record_id,
				array(
					'type'     => $request->get_param( 'type' ),
					'name'     => $request->get_param( 'name' ),
					'value'    => $request->get_param( 'value' ),
					'ttl'      => $request->get_param( 'ttl' ),
					'priority' => $request->get_param( 'priority' ),
					'weight'   => $request->get_param( 'weight' ),
					'port'     => $request->get_param( 'port' ),
				)
			);
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to update DNS record {$record_id}: " . $e->getMessage() );

			return new \WP_Error(
				'opwc_dns_record_update_failed',
				__( 'Unable to update DNS record.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Delete DNS record endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_dns_record( \WP_REST_Request $request ) {
		$record_id = $request->get_param( 'record_id' );

		try {
			$result = $this->dns_service->delete_dns_record( $record_id );
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to delete DNS record {$record_id}: " . $e->getMessage() );

			return new \WP_Error(
				'opwc_dns_record_delete_failed',
				__( 'Unable to delete DNS record.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Permission callback: current user must own the domain (by route 'id') or manage WooCommerce.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error
	 */
	public function owner_permission_callback( \WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'opwc_not_logged_in',
				__( 'You must be logged in to manage DNS for this domain.', 'openprovider-woocommerce' ),
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
				__( 'You do not have permission to manage DNS for this domain.', 'openprovider-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		return $this->public_permission_callback( $request, 30, 60 );
	}

	/**
	 * Permission callback: current user must own the domain referenced by 'domain_id' or manage WooCommerce.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error
	 */
	public function record_owner_permission_callback( \WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'opwc_not_logged_in',
				__( 'You must be logged in to manage DNS records.', 'openprovider-woocommerce' ),
				array( 'status' => 401 )
			);
		}

		$domain_id = (int) $request->get_param( 'domain_id' );
		$domain    = $domain_id ? $this->repository->find( $domain_id ) : null;

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
				__( 'You do not have permission to manage DNS records for this domain.', 'openprovider-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		return $this->public_permission_callback( $request, 30, 60 );
	}
}
