<?php
/**
 * Uninstall script for OpenProvider WooCommerce
 *
 * Removes all plugin data on uninstall.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}op_domains" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}op_domain_notifications" );

// Delete plugin options.
delete_option( 'opwc_settings' );
delete_option( 'opwc_db_version' );
delete_option( 'opwc_domain_product_id' );
delete_option( 'opwc_transfer_product_id' );
delete_option( 'opwc_op_username_encrypted' );
delete_option( 'opwc_op_password_encrypted' );

// Clear auth token transient.
delete_transient( 'opwc_auth_token' );

// Clear any scheduled hooks (none in v1, but included for forward-compat).
wp_clear_scheduled_hook( 'opwc_scheduled_task' );
wp_clear_scheduled_hook( 'opwc_check_expiring_domains' );
