<?php
/**
 * WordPress HTTP Client implementation for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Api
 */

namespace OpenProviderWooCommerce\Api;

use OpenProviderWooCommerce\Support\Logger;

/**
 * WordPress HTTP Client implementation.
 */
class WpHttpClient implements HttpClientInterface {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private string $base_url;

	/**
	 * Bearer token for authentication.
	 *
	 * @var string|null
	 */
	private ?string $bearer_token;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param string   $base_url API base URL.
	 * @param string   $bearer_token Bearer token (optional).
	 * @param Logger   $logger Logger instance.
	 */
	public function __construct(
		string $base_url,
		?string $bearer_token = null,
		Logger $logger
	) {
		$this->base_url     = rtrim( $base_url, '/' );
		$this->bearer_token = $bearer_token;
		$this->logger       = $logger;
	}

	/**
	 * Make HTTP request.
	 *
	 * @param string $method HTTP method.
	 * @param string $path API path.
	 * @param array  $options Request options.
	 * @return array Response with 'status', 'body', 'headers'.
	 * @throws ApiException On request failure.
	 */
	public function request( string $method, string $path, array $options = array() ): array {
		$url    = $this->base_url . '/' . ltrim( $path, '/' );
		$headers = array(
			'Content-Type' => 'application/json',
		);

		if ( $this->bearer_token ) {
			$headers['Authorization'] = 'Bearer ' . $this->bearer_token;
		}

		// Merge custom headers.
		if ( isset( $options['headers'] ) && is_array( $options['headers'] ) ) {
			$headers = array_merge( $headers, $options['headers'] );
		}

		$body = null;
		if ( isset( $options['body'] ) && is_array( $options['body'] ) ) {
			$body = wp_json_encode( $options['body'] );
		}

		$this->logger->debug( "API Request: {$method} {$url}", array( 'body' => $options['body'] ?? null ) );

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'body'    => $body,
			'timeout' => 30,
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'API Request failed: ' . $response->get_error_message() );
			throw new ApiException(
				'API request failed: ' . $response->get_error_message(),
				500,
				array( 'error' => 'wp_error', 'message' => $response->get_error_message() )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$headers     = wp_remote_retrieve_headers( $response );

		$this->logger->debug( "API Response: {$status_code}", array( 'body' => $body ) );

		// Parse JSON response.
		$body_data = json_decode( $body, true ) ?? array();

		if ( $status_code < 200 || $status_code >= 300 ) {
			throw new ApiException(
				$this->get_error_message( $status_code, $body_data ),
				$status_code,
				$body_data
			);
		}

		return array(
			'status'  => $status_code,
			'body'    => $body_data,
			'headers' => $headers,
		);
	}

	/**
	 * Get error message from response.
	 *
	 * @param int   $status_code HTTP status code.
	 * @param array $body_data Response body data.
	 * @return string Error message.
	 */
	private function get_error_message( int $status_code, array $body_data ): string {
		if ( isset( $body_data['error']['message'] ) ) {
			return $body_data['error']['message'];
		}
		if ( isset( $body_data['message'] ) ) {
			return $body_data['message'];
		}
		return "HTTP {$status_code} error";
	}
}
