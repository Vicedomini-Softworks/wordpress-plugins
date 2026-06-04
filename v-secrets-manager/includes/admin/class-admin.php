<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Secrets_Manager_Admin {

	public static function register_menu(): void {
		add_menu_page(
			__( 'VSecrets Manager', 'vs-secrets-manager' ),
			__( 'VSecrets Manager', 'vs-secrets-manager' ),
			'manage_options',
			'vs-secrets-manager',
			array( __CLASS__, 'render_secrets_list' ),
			'dashicons-lock',
			80
		);

		add_submenu_page(
			'vs-secrets-manager',
			__( 'Secrets', 'vs-secrets-manager' ),
			__( 'Secrets', 'vs-secrets-manager' ),
			'manage_options',
			'vs-secrets-manager'
		);

		add_submenu_page(
			'vs-secrets-manager',
			__( 'Add New Secret', 'vs-secrets-manager' ),
			__( 'Add New', 'vs-secrets-manager' ),
			'manage_options',
			'vs-secrets-manager-add',
			array( __CLASS__, 'render_secret_edit' )
		);

		add_submenu_page(
			'vs-secrets-manager',
			__( 'Settings', 'vs-secrets-manager' ),
			__( 'Settings', 'vs-secrets-manager' ),
			'manage_options',
			'vs-secrets-manager-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	public static function render_secrets_list(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'vs-secrets-manager' ) );
		}

		require_once VSECRETS_MANAGER_PLUGIN_DIR . 'includes/admin/views/secrets-list.php';
	}

	public static function render_secret_edit(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'vs-secrets-manager' ) );
		}

		require_once VSECRETS_MANAGER_PLUGIN_DIR . 'includes/admin/views/secret-edit.php';
	}

	public static function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'vs-secrets-manager' ) );
		}

		require_once VSECRETS_MANAGER_PLUGIN_DIR . 'includes/admin/views/settings.php';
	}

	public static function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'vs-secrets-manager' ) );
		}

		check_admin_referer( 'vs_secrets_manager_settings', '_vsnonce' );

		if ( isset( $_POST['aws_access_key'] ) ) {
			update_option( 'vs_secrets_manager_aws_access_key', sanitize_text_field( wp_unslash( $_POST['aws_access_key'] ) ) );
		}

		if ( isset( $_POST['aws_secret_key'] ) && ! empty( $_POST['aws_secret_key'] ) ) {
			update_option( 'vs_secrets_manager_aws_secret_key', sanitize_text_field( wp_unslash( $_POST['aws_secret_key'] ) ) );
		}

		if ( isset( $_POST['aws_region'] ) ) {
			update_option( 'vs_secrets_manager_aws_region', sanitize_text_field( wp_unslash( $_POST['aws_region'] ) ) );
		}

		if ( isset( $_POST['vault_address'] ) ) {
			update_option( 'vs_secrets_manager_vault_address', esc_url_raw( wp_unslash( $_POST['vault_address'] ) ) );
		}

		if ( isset( $_POST['vault_token'] ) && ! empty( $_POST['vault_token'] ) ) {
			update_option( 'vs_secrets_manager_vault_token', sanitize_text_field( wp_unslash( $_POST['vault_token'] ) ) );
		}

		if ( isset( $_POST['vault_mount'] ) ) {
			update_option( 'vs_secrets_manager_vault_mount', sanitize_key( wp_unslash( $_POST['vault_mount'] ) ) );
		}

		if ( isset( $_POST['vault_namespace'] ) ) {
			update_option( 'vs_secrets_manager_vault_namespace', sanitize_text_field( wp_unslash( $_POST['vault_namespace'] ) ) );
		}

		wp_safe_redirect( add_query_arg( 'updated', '1', wp_get_referer() ) );
		exit;
	}

	public static function ajax_reveal_secret(): void {
		check_ajax_referer( 'vs_secrets_manager_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1 );
		}

		$name = sanitize_key( $_POST['name'] ?? '' );

		if ( empty( $name ) ) {
			wp_die( -1 );
		}

		$value = VS_Secrets_Manager_Secret_Manager::get( $name );

		if ( null === $value ) {
			wp_send_json_error( array(
				'message' => __( 'Secret not found.', 'vs-secrets-manager' ),
			) );
		}

		wp_send_json_success( array( 'value' => $value ) );
	}
}
