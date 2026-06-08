<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class V_Auth_Activator {

	public static function activate(): void {
		$defaults = array(
			'v_auth_providers'    => array(),
			'v_auth_login_mode'   => 'button',
			'v_auth_default_role' => 'subscriber',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value, '', false );
			}
		}
	}
}
