<?php
/**
 * PHPStan bootstrap — defines plugin constants and stubs for static analysis.
 * Not loaded at runtime.
 */
define( 'V_AUTH_PLUGIN_DIR', __DIR__ . '/' );
define( 'V_AUTH_PLUGIN_URL', 'http://localhost/wp-content/plugins/v-auth/' );
define( 'V_AUTH_VERSION', '1.0.0' );

// Stubs for the V-Secrets Manager integration.
if ( ! function_exists( 'vs_secrets_manager_get' ) ) {
	function vs_secrets_manager_get( string $name ): ?string {
		return null;
	}
}

if ( ! class_exists( 'VS_Secrets_Manager_Secret_Manager' ) ) {
	class VS_Secrets_Manager_Secret_Manager {
		/**
		 * @phpstan-impure Persists the secret via the resolved provider.
		 */
		public static function set( string $name, string $value, array $meta = array() ): bool {
			return false;
		}
		public static function get( string $name ): ?string {
			return null;
		}
		/**
		 * @phpstan-impure Removes the secret via the resolved provider.
		 */
		public static function delete( string $name ): bool {
			return false;
		}
	}
}
