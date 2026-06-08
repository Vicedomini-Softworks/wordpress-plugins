<?php
/**
 * Maps verified external identities to WordPress users: finds an existing
 * user by email, auto-creates one when none exists, links the external
 * `sub` claim via usermeta, and logs the user in.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class V_Auth_User_Mapper {

	const IDENTITY_META_PREFIX = 'v_auth_identity_';

	/**
	 * Resolve a WP user from verified OIDC claims, creating one if needed,
	 * and establish the login session.
	 *
	 * @param array<string, mixed> $claims Verified ID token claims (must include `sub`; `email` used for matching).
	 * @return WP_User|WP_Error
	 */
	public static function login_from_claims( string $provider_id, array $claims ) {
		$sub   = isset( $claims['sub'] ) ? sanitize_text_field( (string) $claims['sub'] ) : '';
		$email = isset( $claims['email'] ) ? sanitize_email( (string) $claims['email'] ) : '';

		if ( empty( $sub ) || empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error(
				'v_auth_missing_claims',
				__( 'The identity provider did not return the required email and subject claims.', 'v-auth' )
			);
		}

		$user = self::find_by_identity( $provider_id, $sub );

		if ( ! $user ) {
			$user = get_user_by( 'email', $email );
		}

		if ( ! $user ) {
			$user = self::create_user( $email, $claims );
			if ( is_wp_error( $user ) ) {
				return $user;
			}
		}

		update_user_meta( $user->ID, self::IDENTITY_META_PREFIX . $provider_id, $sub );

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );

		return $user;
	}

	private static function find_by_identity( string $provider_id, string $sub ): ?WP_User {
		$users = get_users(
			array(
				'meta_key'   => self::IDENTITY_META_PREFIX . $provider_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $sub, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
			)
		);

		return $users[0] ?? null;
	}

	/**
	 * @param array<string, mixed> $claims
	 * @return WP_User|WP_Error
	 */
	private static function create_user( string $email, array $claims ) {
		$default_role = (string) get_option( 'v_auth_default_role', 'subscriber' );
		$display_name = isset( $claims['name'] ) ? sanitize_text_field( (string) $claims['name'] ) : $email;

		$username = sanitize_user( current( explode( '@', $email ) ), true );
		if ( username_exists( $username ) ) {
			$username .= '_' . wp_generate_password( 4, false );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 32 ),
				'display_name' => $display_name,
				'role'         => $default_role,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		return get_user_by( 'id', $user_id );
	}
}
