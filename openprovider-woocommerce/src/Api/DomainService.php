<?php
/**
 * Domain Service for OpenProvider WooCommerce
 *
 * Handles domain availability checks and registration.
 *
 * @package OpenProviderWooCommerce\Api
 */

namespace OpenProviderWooCommerce\Api;

use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;

/**
 * Domain Service class.
 */
class DomainService {

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
		$this->http       = $http;
		$this->auth       = $auth;
		$this->settings   = $settings;
		$this->logger     = $logger;
	}

	/**
	 * Check availability for multiple domains.
	 *
	 * @param array $domains Array of domain arrays with 'name' and 'extension' keys.
	 * @return array Array of domain availability results.
	 * @throws ApiException On API error.
	 */
	public function check_bulk( array $domains ): array {
		if ( empty( $domains ) ) {
			return array();
		}

		// Validate and normalize domain input.
		$normalized = array();
		foreach ( $domains as $domain ) {
			$normalized[] = array(
				'name'     => strtolower( trim( $domain['name'] ) ),
				'extension' => strtolower( trim( $domain['extension'] ) ),
			);
		}

		try {
			$response = $this->http->request( 'POST', '/domains/check', array( 'body' => array( 'domains' => $normalized ) ) );
		} catch ( ApiException $e ) {
			// Retry once on 401 after token invalidation.
			if ( 401 === $e->getStatusCode() ) {
				$this->auth->invalidate_token();
				$response = $this->http->request( 'POST', '/domains/check', array( 'body' => array( 'domains' => $normalized ) ) );
			} else {
				throw $e;
			}
		}

		return $this->map_check_response( $response['body'] );
	}

	/**
	 * Check availability for a single domain.
	 *
	 * @param string $name Domain name (without extension).
	 * @param string $extension Domain extension.
	 * @return array Domain availability result.
	 * @throws ApiException On API error.
	 */
	public function check( string $name, string $extension ): array {
		$results = $this->check_bulk( array( array( 'name' => $name, 'extension' => $extension ) ) );
		return $results[0] ?? array();
	}

	/**
	 * Register a domain.
	 *
	 * @param array $registration_data Registration data.
	 * @return array Registration result.
	 * @throws ApiException On API error.
	 */
	public function register( array $registration_data ): array {
		$body = array(
			'name'     => $registration_data['domain_name'],
			'extension' => $registration_data['tld'],
			'period'   => $registration_data['registration_period'] ?? 1,
		);

		// Add contact handles if provided.
		if ( ! empty( $registration_data['owner_handle'] ) ) {
			$body['owner'] = array( 'handle' => $registration_data['owner_handle'] );
		}
		if ( ! empty( $registration_data['admin_handle'] ) ) {
			$body['admin'] = array( 'handle' => $registration_data['admin_handle'] );
		}
		if ( ! empty( $registration_data['tech_handle'] ) ) {
			$body['tech'] = array( 'handle' => $registration_data['tech_handle'] );
		}
		if ( ! empty( $registration_data['billing_handle'] ) ) {
			$body['billing'] = array( 'handle' => $registration_data['billing_handle'] );
		}

		// Add nameservers if provided.
		if ( ! empty( $registration_data['nameservers'] ) && is_array( $registration_data['nameservers'] ) ) {
			$body['nameservers'] = $registration_data['nameservers'];
		}

		// Add TLD-specific additional data.
		if ( ! empty( $registration_data['additional_data'] ) && is_array( $registration_data['additional_data'] ) ) {
			$body['additional_data'] = $registration_data['additional_data'];
		}

		try {
			$response = $this->http->request( 'POST', '/domains', array( 'body' => $body ) );
		} catch ( ApiException $e ) {
			// Retry once on 401 after token invalidation.
			if ( 401 === $e->getStatusCode() ) {
				$this->auth->invalidate_token();
				$response = $this->http->request( 'POST', '/domains', array( 'body' => $body ) );
			} else {
				throw $e;
			}
		}

		return $this->map_create_response( $response['body'] );
	}

	/**
	 * Map raw check response to normalized format.
	 *
	 * @param array $raw Raw API response.
	 * @return array Normalized domain availability results.
	 */
	private function map_check_response( array $raw ): array {
		$results = array();

		$domains = $raw['data']['domains'] ?? $raw['domains'] ?? array();

		foreach ( $domains as $domain ) {
			$name = $domain['name'] ?? $domain['data']['name'] ?? '';
			$extension = $domain['extension'] ?? $domain['data']['extension'] ?? '';
			$status = $domain['status'] ?? $domain['data']['status'] ?? 'unknown';
			$is_available = in_array( $status, array( 'free', 'available' ), true );
			$premium = isset( $domain['premium'] ) ? (bool) $domain['premium'] : false;

			// Extract price if available.
			$price = 0;
			$currency = 'EUR';
			if ( ! empty( $domain['price'] ) ) {
				$price = (float) ( $domain['price']['value'] ?? $domain['price'] ?? 0 );
				$currency = strtoupper( $domain['price']['currency'] ?? 'EUR' );
			}

			$results[] = array(
				'name'       => $name,
				'extension'  => $extension,
				'status'     => $status,
				'available'  => $is_available,
				'premium'    => $premium,
				'price'      => $price,
				'currency'   => $currency,
			);
		}

		return $results;
	}

	/**
	 * Map raw create response to normalized format.
	 *
	 * @param array $raw Raw API response.
	 * @return array Normalized registration result.
	 */
	private function map_create_response( array $raw ): array {
		$data = $raw['data'] ?? $raw;

		return array(
			'domain_id'   => $data['id'] ?? $data['domainId'] ?? '',
			'order_id'    => $data['orderId'] ?? $data['order_id'] ?? '',
			'status'      => $data['status'] ?? 'pending',
			'created_at'  => $data['createdAt'] ?? $data['created_at'] ?? null,
		);
	}
}
