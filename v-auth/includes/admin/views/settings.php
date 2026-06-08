<?php
/**
 * Global V-Auth settings: login mode and default role for auto-created users.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$login_mode   = get_option( 'v_auth_login_mode', 'button' );
$default_role = get_option( 'v_auth_default_role', 'subscriber' );
?>
<h2><?php esc_html_e( 'Login Settings', 'v-auth' ); ?></h2>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'v_auth_save_settings' ); ?>
	<input type="hidden" name="action" value="v_auth_save_settings" />

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Login mode', 'v-auth' ); ?></th>
			<td>
				<label>
					<input type="radio" name="v_auth_login_mode" value="button" <?php checked( $login_mode, 'button' ); ?> />
					<?php esc_html_e( 'Show SSO buttons alongside the normal WordPress login form', 'v-auth' ); ?>
				</label>
				<br />
				<label>
					<input type="radio" name="v_auth_login_mode" value="force_redirect" <?php checked( $login_mode, 'force_redirect' ); ?> />
					<?php esc_html_e( 'Redirect straight to the identity provider (use ?v_auth_bypass=1 to reach wp-login)', 'v-auth' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="v_auth_default_role"><?php esc_html_e( 'Default role for new users', 'v-auth' ); ?></label>
			</th>
			<td>
				<select name="v_auth_default_role" id="v_auth_default_role">
					<?php foreach ( get_editable_roles() as $role_key => $role_details ) : ?>
						<option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( $default_role, $role_key ); ?>>
							<?php echo esc_html( translate_user_role( $role_details['name'] ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Role assigned when V-Auth creates a WordPress account for a new external identity.', 'v-auth' ); ?></p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Settings', 'v-auth' ) ); ?>
</form>
<hr />
