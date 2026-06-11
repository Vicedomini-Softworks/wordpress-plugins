<?php
/**
 * Renewal Service for OpenProvider WooCommerce
 *
 * Handles domain renewal pricing, renewal, and auto-renewal toggling.
 *
 * @package OpenProviderWooCommerce\Api
 */

namespace OpenProviderWooCommerce\Api;

use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;

/**
 * Renewal Service class.
 */
class RenewalService {

	use RequestRetryTrait;

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
	 * Get renewal price for a domain.
	 *
	 * @param string $name Domain name (without extension).
	 * @param string $tld Domain extension.
	 * @param int    $period Renewal period in years.
	 * @return array{price: float, currency: string, current_expiry: ?string}
	 * @throws ApiException On API error.
	 */
	public function get_renewal_price( string $name, string $tld, int $period = 1 ): array {
		$query_params = array(
			'domain.name'      => $name,
			'domain.extension' => $tld,
			'operation'        => 'renew',
			'period'           => $period,
		);

		try {
			$response = $this->request_with_retry(
				$this->http,
				$this->auth,
				'GET',
				'/domains/prices?' . http_build_query( $query_params )
			);
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to get renewal price for {$name}.{$tld}: " . $e->getMessage() );
			throw $e;
		}

		return $this->map_price_response( $response['body'] );
	}

	/**
	 * Renew a domain.
	 *
	 * @param array $data Renewal data with 'domain_id' and 'period' keys.
	 * @return array{success: bool, new_expiry: ?string, order_id: string}
	 * @throws ApiException On API error.
	 */
	public function renew_domain( array $data ): array {
		$domain_id = (string) ( $data['domain_id'] ?? '' );
		$period    = (int) ( $data['period'] ?? 1 );

		try {
			$response = $this->request_with_retry(
				$this->http,
				$this->auth,
				'POST',
				"/domains/{$domain_id}/renew",
				array( 'body' => array( 'period' => $period ) )
			);
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to renew domain {$domain_id}: " . $e->getMessage() );
			throw $e;
		}

		return $this->map_renew_response( $response['body'] );
	}

	/**
	 * Get domain details (including expiry).
	 *
	 * @param string $domain_id OpenProvider domain ID.
	 * @return array{name: string, extension: string, expiry_date: ?string, status: string}
	 * @throws ApiException On API error.
	 */
	public function get_domain_details( string $domain_id ): array {
		try {
			$response = $this->request_with_retry( $this->http, $this->auth, 'GET', "/domains/{$domain_id}" );
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to get domain details for {$domain_id}: " . $e->getMessage() );
			throw $e;
		}

		return $this->map_details_response( $response['body'] );
	}

	/**
	 * Enable auto-renewal for a domain.
	 *
	 * @param string $domain_id Domain ID.
	 * @return array{success: bool}
	 * @throws ApiException On API error.
	 */
	public function enable_auto_renewal( string $domain_id ): array {
		return $this->set_auto_renewal( $domain_id, true );
	}

	/**
	 * Disable auto-renewal for a domain.
	 *
	 * @param string $domain_id Domain ID.
	 * @return array{success: bool}
	 * @throws ApiException On API error.
	 */
	public function disable_auto_renewal( string $domain_id ): array {
		return $this->set_auto_renewal( $domain_id, false );
	}

	/**
	 * Set auto-renewal flag for a domain.
	 *
	 * @param string $domain_id Domain ID.
	 * @param bool   $enabled Whether to enable auto-renewal.
	 * @return array{success: bool}
	 * @throws ApiException On API error.
	 */
	private function set_auto_renewal( string $domain_id, bool $enabled ): array {
		try {
			$response = $this->request_with_retry(
				$this->http,
				$this->auth,
				'PUT',
				"/domains/{$domain_id}",
				array( 'body' => array( 'autorenew' => $enabled ) )
			);
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to set auto-renewal for domain {$domain_id}: " . $e->getMessage() );
			throw $e;
		}

		return array( 'success' => $response['status'] >= 200 && $response['status'] < 300 );
	}

	/**
	 * Map raw price response to normalized format.
	 *
	 * @param array $raw Raw API response.
	 * @return array Normalized renewal pricing data.
	 */
	private function map_price_response( array $raw ): array {
		$data = $raw['data'] ?? $raw;

		$price    = $data['price']['value'] ?? $data['price'] ?? 0;
		$currency = $data['price']['currency'] ?? $data['currency'] ?? 'EUR';

		return array(
			'price'          => (float) $price,
			'currency'       => strtoupper( (string) $currency ),
			'current_expiry' => $data['expirationDate'] ?? $data['expiration_date'] ?? null,
		);
	}

	/**
	 * Map raw renew response to normalized format.
	 *
	 * @param array $raw Raw API response.
	 * @return array Normalized renewal result.
	 */
	private function map_renew_response( array $raw ): array {
		$data = $raw['data'] ?? $raw;

		return array(
			'success'    => true,
			'new_expiry' => $data['expirationDate'] ?? $data['expiration_date'] ?? null,
			'order_id'   => (string) ( $data['orderId'] ?? $data['order_id'] ?? $data['id'] ?? '' ),
		);
	}

	/**
	 * Map raw domain details response to normalized format.
	 *
	 * @param array $raw Raw API response.
	 * @return array Normalized domain details.
	 */
	private function map_details_response( array $raw ): array {
		$data   = $raw['data'] ?? $raw;
		$domain = $data['domain'] ?? $data;

		return array(
			'name'        => $domain['name'] ?? '',
			'extension'   => $domain['extension'] ?? '',
			'expiry_date' => $data['expirationDate'] ?? $data['expiration_date'] ?? null,
			'status'      => $data['status'] ?? 'unknown',
		);
	}
}
