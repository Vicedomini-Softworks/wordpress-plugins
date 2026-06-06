<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Secrets_Manager_API {

	private const NAMESPACE = 'vs-secrets-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/secrets',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_secrets' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/secrets/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_secret' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/secrets',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_secret' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => self::get_secret_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/secrets/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_secret' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/secrets/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_secret' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/test-connection',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'test_connection' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'provider' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return in_array( $param, array( 'db', 'aws', 'vault' ), true );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'save_settings' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_settings' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
	}

	public static function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public static function get_secrets(): WP_REST_Response {
		$secrets = VS_Secrets_Manager_Secret_Manager::get_secrets_list();
		$data    = array();

		foreach ( $secrets as $secret ) {
			$data[] = array(
				'id'           => (int) $secret->id,
				'name'         => $secret->name,
				'title'        => $secret->title,
				'provider'     => $secret->provider,
				'status'       => $secret->status,
				'last_rotated' => $secret->last_rotated,
				'created_at'   => $secret->created_at,
				'updated_at'   => $secret->updated_at,
			);
		}

		return new WP_REST_Response( $data, 200 );
	}

	public static function get_secret( WP_REST_Request $request ): WP_REST_Response {
		$id  = (int) $request->get_param( 'id' );
		$row = VS_Secrets_Manager_Secret_Manager::get_record_by_id( $id );

		if ( ! $row ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Secret not found.', 'vs-secrets-manager' ),
				),
				404
			);
		}

		$value = VS_Secrets_Manager_Secret_Manager::get( $row->name );

		return new WP_REST_Response(
			array(
				'id'                => (int) $row->id,
				'name'              => $row->name,
				'title'             => $row->title,
				'value'             => $value,
				'provider'          => $row->provider,
				'encryption_method' => $row->encryption_method,
				'status'            => $row->status,
				'last_rotated'      => $row->last_rotated,
				'created_at'        => $row->created_at,
				'updated_at'        => $row->updated_at,
			),
			200
		);
	}

	public static function create_secret( WP_REST_Request $request ): WP_REST_Response {
		$name     = sanitize_key( $request->get_param( 'name' ) );
		$value    = $request->get_param( 'value' );
		$title    = sanitize_text_field( $request->get_param( 'title' ) ?? '' );
		$provider = sanitize_key( $request->get_param( 'provider' ) ?? 'db' );

		if ( empty( $name ) || null === $value ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Name and value are required.', 'vs-secrets-manager' ),
				),
				400
			);
		}

		$success = VS_Secrets_Manager_Secret_Manager::set(
			$name,
			$value,
			array(
				'title'    => $title,
				'provider' => $provider,
			)
		);

		if ( ! $success ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Failed to create secret.', 'vs-secrets-manager' ),
				),
				500
			);
		}

		$record = VS_Secrets_Manager_Secret_Manager::get_record( $name );

		return new WP_REST_Response(
			array(
				'id'       => (int) $record->id,
				'name'     => $record->name,
				'title'    => $record->title,
				'provider' => $record->provider,
				'message'  => __( 'Secret created.', 'vs-secrets-manager' ),
			),
			201
		);
	}

	public static function update_secret( WP_REST_Request $request ): WP_REST_Response {
		$id  = (int) $request->get_param( 'id' );
		$row = VS_Secrets_Manager_Secret_Manager::get_record_by_id( $id );

		if ( ! $row ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Secret not found.', 'vs-secrets-manager' ),
				),
				404
			);
		}

		$value = $request->get_param( 'value' );
		$title = sanitize_text_field( $request->get_param( 'title' ) ?? $row->title );

		$meta = array( 'title' => $title );

		if ( null !== $value ) {
			$success = VS_Secrets_Manager_Secret_Manager::set( $row->name, $value, $meta );
		} else {
			global $wpdb;
			$success = (bool) $wpdb->update(
				$wpdb->prefix . 'vsecrets_secrets',
				array(
					'title'      => $title,
					'updated_at' => current_time( 'mysql', true ),
				),
				array( 'id' => $id )
			);
		}

		if ( ! $success ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Failed to update secret.', 'vs-secrets-manager' ),
				),
				500
			);
		}

		return new WP_REST_Response(
			array(
				'message' => __( 'Secret updated.', 'vs-secrets-manager' ),
			),
			200
		);
	}

	public static function delete_secret( WP_REST_Request $request ): WP_REST_Response {
		$id  = (int) $request->get_param( 'id' );
		$row = VS_Secrets_Manager_Secret_Manager::get_record_by_id( $id );

		if ( ! $row ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Secret not found.', 'vs-secrets-manager' ),
				),
				404
			);
		}

		$success = VS_Secrets_Manager_Secret_Manager::delete( $row->name );

		if ( ! $success ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Failed to delete secret.', 'vs-secrets-manager' ),
				),
				500
			);
		}

		return new WP_REST_Response(
			array(
				'message' => __( 'Secret deleted.', 'vs-secrets-manager' ),
			),
			200
		);
	}

	public static function test_connection( WP_REST_Request $request ): WP_REST_Response {
		$provider = $request->get_param( 'provider' );
		$result   = VS_Secrets_Manager_Secret_Manager::test_connection( $provider );

		return new WP_REST_Response( $result, $result['success'] ? 200 : 400 );
	}

	public static function save_settings( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();

		if ( isset( $params['aws_access_key'] ) ) {
			update_option( 'vs_secrets_manager_aws_access_key', sanitize_text_field( $params['aws_access_key'] ) );
		}

		if ( isset( $params['aws_secret_key'] ) ) {
			update_option( 'vs_secrets_manager_aws_secret_key', sanitize_text_field( $params['aws_secret_key'] ) );
		}

		if ( isset( $params['aws_region'] ) ) {
			update_option( 'vs_secrets_manager_aws_region', sanitize_text_field( $params['aws_region'] ) );
		}

		if ( isset( $params['vault_address'] ) ) {
			update_option( 'vs_secrets_manager_vault_address', esc_url_raw( $params['vault_address'] ) );
		}

		if ( isset( $params['vault_token'] ) ) {
			update_option( 'vs_secrets_manager_vault_token', sanitize_text_field( $params['vault_token'] ) );
		}

		if ( isset( $params['vault_mount'] ) ) {
			update_option( 'vs_secrets_manager_vault_mount', sanitize_key( $params['vault_mount'] ) );
		}

		if ( isset( $params['vault_namespace'] ) ) {
			update_option( 'vs_secrets_manager_vault_namespace', sanitize_text_field( $params['vault_namespace'] ) );
		}

		return new WP_REST_Response(
			array(
				'message' => __( 'Settings saved.', 'vs-secrets-manager' ),
			),
			200
		);
	}

	public static function get_settings(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'aws_access_key'  => get_option( 'vs_secrets_manager_aws_access_key', '' ),
				'aws_secret_key'  => defined( 'VSECRETS_MANAGER_REDACT_KEYS' ) && VSECRETS_MANAGER_REDACT_KEYS ? '••••••••' : '',
				'aws_region'      => get_option( 'vs_secrets_manager_aws_region', 'us-east-1' ),
				'vault_address'   => get_option( 'vs_secrets_manager_vault_address', '' ),
				'vault_token'     => defined( 'VSECRETS_MANAGER_REDACT_KEYS' ) && VSECRETS_MANAGER_REDACT_KEYS ? '••••••••' : '',
				'vault_mount'     => get_option( 'vs_secrets_manager_vault_mount', 'secret' ),
				'vault_namespace' => get_option( 'vs_secrets_manager_vault_namespace', '' ),
				'aws_sdk_loaded'  => class_exists( 'Aws\SecretsManager\SecretsManagerClient' ),
			),
			200
		);
	}

	private static function get_secret_args(): array {
		return array(
			'name'     => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_key',
			),
			'value'    => array(
				'required' => true,
			),
			'title'    => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
			'provider' => array(
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => function ( $param ) {
					return in_array( $param, array( 'db', 'aws', 'vault' ), true );
				},
				'default'           => 'db',
			),
		);
	}
}
