<?php
/**
 * Plugin Name: VS Mailer
 * Plugin URI: https://vicedominisoftworks.com/products/wp-vs-mailer/
 * Description: Complete SMTP, Brevo, and Mailgun email delivery plugin. Stores credentials securely via VSecrets Manager.
 * Version: 1.0.0
 * Author: Vicedomini Softworks
 * Author URI: https://vicedominisoftworks.com
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.9
 * Requires PHP: 7.2.24
 * Text Domain: vs-mailer
 * Domain Path: /languages
 * Requires Plugins: v-secrets-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

defined( 'VS_MAILER_PLUGIN_DIR' ) || define( 'VS_MAILER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'VS_MAILER_PLUGIN_URL' ) || define( 'VS_MAILER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
defined( 'VS_MAILER_VERSION' ) || define( 'VS_MAILER_VERSION', '1.0.0' );

require_once VS_MAILER_PLUGIN_DIR . 'includes/class-activator.php';
require_once VS_MAILER_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once VS_MAILER_PLUGIN_DIR . 'includes/class-vs-mailer.php';

register_activation_hook( __FILE__, array( 'VS_Mailer_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'VS_Mailer_Deactivator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'VS_Mailer', 'init' ) );
