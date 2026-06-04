<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Secrets_Manager {

	public static function init(): void {
		self::load_dependencies();
		self::register_hooks();
	}

	private static function load_dependencies(): void {
		require_once VSECRETS_MANAGER_PLUGIN_DIR . 'includes/class-activator.php';
		require_once VSECRETS_MANAGER_PLUGIN_DIR . 'includes/class-deactivator.php';
		require_once VSECRETS_MANAGER_PLUGIN_DIR . 'includes/class-encryption.php';
		require_once VSECRETS_MANAGER_PLUGIN_DIR . 'includes/providers/abstract-class-provider.php';
		require_once VSECRETS_MANAGER_PLUGIN_DIR . 'includes/providers/class-db-provider.php';
		require_once VSECRETS_MANAGER_PLUGIN_DIR . 'includes/providers/class-aws-provider.php';
		require_once VSECRETS_MANAGER_PLUGIN_DIR . 'includes/providers/class-vault-provider.php';
		require_once VSECRETS_MANAGER_PLUGIN_DIR . 'includes/class-secret-manager.php';
		require_once VSECRETS_MANAGER_PLUGIN_DIR . 'includes/class-api.php';
		require_once VSECRETS_MANAGER_PLUGIN_DIR . 'includes/class-cron.php';

		if ( is_admin() ) {
			require_once VSECRETS_MANAGER_PLUGIN_DIR . 'includes/admin/class-admin.php';
		}
	}

	private static function register_hooks(): void {
		add_action( 'rest_api_init', array( 'VS_Secrets_Manager_API', 'register_routes' ) );
		add_action( 'vs_secrets_manager_rotate_secrets', array( 'VS_Secrets_Manager_Cron', 'rotate_secrets' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( 'VS_Secrets_Manager_Admin', 'register_menu' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
			add_action( 'wp_ajax_vs_secrets_manager_reveal', array( 'VS_Secrets_Manager_Admin', 'ajax_reveal_secret' ) );
			add_action( 'admin_post_vs_secrets_manager_save_settings', array( 'VS_Secrets_Manager_Admin', 'handle_save_settings' ) );
		}
	}

	public static function enqueue_admin_assets( string $hook ): void {
		if ( strpos( $hook, 'vs-secrets-manager' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'vs-secrets-manager-admin',
			VSECRETS_MANAGER_PLUGIN_URL . 'assets/admin.css',
			array(),
			VSECRETS_MANAGER_VERSION
		);

		wp_enqueue_script(
			'vs-secrets-manager-admin',
			VSECRETS_MANAGER_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			VSECRETS_MANAGER_VERSION,
			true
		);

		wp_localize_script( 'vs-secrets-manager-admin', 'vsSecretsManager', array(
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'ajaxNonce' => wp_create_nonce( 'vs_secrets_manager_ajax' ),
			'restUrl' => rest_url( 'vs-secrets-manager/v1' ),
		) );
	}
}
