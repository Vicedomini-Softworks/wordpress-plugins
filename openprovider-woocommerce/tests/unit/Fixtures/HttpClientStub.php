<?php
/**
 * HTTP Client Stub for unit tests
 *
 * @package OpenProviderWooCommerce\Tests\Fixtures
 */

namespace OpenProviderWooCommerce\Tests\Fixtures;

use OpenProviderWooCommerce\Api\HttpClientInterface;
use OpenProviderWooCommerce\Api\ApiException;

/**
 * HTTP Client Stub implementation.
 */
class HttpClientStub implements HttpClientInterface {

	/**
	 * Queue of canned responses.
	 *
	 * @var array
	 */
	public array $responses = array();

	/**
	 * Recorded requests for assertions.
	 *
	 * @var array
	 */
	public array $requests = array();

	/**
	 * Make HTTP request.
	 *
	 * @param string $method HTTP method.
	 * @param string $path API path.
	 * @param array  $options Request options.
	 * @return array Response.
	 * @throws ApiException
	 */
	public function request( string $method, string $path, array $options = array() ): array {
		// Record the request.
		$this->requests[] = array(
			'method' => $method,
			'path' => $path,
			'options' => $options,
		);

		// Return next response in queue.
		if ( ! empty( $this->responses ) ) {
			return array_shift( $this->responses );
		}

		// Default empty response.
		return array(
			'status' => 200,
			'body' => array(),
			'headers' => array(),
		);
	}

	/**
	 * Add a response to the queue.
	 *
	 * @param int    $status HTTP status code.
	 * @param array  $body Response body.
	 * @param array  $headers Response headers.
	 */
	public function add_response( int $status, array $body, array $headers = array() ): void {
		$this->responses[] = array(
			'status' => $status,
			'body' => $body,
			'headers' => $headers,
		);
	}

	/**
	 * Add an error response to the queue.
	 *
	 * @param int    $status HTTP status code.
	 * @param string $message Error message.
	 */
	public function add_error_response( int $status, string $message ): void {
		$this->responses[] = array(
			'status' => $status,
			'body' => array(
				'error' => array(
					'message' => $message,
				),
			),
			'headers' => array(),
		);
	}
}
