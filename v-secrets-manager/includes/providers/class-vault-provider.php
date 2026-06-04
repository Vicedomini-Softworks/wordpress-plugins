<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Secrets_Manager_Vault_Provider extends VS_Secrets_Manager_Provider {

	public function get( string $name ): ?string {
		$address   = get_option( 'vs_secrets_manager_vault_address' );
		$token     = get_option( 'vs_secrets_manager_vault_token' );
		$mount     = get_option( 'vs_secrets_manager_vault_mount', 'secret' );
		$namespace = get_option( 'vs_secrets_manager_vault_namespace', '' );

		if ( empty( $address ) || empty( $token ) ) {
			return null;
		}

		$url = untrailingslashit( $address ) . '/v1/' . untrailingslashit( $mount ) . '/data/' . $name;

		$headers = array(
			'X-Vault-Token' => $token,
		);

		if ( ! empty( $namespace ) ) {
			$headers['X-Vault-Namespace'] = $namespace;
		}

		$response = wp_remote_get( $url, array( 'headers' => $headers ) );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['data']['data'] ) ) {
			return null;
		}

		$data = $body['data']['data'];

		if ( isset( $data['value'] ) ) {
			return $data['value'];
		}

		if ( 1 === count( $data ) ) {
			return reset( $data );
		}

		return wp_json_encode( $data );
	}

	public function set( string $name, string $value, array $meta = array() ): bool {
		$address   = get_option( 'vs_secrets_manager_vault_address' );
		$token     = get_option( 'vs_secrets_manager_vault_token' );
		$mount     = get_option( 'vs_secrets_manager_vault_mount', 'secret' );
		$namespace = get_option( 'vs_secrets_manager_vault_namespace', '' );

		if ( empty( $address ) || empty( $token ) ) {
			return false;
		}

		$url = untrailingslashit( $address ) . '/v1/' . untrailingslashit( $mount ) . '/data/' . $name;

		$headers = array(
			'X-Vault-Token' => $token,
			'Content-Type'  => 'application/json',
		);

		if ( ! empty( $namespace ) ) {
			$headers['X-Vault-Namespace'] = $namespace;
		}

		$body = wp_json_encode( array(
			'data' => array( 'value' => $value ),
		) );

		$response = wp_remote_post( $url, array(
			'headers' => $headers,
			'body'    => $body,
			'method'  => 'PUT',
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			return false;
		}

		$this->sync_metadata( $name, $meta );

		return true;
	}

	public function delete( string $name ): bool {
		$address   = get_option( 'vs_secrets_manager_vault_address' );
		$token     = get_option( 'vs_secrets_manager_vault_token' );
		$mount     = get_option( 'vs_secrets_manager_vault_mount', 'secret' );
		$namespace = get_option( 'vs_secrets_manager_vault_namespace', '' );

		if ( empty( $address ) || empty( $token ) ) {
			return false;
		}

		$url = untrailingslashit( $address ) . '/v1/' . untrailingslashit( $mount ) . '/data/' . $name;

		$headers = array(
			'X-Vault-Token' => $token,
		);

		if ( ! empty( $namespace ) ) {
			$headers['X-Vault-Namespace'] = $namespace;
		}

		$response = wp_remote_request( $url, array(
			'headers' => $headers,
			'method'  => 'DELETE',
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		return $code >= 200 && $code < 300;
	}

	public function test_connection(): array {
		$address = get_option( 'vs_secrets_manager_vault_address' );
		$token   = get_option( 'vs_secrets_manager_vault_token' );

		if ( empty( $address ) || empty( $token ) ) {
			return array(
				'success' => false,
				'message' => __( 'Vault address or token not configured.', 'vs-secrets-manager' ),
			);
		}

		$url      = untrailingslashit( $address ) . '/v1/sys/health';
		$response = wp_remote_get( $url, array(
			'headers' => array( 'X-Vault-Token' => $token ),
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $code || 429 === $code ) {
			return array(
				'success' => true,
				'message' => __( 'Vault connection successful.', 'vs-secrets-manager' ),
			);
		}

		return array(
			'success' => false,
			'message' => sprintf(
				__( 'Vault returned HTTP %d.', 'vs-secrets-manager' ),
				$code
			),
		);
	}

	private function sync_metadata( string $name, array $meta ): void {
		global $wpdb;

		$title    = $meta['title'] ?? $name;
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}vsecrets_secrets WHERE name = %s",
			$name
		) );

		if ( $existing ) {
			$wpdb->update(
				$wpdb->prefix . 'vsecrets_secrets',
				array(
					'title'       => $title,
					'provider'    => 'vault',
					'value'       => $name,
					'status'      => $meta['status'] ?? 'active',
					'last_rotated' => current_time( 'mysql', true ),
					'updated_at'  => current_time( 'mysql', true ),
				),
				array( 'id' => $existing )
			);
		} else {
			$wpdb->insert(
				$wpdb->prefix . 'vsecrets_secrets',
				array(
					'name'        => $name,
					'title'       => $title,
					'provider'    => 'vault',
					'value'       => $name,
					'status'      => $meta['status'] ?? 'active',
					'last_rotated' => current_time( 'mysql', true ),
					'created_at'  => current_time( 'mysql', true ),
					'updated_at'  => current_time( 'mysql', true ),
				)
			);
		}
	}
}
