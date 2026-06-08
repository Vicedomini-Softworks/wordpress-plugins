<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$providers = get_option( 'v_auth_providers', array() );

if ( function_exists( 'vs_secrets_manager_get' ) && class_exists( 'VS_Secrets_Manager_Secret_Manager' ) && is_array( $providers ) ) {
	foreach ( array_keys( $providers ) as $provider_id ) {
		VS_Secrets_Manager_Secret_Manager::delete( 'v_auth_' . $provider_id . '_client_secret' );
	}
}

$options = array(
	'v_auth_providers',
	'v_auth_login_mode',
	'v_auth_default_role',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

global $wpdb;
// Direct queries are required here: uninstall cleanup has no caching concerns and no WP API covers bulk meta/option deletes by key prefix.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like( 'v_auth_identity_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_v_auth_' ) . '%', $wpdb->esc_like( '_transient_timeout_v_auth_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
