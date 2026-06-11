<?php
/**
 * RateLimiter class for OpenProvider WooCommerce
 *
 * Transient-based rate limiting for public REST endpoints.
 *
 * @package OpenProviderWooCommerce\Support
 */

namespace OpenProviderWooCommerce\Support;

/**
 * RateLimiter class.
 */
class RateLimiter {

	/**
	 * Transient prefix.
	 */
	private const PREFIX = 'opwc_rl_';

	/**
	 * Check if request is allowed.
	 *
	 * @param string $bucket_key Rate limit bucket key.
	 * @param int    $max_requests Maximum requests allowed.
	 * @param int    $window_seconds Time window in seconds.
	 * @return bool True if request allowed, false if rate limited.
	 */
	public function check( string $bucket_key, int $max_requests, int $window_seconds ): bool {
		$transient_key = self::PREFIX . $bucket_key;
		$data          = get_transient( $transient_key );

		if ( false === $data ) {
			// First request in this window.
			set_transient(
				$transient_key,
				array(
					'count'  => 1,
					'reset'  => time() + $window_seconds,
				),
				$window_seconds
			);
			return true;
		}

		if ( ! is_array( $data ) ) {
			// Corrupted data, reset.
			set_transient(
				$transient_key,
				array(
					'count'  => 1,
					'reset'  => time() + $window_seconds,
				),
				$window_seconds
			);
			return true;
		}

		// Check if window has expired.
		if ( isset( $data['reset'] ) && time() >= $data['reset'] ) {
			// Window expired, reset counter.
			set_transient(
				$transient_key,
				array(
					'count'  => 1,
					'reset'  => time() + $window_seconds,
				),
				$window_seconds
			);
			return true;
		}

		// Check if limit exceeded.
		if ( isset( $data['count'] ) && $data['count'] >= $max_requests ) {
			return false;
		}

		// Increment counter.
		$data['count'] = ( $data['count'] ?? 0 ) + 1;
		set_transient( $transient_key, $data, max( 60, $data['reset'] - time() ) );

		return true;
	}

	/**
	 * Build bucket key from request.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return string Bucket key hash.
	 */
	public function bucket_key_for_request( \WP_REST_Request $request ): string {
		$ip = $this->get_client_ip();
		$route = $request->get_route();
		return md5( $ip . ':' . $route );
	}

	/**
	 * Get client IP address.
	 *
	 * @return string Client IP address.
	 */
	private function get_client_ip(): string {
		$ip = '';

		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
			$ip = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$ip = explode( ',', $ip )[0]; // First IP in chain.
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
			$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] );
		}

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown';
	}
}
