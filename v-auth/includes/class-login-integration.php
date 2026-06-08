<?php
/**
 * Adds SSO login buttons to wp-login.php, or — when configured — forces a
 * redirect straight to the configured identity provider.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class V_Auth_Login_Integration {

	public static function enqueue_styles(): void {
		wp_enqueue_style(
			'v-auth-login',
			V_AUTH_PLUGIN_URL . 'assets/login.css',
			array(),
			V_AUTH_VERSION
		);
	}

	public static function render_sso_buttons(): void {
		if ( 'button' !== get_option( 'v_auth_login_mode', 'button' ) ) {
			return;
		}

		$providers = V_Auth_Provider_Store::all();
		if ( empty( $providers ) ) {
			return;
		}

		echo '<div class="v-auth-sso-buttons">';
		foreach ( $providers as $id => $provider ) {
			$label = ! empty( $provider['button_label'] )
				? $provider['button_label']
				/* translators: %s: identity provider display name */
				: sprintf( __( 'Log in with %s', 'v-auth' ), $provider['display_name'] );

			printf(
				'<a class="button button-secondary v-auth-sso-button" href="%1$s">%2$s</a>',
				esc_url( rest_url( 'v-auth/v1/oidc/' . rawurlencode( (string) $id ) . '/authorize' ) ),
				esc_html( $label )
			);
		}
		echo '</div>';
	}

	/**
	 * When "force redirect" mode is enabled, send the browser straight to the
	 * single configured provider — except for logout and the explicit bypass,
	 * so admins can never lock themselves out of wp-login.
	 */
	public static function maybe_force_redirect(): void {
		if ( 'force_redirect' !== get_option( 'v_auth_login_mode', 'button' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['v_auth_bypass'], $_GET['action'] ) && 'logout' === $_GET['action'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['v_auth_bypass'] ) || isset( $_GET['action'] ) ) {
			return;
		}

		$providers = V_Auth_Provider_Store::all();
		if ( empty( $providers ) ) {
			return;
		}

		$provider_id = (string) array_key_first( $providers );

		wp_redirect( rest_url( 'v-auth/v1/oidc/' . rawurlencode( $provider_id ) . '/authorize' ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}
}
