<?php
/**
 * PHPStan bootstrap — defines plugin constants and stubs for static analysis.
 * Not loaded at runtime.
 */
define( 'VS_MAILER_PLUGIN_DIR', __DIR__ . '/' );
define( 'VS_MAILER_PLUGIN_URL', 'http://localhost/wp-content/plugins/vs-mailer/' );
define( 'VS_MAILER_VERSION', '1.0.0' );

// Stub for optional v-secrets-manager integration.
if ( ! function_exists( 'vs_secrets_manager_get' ) ) {
	function vs_secrets_manager_get( string $name ): ?string {
		return null;
	}
}

if ( ! class_exists( 'VS_Secrets_Manager_Secret_Manager' ) ) {
	class VS_Secrets_Manager_Secret_Manager {
		public static function set( string $name, string $value, array $meta = array() ): bool {
			return false;
		}
		public static function get( string $name ): ?string {
			return null;
		}
		public static function delete( string $name ): bool {
			return false;
		}
	}
}
