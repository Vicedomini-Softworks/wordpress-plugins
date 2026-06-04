<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Secrets_Manager_DB_Provider extends VS_Secrets_Manager_Provider {

	public function get( string $name ): ?string {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT value FROM {$wpdb->prefix}vsecrets_secrets WHERE name = %s AND provider = 'db'",
			$name
		) );

		if ( ! $row || null === $row->value ) {
			return null;
		}

		$decrypted = VS_Secrets_Manager_Encryption::decrypt( $row->value );

		if ( $decrypted === $row->value ) {
			return null;
		}

		return $decrypted;
	}

	public function set( string $name, string $value, array $meta = array() ): bool {
		global $wpdb;

		$encrypted = VS_Secrets_Manager_Encryption::encrypt( $value );
		$title     = $meta['title'] ?? '';
		$now       = current_time( 'mysql', true );

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}vsecrets_secrets WHERE name = %s",
			$name
		) );

		if ( $existing ) {
			return (bool) $wpdb->update(
				$wpdb->prefix . 'vsecrets_secrets',
				array(
					'value'             => $encrypted,
					'encryption_method' => 'aes-256-cbc',
					'title'             => $title,
					'status'            => $meta['status'] ?? 'active',
					'last_rotated'      => $now,
					'updated_at'        => $now,
				),
				array( 'name' => $name )
			);
		}

		return (bool) $wpdb->insert(
			$wpdb->prefix . 'vsecrets_secrets',
			array(
				'name'              => $name,
				'title'             => $title,
				'provider'          => 'db',
				'value'             => $encrypted,
				'encryption_method' => 'aes-256-cbc',
				'status'            => $meta['status'] ?? 'active',
				'last_rotated'      => $now,
				'created_at'        => $now,
				'updated_at'        => $now,
			)
		);
	}

	public function delete( string $name ): bool {
		global $wpdb;

		return (bool) $wpdb->delete(
			$wpdb->prefix . 'vsecrets_secrets',
			array( 'name' => $name, 'provider' => 'db' )
		);
	}

	public function test_connection(): array {
		$test_value = 'vs_secrets_manager_connection_test_' . time();
		$encrypted  = VS_Secrets_Manager_Encryption::encrypt( $test_value );
		$decrypted  = VS_Secrets_Manager_Encryption::decrypt( $encrypted );

		if ( $test_value === $decrypted ) {
			return array(
				'success' => true,
				'message' => __( 'Encryption and decryption working correctly.', 'vs-secrets-manager' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Encryption test failed. Check your wp_salt() configuration.', 'vs-secrets-manager' ),
		);
	}
}
