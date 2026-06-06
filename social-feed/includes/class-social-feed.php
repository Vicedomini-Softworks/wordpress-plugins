<?php
/**
 * Main plugin class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Feed {

	public static function init(): void {
		if ( ! function_exists( 'vs_secrets_manager_get' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_missing_dependency' ) );
			return;
		}

		self::load_dependencies();
		self::register_hooks();
	}

	public static function notice_missing_dependency(): void {
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong>: %s</p></div>',
			esc_html__( 'Social Feed', 'social-feed' ),
			esc_html__( 'VSecrets Manager plugin is required and must be active.', 'social-feed' )
		);
	}

	private static function load_dependencies(): void {
		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/class-activator.php';
		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/class-deactivator.php';
		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/class-cache-manager.php';
		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/class-media-downloader.php';
		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/class-feed-repository.php';
		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/class-oauth-handler.php';
		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/class-shortcode.php';
		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/class-public.php';

		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/providers/abstract-class-provider.php';
		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/providers/class-instagram-provider.php';
		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/providers/class-facebook-provider.php';
		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/providers/class-tiktok-provider.php';
		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/providers/class-x-provider.php';
		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/providers/class-threads-provider.php';
		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/providers/class-bluesky-provider.php';
		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/providers/class-youtube-provider.php';

		if ( is_admin() ) {
			require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/admin/class-admin.php';
		}
	}

	private static function register_hooks(): void {
		// Text domain
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );

		// Shortcode
		add_action( 'init', array( 'Social_Feed_Shortcode', 'register' ) );

		// Assets
		add_action( 'wp_enqueue_scripts', array( 'Social_Feed_Public', 'register_assets' ) );

		// OAuth routes
		Social_Feed_OAuth_Handler::register_routes();

		if ( is_admin() ) {
			// Admin menu
			add_action( 'admin_menu', array( 'Social_Feed_Admin', 'register_menu' ) );

			// Handle form submissions via admin-post
			add_action( 'admin_post_social_feed_save_feed', array( 'Social_Feed_Admin', 'handle_submission' ) );
			add_action( 'admin_post_social_feed_delete_feed', array( 'Social_Feed_Admin', 'handle_submission' ) );
			add_action( 'admin_post_social_feed_save_platform', array( __CLASS__, 'handle_platform_save' ) );
			add_action( 'admin_post_social_feed_reset_cache', array( 'Social_Feed_Admin', 'handle_submission' ) );

			// Admin styles
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		}
	}

	/**
	 * Save platform credentials
	 */
	public static function handle_platform_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied', 'social-feed' ) );
		}

		check_admin_referer( 'social_feed_save_platform' );

		$platform = isset( $_POST['platform'] ) ? sanitize_key( $_POST['platform'] ) : '';
		if ( empty( $platform ) ) {
			wp_redirect( admin_url( 'admin.php?page=social-feed-platform-settings' ) );
			exit;
		}

		// Store sensitive credentials in VS Secrets Manager
		if ( isset( $_POST['client_id'] ) && ! empty( $_POST['client_id'] ) ) {
			VS_Secrets_Manager_Secret_Manager::set(
				'social_feed_' . $platform . '_client_id',
				sanitize_text_field( wp_unslash( $_POST['client_id'] ) ),
				array(
					'title'    => 'Social Feed ' . $platform . ' client_id',
					'provider' => 'db',
				)
			);
		}

		if ( isset( $_POST['client_secret'] ) && ! empty( $_POST['client_secret'] ) ) {
			VS_Secrets_Manager_Secret_Manager::set(
				'social_feed_' . $platform . '_client_secret',
				sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ),
				array(
					'title'    => 'Social Feed ' . $platform . ' client_secret',
					'provider' => 'db',
				)
			);
		}

		// Non-sensitive metadata stays in wp_options
		$option_name = 'social_feed_meta_' . $platform;
		$current     = get_option( $option_name, array() );

		$meta = array(
			'token_expiry'   => $current['token_expiry'] ?? 0,
			'connected_at'   => $current['connected_at'] ?? 0,
			'account_id'     => $current['account_id'] ?? '',
			'cache_reset_at' => $current['cache_reset_at'] ?? 0,
		);

		update_option( $option_name, $meta, false );

		wp_redirect( admin_url( 'admin.php?page=social-feed-platform-settings&platform=' . $platform . '&saved=1' ) );
		exit;
	}

	public static function load_textdomain(): void {
		load_plugin_textdomain(
			'social-feed',
			false,
			dirname( plugin_basename( SOCIAL_FEED_PLUGIN_DIR . 'social-feed.php' ) ) . '/languages'
		);
	}

	/**
	 * Enqueue admin assets
	 */
	public static function enqueue_admin_assets( string $hook ): void {
		if ( false === strpos( $hook, 'social-feed' ) ) {
			return;
		}

		wp_enqueue_style(
			'social-feed-admin',
			SOCIAL_FEED_PLUGIN_URL . 'assets/admin.css',
			array(),
			SOCIAL_FEED_VERSION
		);
	}
}
