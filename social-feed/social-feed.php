<?php
/**
 * Plugin Name: Social Feed
 * Plugin URI: https://vicedominisoftworks.com/products/wp-social-feed/
 * Description: Display social media feeds from Instagram, Facebook, TikTok, X, Threads, Bluesky, and YouTube.
 * Version: 1.0.0
 * Author: Vicedomini Softworks
 * Author URI: https://vicedominisoftworks.com
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Text Domain: social-feed
 * Domain Path: /languages
 * Requires Plugins: v-secrets-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

defined( 'SOCIAL_FEED_PLUGIN_DIR' ) || define( 'SOCIAL_FEED_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'SOCIAL_FEED_PLUGIN_URL' ) || define( 'SOCIAL_FEED_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
defined( 'SOCIAL_FEED_VERSION' ) || define( 'SOCIAL_FEED_VERSION', '1.0.0' );

require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/class-activator.php';
require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once SOCIAL_FEED_PLUGIN_DIR . 'includes/class-social-feed.php';

register_activation_hook( __FILE__, array( 'Social_Feed_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Social_Feed_Deactivator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Social_Feed', 'init' ) );
