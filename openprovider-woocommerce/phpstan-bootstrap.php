<?php
/**
 * PHPStan bootstrap file for OpenProvider WooCommerce
 *
 * Defines stubs for WordPress functions not available during static analysis.
 */

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Stub for get_option().
	 */
	function get_option( string $option, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Stub for update_option().
	 */
	function update_option( string $option, $value, $autoload = null ): bool {
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Stub for delete_option().
	 */
	function delete_option( string $option ): bool {
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Stub for add_action().
	 */
	function add_action( string $tag, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Stub for add_filter().
	 */
	function add_filter( string $tag, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		return true;
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * Stub for wp_create_nonce().
	 */
	function wp_create_nonce( string $action = -1 ): string {
		return 'stub_nonce';
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * Stub for wp_verify_nonce().
	 */
	function wp_verify_nonce( string $nonce, string $action = -1 ): bool {
		return true;
	}
}

if ( ! function_exists( 'wc_get_logger' ) ) {
	/**
	 * Stub for wc_get_logger().
	 */
	function wc_get_logger(): \WC_Logger_Interface {
		return new class implements \WC_Logger_Interface {
			public function debug( string $message, array $context = array() ): void {}
			public function info( string $message, array $context = array() ): void {}
			public function notice( string $message, array $context = array() ): void {}
			public function warning( string $message, array $context = array() ): void {}
			public function error( string $message, array $context = array() ): void {}
			public function log( string $level, string $message, array $context = array() ): void {}
		};
	}
}

if ( ! function_exists( 'get_woocommerce_currency' ) ) {
	/**
	 * Stub for get_woocommerce_currency().
	 */
	function get_woocommerce_currency(): string {
		return 'EUR';
	}
}

if ( ! function_exists( 'vs_secrets_manager_get' ) ) {
	/**
	 * Stub for vs_secrets_manager_get() from v-secrets-manager plugin.
	 *
	 * @param string $name Secret name.
	 * @return string|null Secret value or null if not found.
	 */
	function vs_secrets_manager_get( string $name ): ?string {
		return null;
	}
}
