<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Secrets_Manager_AWS_Provider extends VS_Secrets_Manager_Provider {

	private $client = null;

	private function get_client(): ?Aws\SecretsManager\SecretsManagerClient {
		if ( null !== $this->client ) {
			return $this->client;
		}

		if ( ! class_exists( 'Aws\SecretsManager\SecretsManagerClient' ) ) {
			return null;
		}

		$access_key = get_option( 'vs_secrets_manager_aws_access_key' );
		$secret_key = get_option( 'vs_secrets_manager_aws_secret_key' );
		$region     = get_option( 'vs_secrets_manager_aws_region', 'us-east-1' );

		if ( empty( $access_key ) || empty( $secret_key ) ) {
			return null;
		}

		try {
			$this->client = new Aws\SecretsManager\SecretsManagerClient(
				array(
					'version'     => '2017-10-17',
					'region'      => $region,
					'credentials' => array(
						'key'    => $access_key,
						'secret' => $secret_key,
					),
				)
			);
		} catch ( Exception $e ) {
			return null;
		}

		return $this->client;
	}

	public function get( string $name ): ?string {
		$client = $this->get_client();

		if ( null === $client ) {
			return null;
		}

		try {
			$result = $client->getSecretValue( array( 'SecretId' => $name ) );

			if ( isset( $result['SecretString'] ) ) {
				return $result['SecretString'];
			}

			if ( isset( $result['SecretBinary'] ) ) {
				return base64_decode( $result['SecretBinary'] );
			}
		} catch ( Exception $e ) {
			return null;
		}

		return null;
	}

	public function set( string $name, string $value, array $meta = array() ): bool {
		$client = $this->get_client();

		if ( null === $client ) {
			return false;
		}

		try {
			$client->createSecret(
				array(
					'Name'         => $name,
					'SecretString' => $value,
					'Description'  => $meta['title'] ?? '',
				)
			);
		} catch ( Aws\Exception\AwsException $e ) {
			if ( 'ResourceExistsException' === $e->getAwsErrorCode() ) {
				try {
					$client->putSecretValue(
						array(
							'SecretId'     => $name,
							'SecretString' => $value,
						)
					);
				} catch ( Exception $put_e ) {
					return false;
				}
			} else {
				return false;
			}
		}

		$this->sync_metadata( $name, $meta );

		return true;
	}

	public function delete( string $name ): bool {
		$client = $this->get_client();

		if ( null === $client ) {
			return false;
		}

		try {
			$client->deleteSecret(
				array(
					'SecretId'                   => $name,
					'ForceDeleteWithoutRecovery' => true,
				)
			);
		} catch ( Exception $e ) {
			return false;
		}

		return true;
	}

	public function test_connection(): array {
		$client = $this->get_client();

		if ( null === $client ) {
			return array(
				'success' => false,
				'message' => __( 'AWS SDK not available or credentials not configured.', 'vs-secrets-manager' ),
			);
		}

		try {
			$client->listSecrets( array( 'MaxResults' => 1 ) );

			return array(
				'success' => true,
				'message' => __( 'AWS Secrets Manager connection successful.', 'vs-secrets-manager' ),
			);
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	private function sync_metadata( string $name, array $meta ): void {
		global $wpdb;

		$title    = $meta['title'] ?? $name;
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}vsecrets_secrets WHERE name = %s",
				$name
			)
		);

		if ( $existing ) {
			$wpdb->update(
				$wpdb->prefix . 'vsecrets_secrets',
				array(
					'title'        => $title,
					'provider'     => 'aws',
					'value'        => $name,
					'status'       => $meta['status'] ?? 'active',
					'last_rotated' => current_time( 'mysql', true ),
					'updated_at'   => current_time( 'mysql', true ),
				),
				array( 'id' => $existing )
			);
		} else {
			$wpdb->insert(
				$wpdb->prefix . 'vsecrets_secrets',
				array(
					'name'         => $name,
					'title'        => $title,
					'provider'     => 'aws',
					'value'        => $name,
					'status'       => $meta['status'] ?? 'active',
					'last_rotated' => current_time( 'mysql', true ),
					'created_at'   => current_time( 'mysql', true ),
					'updated_at'   => current_time( 'mysql', true ),
				)
			);
		}
	}
}
