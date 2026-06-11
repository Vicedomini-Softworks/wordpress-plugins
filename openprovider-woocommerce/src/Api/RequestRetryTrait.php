<?php
/**
 * Request Retry Trait for OpenProvider WooCommerce
 *
 * Shared 401-retry-once logic for API service classes.
 *
 * @package OpenProviderWooCommerce\Api
 */

namespace OpenProviderWooCommerce\Api;

/**
 * Request Retry Trait.
 */
trait RequestRetryTrait {

	/**
	 * Perform an HTTP request, retrying once after token invalidation on 401.
	 *
	 * @param HttpClientInterface $http HTTP client.
	 * @param AuthService         $auth Auth service.
	 * @param string              $method HTTP method.
	 * @param string              $path API path.
	 * @param array               $options Request options.
	 * @return array Response.
	 * @throws ApiException On API error.
	 */
	private function request_with_retry( HttpClientInterface $http, AuthService $auth, string $method, string $path, array $options = array() ): array {
		try {
			return $http->request( $method, $path, $options );
		} catch ( ApiException $e ) {
			if ( 401 === $e->getStatusCode() ) {
				$auth->invalidate_token();
				return $http->request( $method, $path, $options );
			}
			throw $e;
		}
	}
}
