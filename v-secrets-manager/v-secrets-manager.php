<?php
/**
 * Plugin Name: VSecrets Manager
 * Plugin URI: https://vicedominisoftworks.com/products/vs-secrets-manager/
 * Description: Manage secrets with multiple providers: encrypted database, AWS Secrets Manager, and Hashicorp Vault/OpenBao.
 * Version: 1.0.0
 * Author: Vicedomini Softworks
 * Author URI: https://vicedominisoftworks.com
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.9
 * Requires PHP: 7.2.24
 * Text Domain: vs-secrets-manager
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

defined( 'VSECRETS_MANAGER_PLUGIN_DIR' ) || define( 'VSECRETS_MANAGER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'VSECRETS_MANAGER_PLUGIN_URL' ) || define( 'VSECRETS_MANAGER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
defined( 'VSECRETS_MANAGER_VERSION' ) || define( 'VSECRETS_MANAGER_VERSION', '1.0.0' );
defined( 'VSECRETS_MANAGER_DB_VERSION' ) || define( 'VSECRETS_MANAGER_DB_VERSION', '1.0.0' );

$vs_aws_autoload = VSECRETS_MANAGER_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $vs_aws_autoload ) ) {
	require_once $vs_aws_autoload;
}

require_once VSECRETS_MANAGER_PLUGIN_DIR . 'includes/class-activator.php';
require_once VSECRETS_MANAGER_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once VSECRETS_MANAGER_PLUGIN_DIR . 'includes/class-v-secrets-manager.php';

register_activation_hook( __FILE__, array( 'VS_Secrets_Manager_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'VS_Secrets_Manager_Deactivator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'VS_Secrets_Manager', 'init' ) );

function vs_secrets_manager_get( string $name ): ?string {
	return VS_Secrets_Manager_Secret_Manager::get( $name );
}
