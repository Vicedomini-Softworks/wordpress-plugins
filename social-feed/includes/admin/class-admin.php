<?php
/**
 * Admin functionality
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Feed_Admin {

	/**
	 * Register admin menu
	 */
	public static function register_menu(): void {
		add_menu_page(
			__( 'Social Feed', 'social-feed' ),
			__( 'Social Feed', 'social-feed' ),
			'manage_options',
			'social-feed',
			array( __CLASS__, 'page_feeds' ),
			'dashicons-images-alt2',
			30
		);

		add_submenu_page(
			'social-feed',
			__( 'Feeds', 'social-feed' ),
			__( 'Feeds', 'social-feed' ),
			'manage_options',
			'social-feed',
			array( __CLASS__, 'page_feeds' )
		);

		add_submenu_page(
			'social-feed',
			__( 'Add New Feed', 'social-feed' ),
			__( 'Add New Feed', 'social-feed' ),
			'manage_options',
			'social-feed-add',
			array( __CLASS__, 'page_add_feed' )
		);

		add_submenu_page(
			'social-feed',
			__( 'Platform Settings', 'social-feed' ),
			__( 'Platform Settings', 'social-feed' ),
			'manage_options',
			'social-feed-platform-settings',
			array( __CLASS__, 'page_platform_settings' )
		);
	}

	/**
	 * Feeds list page
	 */
	public static function page_feeds(): void {
		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/admin/views/feeds-list.php';
	}

	/**
	 * Add/edit feed page
	 */
	public static function page_add_feed(): void {
		$slug = isset( $_GET['edit'] ) ? sanitize_key( $_GET['edit'] ) : '';
		$feed = $slug ? Social_Feed_Feed_Repository::get( $slug ) : null;

		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/admin/views/feed-edit.php';
	}

	/**
	 * Platform settings page
	 */
	public static function page_platform_settings(): void {
		$platform = isset( $_GET['platform'] ) ? sanitize_key( $_GET['platform'] ) : '';
		$connected = isset( $_GET['connected'] );

		require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/admin/views/platform-settings.php';
	}

	/**
	 * Handle form submissions
	 */
	public static function handle_submission(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Save feed
		if ( isset( $_POST['social_feed_save_feed'] ) ) {
			check_admin_referer( 'social_feed_save_feed' );

			$data = array(
				'slug'        => isset( $_POST['feed_slug'] ) ? sanitize_key( $_POST['feed_slug'] ) : '',
				'platform'    => isset( $_POST['platform'] ) ? sanitize_key( $_POST['platform'] ) : '',
				'mode'        => isset( $_POST['mode'] ) ? sanitize_key( $_POST['mode'] ) : 'embed',
				'account'     => isset( $_POST['account'] ) ? sanitize_text_field( $_POST['account'] ) : '',
				'cache_hours' => isset( $_POST['cache_hours'] ) ? intval( $_POST['cache_hours'] ) : 8,
				'display'     => array(
					'type'           => isset( $_POST['display_type'] ) ? sanitize_key( $_POST['display_type'] ) : 'grid',
					'limit'          => isset( $_POST['display_limit'] ) ? intval( $_POST['display_limit'] ) : 8,
					'theme'          => isset( $_POST['display_theme'] ) ? sanitize_key( $_POST['display_theme'] ) : 'light',
					'show_media'     => isset( $_POST['show_media'] ),
					'show_caption'   => isset( $_POST['show_caption'] ),
					'show_username'  => isset( $_POST['show_username'] ),
					'show_timestamp' => isset( $_POST['show_timestamp'] ),
					'link_posts'     => isset( $_POST['link_posts'] ),
				),
			);

			$errors = Social_Feed_Feed_Repository::validate( $data );
			if ( empty( $errors ) ) {
				Social_Feed_Feed_Repository::save( $data );
				wp_redirect( admin_url( 'admin.php?page=social-feed&saved=1' ) );
				exit;
			} else {
				add_action( 'admin_notices', function() use ( $errors ) {
					foreach ( $errors as $error ) {
						echo '<div class="error"><p>' . esc_html( $error ) . '</p></div>';
					}
				} );
			}
		}

		// Delete feed
		if ( isset( $_POST['social_feed_delete_feed'] ) ) {
			check_admin_referer( 'social_feed_delete_feed' );

			$slug = isset( $_POST['feed_slug'] ) ? sanitize_key( $_POST['feed_slug'] ) : '';
			if ( $slug ) {
				Social_Feed_Feed_Repository::delete( $slug );
				Social_Feed_Cache_Manager::delete( $slug );
			}

			wp_redirect( admin_url( 'admin.php?page=social-feed&deleted=1' ) );
			exit;
		}

		// Reset platform cache
		if ( isset( $_POST['social_feed_reset_cache'] ) ) {
			check_admin_referer( 'social_feed_reset_cache' );

			$platform = isset( $_POST['platform'] ) ? sanitize_key( $_POST['platform'] ) : '';
			if ( $platform ) {
				Social_Feed_Cache_Manager::clear_platform( $platform );
			}

			wp_redirect( admin_url( 'admin.php?page=social-feed-platform-settings&platform=' . $platform . '&reset=1' ) );
			exit;
		}
	}

}
