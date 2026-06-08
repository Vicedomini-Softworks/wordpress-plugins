<?php
/**
 * Plugin Name: V-Auth
 * Plugin URI: https://vicedominisoftworks.com/products/wp-v-auth/
 * Description: OpenID Connect single sign-on for WordPress. Lets site admins configure login with external identity providers; SAML support planned.
 * Version: 1.0.0
 * Author: Vicedomini Softworks
 * Author URI: https://vicedominisoftworks.com
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Text Domain: v-auth
 * Domain Path: /languages
 * Requires Plugins: v-secrets-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

defined( 'V_AUTH_PLUGIN_DIR' ) || define( 'V_AUTH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'V_AUTH_PLUGIN_URL' ) || define( 'V_AUTH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
defined( 'V_AUTH_VERSION' ) || define( 'V_AUTH_VERSION', '1.0.0' );

require_once V_AUTH_PLUGIN_DIR . 'includes/class-activator.php';
require_once V_AUTH_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once V_AUTH_PLUGIN_DIR . 'includes/class-v-auth.php';

register_activation_hook( __FILE__, array( 'V_Auth_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'V_Auth_Deactivator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'V_Auth', 'init' ) );
