<?php
/**
 * Plugin Name: OpenProvider for WooCommerce
 * Plugin URI: https://vicedominisoftworks.com/products/openprovider-woocommerce/
 * Description: Domain search, pricing, and registration via OpenProvider, integrated into WooCommerce.
 * Version: 1.0.0
 * Author: Vicedomini Softworks
 * Author URI: https://vicedominisoftworks.com
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Text Domain: openprovider-woocommerce
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

defined( 'OPWC_PLUGIN_DIR' ) || define( 'OPWC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'OPWC_PLUGIN_URL' ) || define( 'OPWC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
defined( 'OPWC_PLUGIN_FILE' ) || define( 'OPWC_PLUGIN_FILE', __FILE__ );
defined( 'OPWC_VERSION' ) || define( 'OPWC_VERSION', '1.0.0' );
defined( 'OPWC_DB_VERSION' ) || define( 'OPWC_DB_VERSION', '1.0.0' );

// Load Composer autoloader if present
$opwc_autoload = OPWC_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $opwc_autoload ) ) {
	require_once $opwc_autoload;
}

// Load required files
require_once OPWC_PLUGIN_DIR . 'src/Activation/Activator.php';
require_once OPWC_PLUGIN_DIR . 'src/Activation/Deactivator.php';
require_once OPWC_PLUGIN_DIR . 'src/Plugin.php';

// Register hooks
register_activation_hook( __FILE__, array( 'OpenProviderWooCommerce\Activation\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'OpenProviderWooCommerce\Activation\Deactivator', 'deactivate' ) );

// Declare HPOS compatibility before WooCommerce initializes
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', OPWC_PLUGIN_FILE, true );
	}
} );

// Initialize plugin
add_action( 'plugins_loaded', array( 'OpenProviderWooCommerce\Plugin', 'init' ) );
