<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class V_Auth {

	public static function init(): void {
		load_plugin_textdomain(
			'v-auth',
			false,
			dirname( plugin_basename( V_AUTH_PLUGIN_DIR ) ) . '/languages'
		);

		if ( ! function_exists( 'vs_secrets_manager_get' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_missing_dependency' ) );
			return;
		}

		self::load_dependencies();
		self::register_hooks();
	}

	public static function notice_missing_dependency(): void {
		echo '<div class="notice notice-error"><p>';
		echo '<strong>V-Auth</strong>: ';
		esc_html_e( 'VSecrets Manager plugin is required and must be active.', 'v-auth' );
		echo '</p></div>';
	}

	private static function load_dependencies(): void {
		require_once V_AUTH_PLUGIN_DIR . 'includes/class-provider-store.php';
		require_once V_AUTH_PLUGIN_DIR . 'includes/class-user-mapper.php';
		require_once V_AUTH_PLUGIN_DIR . 'includes/class-oidc-handler.php';
		require_once V_AUTH_PLUGIN_DIR . 'includes/class-login-integration.php';

		if ( is_admin() ) {
			require_once V_AUTH_PLUGIN_DIR . 'includes/admin/class-admin.php';
		}
	}

	private static function register_hooks(): void {
		V_Auth_OIDC_Handler::register_routes();

		add_action( 'login_enqueue_scripts', array( 'V_Auth_Login_Integration', 'enqueue_styles' ) );
		add_action( 'login_form', array( 'V_Auth_Login_Integration', 'render_sso_buttons' ) );
		add_action( 'login_init', array( 'V_Auth_Login_Integration', 'maybe_force_redirect' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( 'V_Auth_Admin', 'register_menu' ) );
			add_action( 'admin_post_v_auth_save_provider', array( 'V_Auth_Admin', 'handle_save_provider' ) );
			add_action( 'admin_post_v_auth_delete_provider', array( 'V_Auth_Admin', 'handle_delete_provider' ) );
			add_action( 'admin_post_v_auth_save_settings', array( 'V_Auth_Admin', 'handle_save_settings' ) );
		}
	}
}
