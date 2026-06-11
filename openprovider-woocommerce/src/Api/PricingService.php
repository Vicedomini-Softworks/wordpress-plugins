<?php
/**
 * Pricing Service for OpenProvider WooCommerce
 *
 * Handles domain pricing retrieval from OpenProvider API.
 *
 * @package OpenProviderWooCommerce\Api
 */

namespace OpenProviderWooCommerce\Api;

use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;

/**
 * Pricing Service class.
 */
class PricingService {

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
	 * @param Logger              $logger Logger.
	 */
	public function __construct(
		HttpClientInterface $http,
		AuthService $auth,
		Logger $logger
	) {
		$this->http   = $http;
		$this->auth   = $auth;
		$this->logger = $logger;
	}

	/**
	 * Get pricing for a domain.
	 *
	 * @param string      $name Domain name (without extension).
	 * @param string      $extension Domain extension (e.g., 'com').
	 * @param string      $operation Operation type (create, renew, transfer).
	 * @param int         $period Registration period in years.
	 * @param string|null $idn_script IDN script for applicable TLDs.
	 * @return array Pricing data with price, currency, premium.
	 * @throws ApiException On API error.
	 */
	public function get_price(
		string $name,
		string $extension,
		string $operation = 'create',
		int $period = 1,
		?string $idn_script = null
	): array {
		$query_params = array(
			'domain.name'    => $name,
			'domain.extension' => $extension,
			'operation'      => $operation,
			'period'         => $period,
		);

		if ( $idn_script ) {
			$query_params['additional_data.idn_script'] = $idn_script;
		}

		// Build query string.
		$query_string = http_build_query( $query_params );

		try {
			$response = $this->http->request( 'GET', '/domains/prices?' . $query_string );
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to get pricing for {$name}.{$extension}: " . $e->getMessage() );
			throw $e;
		}

		return $this->map_price_response( $response['body'] );
	}

	/**
	 * Map raw price response to normalized format.
	 *
	 * @param array $raw Raw API response.
	 * @return array Normalized pricing data.
	 */
	private function map_price_response( array $raw ): array {
		// Try different possible field names (API variations).
		$price = $raw['data']['price'] ?? $raw['price'] ?? $raw['data']['product']['price'] ?? 0;
		$currency = $raw['data']['currency'] ?? $raw['currency'] ?? $raw['data']['product']['currency'] ?? 'EUR';
		$premium = isset( $raw['data']['premium'] ) ? (bool) $raw['data']['premium'] : false;

		return array(
			'price'     => (float) $price,
			'currency'  => strtoupper( $currency ),
			'premium'   => $premium,
		);
	}
}
