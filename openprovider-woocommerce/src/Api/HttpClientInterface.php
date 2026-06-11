<?php
/**
 * HTTP Client Interface for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Api
 */

namespace OpenProviderWooCommerce\Api;

/**
 * HTTP Client Interface.
 */
interface HttpClientInterface {

	/**
	 * Make HTTP request.
	 *
	 * @param string $method HTTP method (GET, POST, etc.).
	 * @param string $path API path (without base URL).
	 * @param array  $options Request options (body, headers, etc.).
	 * @return array Response with 'status', 'body', 'headers' keys.
	 * @throws ApiException On request failure.
	 */
	public function request( string $method, string $path, array $options = array() ): array;
}
