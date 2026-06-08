<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class V_Auth_Admin {

	public static function register_menu(): void {
		add_options_page(
			__( 'V-Auth', 'v-auth' ),
			__( 'V-Auth', 'v-auth' ),
			'manage_options',
			'v-auth',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'v-auth' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'V-Auth', 'v-auth' ) . '</h1>';

		if ( isset( $_GET['v_auth_message'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'v-auth' ) . '</p></div>';
		}

		include V_AUTH_PLUGIN_DIR . 'includes/admin/views/settings.php';
		include V_AUTH_PLUGIN_DIR . 'includes/admin/views/providers.php';

		echo '</div>';
	}

	public static function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'v-auth' ) );
		}

		check_admin_referer( 'v_auth_save_settings' );

		$login_mode = isset( $_POST['v_auth_login_mode'] ) ? sanitize_key( $_POST['v_auth_login_mode'] ) : 'button';
		$allowed    = array( 'button', 'force_redirect' );
		update_option( 'v_auth_login_mode', in_array( $login_mode, $allowed, true ) ? $login_mode : 'button', false );

		$default_role = isset( $_POST['v_auth_default_role'] ) ? sanitize_key( $_POST['v_auth_default_role'] ) : 'subscriber';
		if ( ! array_key_exists( $default_role, get_editable_roles() ) ) {
			$default_role = 'subscriber';
		}
		update_option( 'v_auth_default_role', $default_role, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'v-auth',
					'v_auth_message' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	public static function handle_save_provider(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'v-auth' ) );
		}

		check_admin_referer( 'v_auth_save_provider' );

		$id = isset( $_POST['provider_id'] ) ? sanitize_key( wp_unslash( $_POST['provider_id'] ) ) : '';
		if ( empty( $id ) ) {
			$id = sanitize_key( wp_unslash( $_POST['display_name'] ?? '' ) ) . '-' . wp_generate_password( 6, false );
		}

		$data = array(
			'display_name'  => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
			'issuer'        => esc_url_raw( wp_unslash( $_POST['issuer'] ?? '' ) ),
			'client_id'     => sanitize_text_field( wp_unslash( $_POST['client_id'] ?? '' ) ),
			'client_secret' => sanitize_text_field( wp_unslash( $_POST['client_secret'] ?? '' ) ),
			'scopes'        => sanitize_text_field( wp_unslash( $_POST['scopes'] ?? 'openid email profile' ) ),
			'button_label'  => sanitize_text_field( wp_unslash( $_POST['button_label'] ?? '' ) ),
		);

		V_Auth_Provider_Store::save( $id, $data );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'v-auth',
					'v_auth_message' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	public static function handle_delete_provider(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'v-auth' ) );
		}

		check_admin_referer( 'v_auth_delete_provider' );

		$id = isset( $_POST['provider_id'] ) ? sanitize_key( wp_unslash( $_POST['provider_id'] ) ) : '';
		if ( ! empty( $id ) ) {
			V_Auth_Provider_Store::delete( $id );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'v-auth',
					'v_auth_message' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
}
