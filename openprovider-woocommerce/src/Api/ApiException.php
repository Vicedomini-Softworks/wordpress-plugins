<?php
/**
 * API Exception class for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Api
 */

namespace OpenProviderWooCommerce\Api;

/**
 * API Exception class.
 */
class ApiException extends \Exception {

	/**
	 * HTTP status code.
	 *
	 * @var int
	 */
	private int $status_code;

	/**
	 * API response body.
	 *
	 * @var array
	 */
	private array $response_body;

	/**
	 * Constructor.
	 *
	 * @param string $message Exception message.
	 * @param int    $status_code HTTP status code.
	 * @param array  $response_body API response body.
	 * @param int    $code Exception code.
	 * @param \Throwable|null $previous Previous exception.
	 */
	public function __construct(
		string $message,
		int $status_code,
		array $response_body = array(),
		int $code = 0,
		?\Throwable $previous = null
	) {
		parent::__construct( $message, $code, $previous );
		$this->status_code  = $status_code;
		$this->response_body = $response_body;
	}

	/**
	 * Get HTTP status code.
	 *
	 * @return int HTTP status code.
	 */
	public function getStatusCode(): int {
		return $this->status_code;
	}

	/**
	 * Get response body.
	 *
	 * @return array Response body.
	 */
	public function getResponseBody(): array {
		return $this->response_body;
	}
}
