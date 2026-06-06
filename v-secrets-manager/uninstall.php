<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'vsecrets_secrets';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name derived from $wpdb->prefix, not user input
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

delete_option( 'vs_secrets_manager_db_version' );
delete_option( 'vs_secrets_manager_aws_access_key' );
delete_option( 'vs_secrets_manager_aws_secret_key' );
delete_option( 'vs_secrets_manager_aws_region' );
delete_option( 'vs_secrets_manager_vault_address' );
delete_option( 'vs_secrets_manager_vault_token' );
delete_option( 'vs_secrets_manager_vault_mount' );
delete_option( 'vs_secrets_manager_vault_namespace' );

wp_clear_scheduled_hook( 'vs_secrets_manager_rotate_secrets' );
