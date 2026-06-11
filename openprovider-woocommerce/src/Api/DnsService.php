<?php
/**
 * DNS Service for OpenProvider WooCommerce
 *
 * Handles nameserver and DNS record management for domains.
 *
 * @package OpenProviderWooCommerce\Api
 */

namespace OpenProviderWooCommerce\Api;

use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;

/**
 * DNS Service class.
 */
class DnsService {

	use RequestRetryTrait;

	/**
	 * Supported DNS record types.
	 */
	private const SUPPORTED_TYPES = array( 'A', 'AAAA', 'CNAME', 'MX', 'TXT', 'SRV', 'NS', 'SOA' );

	/**
	 * HTTP client instance.
	 *
	 * @var HttpClientInterface
	 */
	private HttpClientInterface $http;

	/**
	 * Auth service instance.
	 *
	 * @var AuthService
	 */
	private AuthService $auth;

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
	 * @param AuthService         $auth Auth service.
	 * @param Settings            $settings Settings.
	 * @param Logger              $logger Logger.
	 */
	public function __construct(
		HttpClientInterface $http,
		AuthService $auth,
		Settings $settings,
		Logger $logger
	) {
		$this->http     = $http;
		$this->auth     = $auth;
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Get nameservers for a domain.
	 *
	 * @param string $domain_id Domain ID.
	 * @return array{type: string, servers: array}
	 * @throws ApiException On API error.
	 */
	public function get_nameservers( string $domain_id ): array {
		try {
			$response = $this->request_with_retry( $this->http, $this->auth, 'GET', "/domains/{$domain_id}" );
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to get nameservers for domain {$domain_id}: " . $e->getMessage() );
			throw $e;
		}

		return $this->map_nameservers_response( $response['body'] );
	}

	/**
	 * Update nameservers for a domain.
	 *
	 * @param string $domain_id Domain ID.
	 * @param array  $nameservers Array of nameserver hostnames.
	 * @return array{success: bool, message: string}
	 * @throws ApiException On API error.
	 */
	public function update_nameservers( string $domain_id, array $nameservers ): array {
		$name_servers = array_map(
			static function ( string $hostname ): array {
				return array( 'name' => $hostname );
			},
			$nameservers
		);

		try {
			$response = $this->request_with_retry(
				$this->http,
				$this->auth,
				'PUT',
				"/domains/{$domain_id}",
				array( 'body' => array( 'nameServers' => $name_servers ) )
			);
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to update nameservers for domain {$domain_id}: " . $e->getMessage() );
			throw $e;
		}

		return array(
			'success' => $response['status'] >= 200 && $response['status'] < 300,
			'message' => $response['body']['data']['message'] ?? $response['body']['message'] ?? '',
		);
	}

	/**
	 * Reset nameservers to OpenProvider defaults.
	 *
	 * @param string $domain_id Domain ID.
	 * @return array{success: bool, message: string}
	 * @throws ApiException On API error.
	 */
	public function reset_nameservers( string $domain_id ): array {
		try {
			$response = $this->request_with_retry(
				$this->http,
				$this->auth,
				'PUT',
				"/domains/{$domain_id}",
				array( 'body' => array( 'useDomainProviderNameservers' => true ) )
			);
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to reset nameservers for domain {$domain_id}: " . $e->getMessage() );
			throw $e;
		}

		return array(
			'success' => $response['status'] >= 200 && $response['status'] < 300,
			'message' => $response['body']['data']['message'] ?? $response['body']['message'] ?? '',
		);
	}

	/**
	 * Get DNS records for a domain.
	 *
	 * @param string      $domain_id Domain ID.
	 * @param string|null $type Filter by record type (A, AAAA, CNAME, MX, TXT, SRV, NS, SOA).
	 * @return array DNS records.
	 * @throws ApiException On API error.
	 */
	public function get_dns_records( string $domain_id, ?string $type = null ): array {
		$path = "/dns/zones/{$domain_id}/records";

		if ( null !== $type ) {
			$path .= '?' . http_build_query( array( 'type' => strtoupper( $type ) ) );
		}

		try {
			$response = $this->request_with_retry( $this->http, $this->auth, 'GET', $path );
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to get DNS records for domain {$domain_id}: " . $e->getMessage() );
			throw $e;
		}

		return $this->map_records_response( $response['body'] );
	}

	/**
	 * Add a DNS record.
	 *
	 * @param array $record Record data: domain_id, type, name, value, ttl, priority, weight, port.
	 * @return array{id: string, success: bool}
	 * @throws ApiException On API error.
	 */
	public function add_dns_record( array $record ): array {
		$domain_id = (string) ( $record['domain_id'] ?? '' );

		try {
			$response = $this->request_with_retry(
				$this->http,
				$this->auth,
				'POST',
				"/dns/zones/{$domain_id}/records",
				array( 'body' => $this->map_record_to_request( $record ) )
			);
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to add DNS record for domain {$domain_id}: " . $e->getMessage() );
			throw $e;
		}

		$data = $response['body']['data'] ?? $response['body'];

		return array(
			'id'      => (string) ( $data['id'] ?? '' ),
			'success' => $response['status'] >= 200 && $response['status'] < 300,
		);
	}

	/**
	 * Update a DNS record.
	 *
	 * @param string $record_id Record ID.
	 * @param array  $data Record data to update.
	 * @return array{success: bool}
	 * @throws ApiException On API error.
	 */
	public function update_dns_record( string $record_id, array $data ): array {
		try {
			$response = $this->request_with_retry(
				$this->http,
				$this->auth,
				'PUT',
				"/dns/records/{$record_id}",
				array( 'body' => $this->map_record_to_request( $data ) )
			);
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to update DNS record {$record_id}: " . $e->getMessage() );
			throw $e;
		}

		return array( 'success' => $response['status'] >= 200 && $response['status'] < 300 );
	}

	/**
	 * Delete a DNS record.
	 *
	 * @param string $record_id Record ID.
	 * @return array{success: bool}
	 * @throws ApiException On API error.
	 */
	public function delete_dns_record( string $record_id ): array {
		try {
			$response = $this->request_with_retry( $this->http, $this->auth, 'DELETE', "/dns/records/{$record_id}" );
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to delete DNS record {$record_id}: " . $e->getMessage() );
			throw $e;
		}

		return array( 'success' => $response['status'] >= 200 && $response['status'] < 300 );
	}

	/**
	 * Get supported DNS record types.
	 *
	 * @return array List of supported types.
	 */
	public function get_supported_types(): array {
		return self::SUPPORTED_TYPES;
	}

	/**
	 * Map record data to the API request body.
	 *
	 * @param array $record Record data.
	 * @return array Request body.
	 */
	private function map_record_to_request( array $record ): array {
		$body = array(
			'type'  => strtoupper( (string) ( $record['type'] ?? '' ) ),
			'name'  => $record['name'] ?? '',
			'value' => $record['value'] ?? '',
			'ttl'   => (int) ( $record['ttl'] ?? 3600 ),
		);

		if ( isset( $record['priority'] ) ) {
			$body['prio'] = (int) $record['priority'];
		}

		if ( isset( $record['weight'] ) ) {
			$body['weight'] = (int) $record['weight'];
		}

		if ( isset( $record['port'] ) ) {
			$body['port'] = (int) $record['port'];
		}

		return $body;
	}

	/**
	 * Map raw nameservers response to normalized format.
	 *
	 * @param array $raw Raw API response.
	 * @return array{type: string, servers: array}
	 */
	private function map_nameservers_response( array $raw ): array {
		$data = $raw['data'] ?? $raw;

		$name_servers = $data['nameServers'] ?? $data['name_servers'] ?? array();

		$servers = array_map(
			static function ( $server ) {
				return is_array( $server ) ? ( $server['name'] ?? '' ) : (string) $server;
			},
			$name_servers
		);

		$servers = array_values( array_filter( $servers ) );

		return array(
			'type'    => empty( $servers ) ? 'default' : 'custom',
			'servers' => $servers,
		);
	}

	/**
	 * Map raw DNS records response to normalized format.
	 *
	 * @param array $raw Raw API response.
	 * @return array Normalized DNS records.
	 */
	private function map_records_response( array $raw ): array {
		$data    = $raw['data'] ?? $raw;
		$records = $data['records'] ?? $data;

		return array_map(
			static function ( array $record ): array {
				return array(
					'id'       => (string) ( $record['id'] ?? '' ),
					'type'     => $record['type'] ?? '',
					'name'     => $record['name'] ?? '',
					'value'    => $record['value'] ?? '',
					'ttl'      => (int) ( $record['ttl'] ?? 3600 ),
					'priority' => isset( $record['prio'] ) ? (int) $record['prio'] : null,
				);
			},
			array_values( (array) $records )
		);
	}
}
