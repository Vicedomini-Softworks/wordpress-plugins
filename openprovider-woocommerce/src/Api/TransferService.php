<?php
/**
 * Transfer Service for OpenProvider WooCommerce
 *
 * Handles domain transfer eligibility checks, initiation, status, and completion.
 *
 * @package OpenProviderWooCommerce\Api
 */

namespace OpenProviderWooCommerce\Api;

use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;

/**
 * Transfer Service class.
 */
class TransferService {

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
	 * Check if a domain can be transferred.
	 *
	 * @param string $name Domain name (without extension).
	 * @param string $tld Domain extension.
	 * @return array{available: bool, price: float, currency: string, requires_auth_code: bool}
	 * @throws ApiException On API error.
	 */
	public function check_transfer( string $name, string $tld ): array {
		try {
			$response = $this->request_with_retry(
				$this->http,
				$this->auth,
				'POST',
				'/transfers/check',
				array(
					'body' => array(
						'name'      => $name,
						'extension' => $tld,
					),
				)
			);
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to check transfer eligibility for {$name}.{$tld}: " . $e->getMessage() );
			throw $e;
		}

		return $this->map_check_response( $response['body'] );
	}

	/**
	 * Initiate a domain transfer.
	 *
	 * @param array $data Transfer data: name, extension, auth_code, owner_handle, admin_handle, tech_handle, billing_handle.
	 * @return array{transfer_id: string, status: string, estimated_completion: ?string}
	 * @throws ApiException On API error.
	 */
	public function initiate_transfer( array $data ): array {
		$body = array(
			'name'      => $data['name'] ?? '',
			'extension' => $data['extension'] ?? '',
			'authCode'  => $data['auth_code'] ?? '',
			'owner'     => array( 'handle' => $data['owner_handle'] ?? '' ),
			'admin'     => array( 'handle' => $data['admin_handle'] ?? '' ),
			'tech'      => array( 'handle' => $data['tech_handle'] ?? '' ),
			'billing'   => array( 'handle' => $data['billing_handle'] ?? '' ),
		);

		try {
			$response = $this->request_with_retry(
				$this->http,
				$this->auth,
				'POST',
				'/transfers',
				array( 'body' => $body )
			);
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to initiate transfer for {$body['name']}.{$body['extension']}: " . $e->getMessage() );
			throw $e;
		}

		return $this->map_initiate_response( $response['body'] );
	}

	/**
	 * Get transfer status.
	 *
	 * @param string $transfer_id Transfer ID from OpenProvider.
	 * @return array{status: string, progress: int, message: ?string}
	 * @throws ApiException On API error.
	 */
	public function get_transfer_status( string $transfer_id ): array {
		try {
			$response = $this->request_with_retry( $this->http, $this->auth, 'GET', "/transfers/{$transfer_id}" );
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to get transfer status for {$transfer_id}: " . $e->getMessage() );
			throw $e;
		}

		return $this->map_status_response( $response['body'] );
	}

	/**
	 * Complete a transfer with an auth/EPP code.
	 *
	 * @param string $transfer_id Transfer ID.
	 * @param string $auth_code EPP/auth code.
	 * @return array{success: bool, message: string}
	 * @throws ApiException On API error.
	 */
	public function complete_transfer( string $transfer_id, string $auth_code ): array {
		try {
			$response = $this->request_with_retry(
				$this->http,
				$this->auth,
				'PUT',
				"/transfers/{$transfer_id}",
				array( 'body' => array( 'authCode' => $auth_code ) )
			);
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to complete transfer {$transfer_id}: " . $e->getMessage() );
			throw $e;
		}

		return array(
			'success' => $response['status'] >= 200 && $response['status'] < 300,
			'message' => $response['body']['data']['message'] ?? $response['body']['message'] ?? '',
		);
	}

	/**
	 * Cancel a pending transfer.
	 *
	 * @param string $transfer_id Transfer ID.
	 * @return array{success: bool, message: string}
	 * @throws ApiException On API error.
	 */
	public function cancel_transfer( string $transfer_id ): array {
		try {
			$response = $this->request_with_retry( $this->http, $this->auth, 'DELETE', "/transfers/{$transfer_id}" );
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to cancel transfer {$transfer_id}: " . $e->getMessage() );
			throw $e;
		}

		return array(
			'success' => $response['status'] >= 200 && $response['status'] < 300,
			'message' => $response['body']['data']['message'] ?? $response['body']['message'] ?? '',
		);
	}

	/**
	 * Map raw transfer-check response to normalized format.
	 *
	 * @param array $raw Raw API response.
	 * @return array Normalized transfer eligibility data.
	 */
	private function map_check_response( array $raw ): array {
		$data = $raw['data'] ?? $raw;

		return array(
			'available'          => (bool) ( $data['transferable'] ?? false ),
			'price'              => (float) ( $data['price']['value'] ?? $data['price'] ?? 0 ),
			'currency'           => strtoupper( (string) ( $data['price']['currency'] ?? $data['currency'] ?? 'EUR' ) ),
			'requires_auth_code' => (bool) ( $data['requiresAuthCode'] ?? true ),
		);
	}

	/**
	 * Map raw transfer-initiate response to normalized format.
	 *
	 * @param array $raw Raw API response.
	 * @return array Normalized transfer initiation data.
	 */
	private function map_initiate_response( array $raw ): array {
		$data = $raw['data'] ?? $raw;

		return array(
			'transfer_id'          => (string) ( $data['id'] ?? '' ),
			'status'               => $data['status'] ?? 'pending',
			'estimated_completion' => $data['estimatedCompletion'] ?? null,
		);
	}

	/**
	 * Map raw transfer-status response to normalized format.
	 *
	 * @param array $raw Raw API response.
	 * @return array Normalized transfer status data.
	 */
	private function map_status_response( array $raw ): array {
		$data = $raw['data'] ?? $raw;

		return array(
			'status'   => $data['status'] ?? 'unknown',
			'progress' => (int) ( $data['progress'] ?? 0 ),
			'message'  => $data['message'] ?? null,
		);
	}
}
