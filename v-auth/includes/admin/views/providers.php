<?php
/**
 * Identity provider list and add/edit form.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$providers = V_Auth_Provider_Store::all();
?>
<h2><?php esc_html_e( 'Identity Providers', 'v-auth' ); ?></h2>

<?php if ( ! empty( $providers ) ) : ?>
<table class="widefat striped">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Name', 'v-auth' ); ?></th>
			<th><?php esc_html_e( 'Issuer', 'v-auth' ); ?></th>
			<th><?php esc_html_e( 'Client ID', 'v-auth' ); ?></th>
			<th><?php esc_html_e( 'Redirect URI', 'v-auth' ); ?></th>
			<th></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ( $providers as $provider_id => $provider ) : ?>
		<tr>
			<td><?php echo esc_html( $provider['display_name'] ); ?></td>
			<td><?php echo esc_html( $provider['issuer'] ); ?></td>
			<td><?php echo esc_html( $provider['client_id'] ); ?></td>
			<td><code><?php echo esc_html( rest_url( 'v-auth/v1/oidc/' . $provider_id . '/callback' ) ); ?></code></td>
			<td>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this identity provider?', 'v-auth' ) ); ?>');">
					<?php wp_nonce_field( 'v_auth_delete_provider' ); ?>
					<input type="hidden" name="action" value="v_auth_delete_provider" />
					<input type="hidden" name="provider_id" value="<?php echo esc_attr( $provider_id ); ?>" />
					<button type="submit" class="button-link-delete"><?php esc_html_e( 'Remove', 'v-auth' ); ?></button>
				</form>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>

<h3><?php esc_html_e( 'Add Identity Provider', 'v-auth' ); ?></h3>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'v_auth_save_provider' ); ?>
	<input type="hidden" name="action" value="v_auth_save_provider" />

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="display_name"><?php esc_html_e( 'Display name', 'v-auth' ); ?></label></th>
			<td><input type="text" class="regular-text" id="display_name" name="display_name" required /></td>
		</tr>
		<tr>
			<th scope="row"><label for="issuer"><?php esc_html_e( 'Issuer URL', 'v-auth' ); ?></label></th>
			<td>
				<input type="url" class="regular-text" id="issuer" name="issuer" placeholder="https://idp.example.com" required />
				<p class="description"><?php esc_html_e( 'V-Auth fetches /.well-known/openid-configuration from this URL.', 'v-auth' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="client_id"><?php esc_html_e( 'Client ID', 'v-auth' ); ?></label></th>
			<td><input type="text" class="regular-text" id="client_id" name="client_id" required /></td>
		</tr>
		<tr>
			<th scope="row"><label for="client_secret"><?php esc_html_e( 'Client secret', 'v-auth' ); ?></label></th>
			<td>
				<input type="password" class="regular-text" id="client_secret" name="client_secret" autocomplete="new-password" />
				<p class="description"><?php esc_html_e( 'Stored securely via V-Secrets Manager. Leave blank to keep the existing secret when editing.', 'v-auth' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="scopes"><?php esc_html_e( 'Scopes', 'v-auth' ); ?></label></th>
			<td><input type="text" class="regular-text" id="scopes" name="scopes" value="openid email profile" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="button_label"><?php esc_html_e( 'Login button label', 'v-auth' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" id="button_label" name="button_label" placeholder="<?php esc_attr_e( 'Log in with…', 'v-auth' ); ?>" />
				<p class="description"><?php esc_html_e( 'Leave blank to use “Log in with {Provider name}”.', 'v-auth' ); ?></p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Add Provider', 'v-auth' ) ); ?>
</form>
