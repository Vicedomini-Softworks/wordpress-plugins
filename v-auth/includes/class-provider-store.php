<?php
/**
 * Stores and retrieves OIDC provider configurations.
 *
 * Non-sensitive fields live in the `v_auth_providers` option; each provider's
 * client secret is written through V-Secrets Manager, mirroring the
 * sensitive/metadata split used by Social Feed's provider credentials.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class V_Auth_Provider_Store {

	const OPTION_KEY = 'v_auth_providers';

	/**
	 * Get all configured providers, including their client secret.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		$providers = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $providers ) ) {
			return array();
		}

		foreach ( $providers as $id => &$provider ) {
			$provider['client_secret'] = vs_secrets_manager_get( self::secret_key( (string) $id ) ) ?? '';
		}

		return $providers;
	}

	/**
	 * Get a single provider config by id.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get( string $id ): ?array {
		$providers = self::all();
		return $providers[ $id ] ?? null;
	}

	/**
	 * Save (create or update) a provider config.
	 *
	 * @param array<string, mixed> $data Expects: display_name, issuer, client_id, client_secret, scopes, button_label.
	 */
	public static function save( string $id, array $data ): void {
		$providers = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $providers ) ) {
			$providers = array();
		}

		if ( ! empty( $data['client_secret'] ) && class_exists( 'VS_Secrets_Manager_Secret_Manager' ) ) {
			VS_Secrets_Manager_Secret_Manager::set(
				self::secret_key( $id ),
				$data['client_secret'],
				array(
					'title'    => 'V-Auth ' . $data['display_name'] . ' Client Secret',
					'provider' => 'db',
				)
			);
		}
		unset( $data['client_secret'] );

		$providers[ $id ] = $data;
		update_option( self::OPTION_KEY, $providers, false );
	}

	public static function delete( string $id ): void {
		$providers = get_option( self::OPTION_KEY, array() );
		if ( is_array( $providers ) ) {
			unset( $providers[ $id ] );
			update_option( self::OPTION_KEY, $providers, false );
		}

		if ( class_exists( 'VS_Secrets_Manager_Secret_Manager' ) ) {
			VS_Secrets_Manager_Secret_Manager::delete( self::secret_key( $id ) );
		}
	}

	public static function secret_key( string $id ): string {
		return 'v_auth_' . $id . '_client_secret';
	}
}
