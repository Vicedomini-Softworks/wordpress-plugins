<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aws_loaded      = class_exists( 'Aws\SecretsManager\SecretsManagerClient' );
$aws_access_key  = get_option( 'vs_secrets_manager_aws_access_key', '' );
$aws_region      = get_option( 'vs_secrets_manager_aws_region', 'us-east-1' );
$vault_address   = get_option( 'vs_secrets_manager_vault_address', '' );
$vault_mount     = get_option( 'vs_secrets_manager_vault_mount', 'secret' );
$vault_namespace = get_option( 'vs_secrets_manager_vault_namespace', '' );

$updated = isset( $_GET['updated'] ) && '1' === $_GET['updated']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'VSecrets Manager Settings', 'vs-secrets-manager' ); ?></h1>

	<?php if ( $updated ) : ?>
	<div class="notice notice-success"><p><?php echo esc_html__( 'Settings saved.', 'vs-secrets-manager' ); ?></p></div>
	<?php endif; ?>

	<form id="vs-settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="vs_secrets_manager_save_settings">
		<?php wp_nonce_field( 'vs_secrets_manager_settings', '_vsnonce' ); ?>

		<h2><?php echo esc_html__( 'AWS Secrets Manager', 'vs-secrets-manager' ); ?></h2>

		<?php if ( ! $aws_loaded ) : ?>
		<div class="notice notice-warning inline">
			<p><?php echo wp_kses_post( __( 'AWS SDK not installed. Run <code>composer require aws/aws-sdk-php</code> in the plugin directory.', 'vs-secrets-manager' ) ); ?></p>
		</div>
		<?php endif; ?>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="vs-aws-access-key"><?php echo esc_html__( 'Access Key ID', 'vs-secrets-manager' ); ?></label>
					</th>
					<td>
						<input type="text" id="vs-aws-access-key" name="aws_access_key" class="regular-text"
							value="<?php echo esc_attr( $aws_access_key ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="vs-aws-secret-key"><?php echo esc_html__( 'Secret Access Key', 'vs-secrets-manager' ); ?></label>
					</th>
					<td>
						<input type="password" id="vs-aws-secret-key" name="aws_secret_key" class="regular-text">
						<p class="description"><?php echo esc_html__( 'Leave blank to keep the current value.', 'vs-secrets-manager' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="vs-aws-region"><?php echo esc_html__( 'Region', 'vs-secrets-manager' ); ?></label>
					</th>
					<td>
						<input type="text" id="vs-aws-region" name="aws_region" class="regular-text"
							value="<?php echo esc_attr( $aws_region ); ?>">
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php echo esc_html__( 'Hashicorp Vault / OpenBao', 'vs-secrets-manager' ); ?></h2>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="vs-vault-address"><?php echo esc_html__( 'Vault Address', 'vs-secrets-manager' ); ?></label>
					</th>
					<td>
						<input type="url" id="vs-vault-address" name="vault_address" class="regular-text code"
							value="<?php echo esc_attr( $vault_address ); ?>"
							placeholder="https://vault.example.com:8200">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="vs-vault-token"><?php echo esc_html__( 'Token', 'vs-secrets-manager' ); ?></label>
					</th>
					<td>
						<input type="password" id="vs-vault-token" name="vault_token" class="regular-text">
						<p class="description"><?php echo esc_html__( 'Leave blank to keep the current value.', 'vs-secrets-manager' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="vs-vault-mount"><?php echo esc_html__( 'Secret Mount Path', 'vs-secrets-manager' ); ?></label>
					</th>
					<td>
						<input type="text" id="vs-vault-mount" name="vault_mount" class="regular-text"
							value="<?php echo esc_attr( $vault_mount ); ?>">
						<p class="description"><?php echo esc_html__( 'Default: secret', 'vs-secrets-manager' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="vs-vault-namespace"><?php echo esc_html__( 'Namespace', 'vs-secrets-manager' ); ?></label>
					</th>
					<td>
						<input type="text" id="vs-vault-namespace" name="vault_namespace" class="regular-text"
							value="<?php echo esc_attr( $vault_namespace ); ?>">
						<p class="description"><?php echo esc_html__( 'Optional. For Vault Enterprise or OpenBao multi-tenancy.', 'vs-secrets-manager' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php echo esc_html__( 'Save Settings', 'vs-secrets-manager' ); ?>
			</button>
		</p>
	</form>
</div>
