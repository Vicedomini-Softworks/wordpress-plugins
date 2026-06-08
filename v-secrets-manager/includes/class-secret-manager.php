<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Secrets_Manager_Secret_Manager {

	private static $providers = array();

	private static function get_provider( string $provider_name ): ?VS_Secrets_Manager_Provider {
		if ( ! isset( self::$providers[ $provider_name ] ) ) {
			switch ( $provider_name ) {
				case 'db':
					self::$providers[ $provider_name ] = new VS_Secrets_Manager_DB_Provider();
					break;
				case 'aws':
					self::$providers[ $provider_name ] = new VS_Secrets_Manager_AWS_Provider();
					break;
				case 'vault':
					self::$providers[ $provider_name ] = new VS_Secrets_Manager_Vault_Provider();
					break;
				default:
					return null;
			}
		}

		return self::$providers[ $provider_name ];
	}

	public static function get( string $name ): ?string {
		$record = self::get_record( $name );

		if ( null === $record ) {
			return null;
		}

		$provider = self::get_provider( $record->provider );

		if ( null === $provider ) {
			return null;
		}

		return $provider->get( $name );
	}

	/**
	 * @phpstan-impure Persists the secret via the resolved provider.
	 */
	public static function set( string $name, string $value, array $meta = array() ): bool {
		$provider_name = $meta['provider'] ?? 'db';
		$provider      = self::get_provider( $provider_name );

		if ( null === $provider ) {
			return false;
		}

		return $provider->set( $name, $value, $meta );
	}

	/**
	 * @phpstan-impure Removes the secret via the resolved provider.
	 */
	public static function delete( string $name ): bool {
		$record = self::get_record( $name );

		if ( null === $record ) {
			return false;
		}

		$provider = self::get_provider( $record->provider );

		if ( null === $provider ) {
			return false;
		}

		$deleted = $provider->delete( $name );

		if ( $deleted ) {
			global $wpdb;
			$wpdb->delete(
				$wpdb->prefix . 'vsecrets_secrets',
				array( 'name' => $name )
			);
		}

		return $deleted;
	}

	public static function get_record( string $name ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}vsecrets_secrets WHERE name = %s",
				$name
			)
		);

		return $row ? $row : null;
	}

	public static function get_record_by_id( int $id ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}vsecrets_secrets WHERE id = %d",
				$id
			)
		);

		return $row ? $row : null;
	}

	public static function get_secrets_list(): array {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT id, name, title, provider, status, last_rotated, created_at, updated_at
			 FROM {$wpdb->prefix}vsecrets_secrets
			 ORDER BY name ASC"
		);
	}

	public static function test_connection( string $provider_name ): array {
		$provider = self::get_provider( $provider_name );

		if ( null === $provider ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: provider name */
					__( 'Unknown provider: %s', 'vs-secrets-manager' ),
					$provider_name
				),
			);
		}

		return $provider->test_connection();
	}
}
