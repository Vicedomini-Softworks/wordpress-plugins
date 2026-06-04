<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$secrets = VS_Secrets_Manager_Secret_Manager::get_secrets_list();
$aws_loaded = class_exists( 'Aws\SecretsManager\SecretsManagerClient' );
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Secrets', 'vs-secrets-manager' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=vs-secrets-manager-add' ) ); ?>" class="page-title-action">
			<?php echo esc_html__( 'Add New', 'vs-secrets-manager' ); ?>
		</a>
	</h1>

	<?php if ( ! $aws_loaded ) : ?>
	<div class="notice notice-info">
		<p><?php echo wp_kses_post( __( 'AWS SDK not detected. Install <code>aws/aws-sdk-php</code> via Composer to enable the AWS Secrets Manager provider.', 'vs-secrets-manager' ) ); ?></p>
	</div>
	<?php endif; ?>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Name', 'vs-secrets-manager' ); ?></th>
				<th><?php echo esc_html__( 'Title', 'vs-secrets-manager' ); ?></th>
				<th><?php echo esc_html__( 'Provider', 'vs-secrets-manager' ); ?></th>
				<th><?php echo esc_html__( 'Status', 'vs-secrets-manager' ); ?></th>
				<th><?php echo esc_html__( 'Last Rotated', 'vs-secrets-manager' ); ?></th>
				<th><?php echo esc_html__( 'Actions', 'vs-secrets-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $secrets ) ) : ?>
			<tr>
				<td colspan="6"><?php echo esc_html__( 'No secrets found.', 'vs-secrets-manager' ); ?></td>
			</tr>
			<?php else : ?>
				<?php foreach ( $secrets as $secret ) : ?>
				<tr>
					<td><code><?php echo esc_html( $secret->name ); ?></code></td>
					<td><?php echo esc_html( $secret->title ); ?></td>
					<td>
						<span class="vs-provider-badge vs-provider-<?php echo esc_attr( $secret->provider ); ?>">
							<?php echo esc_html( strtoupper( $secret->provider ) ); ?>
						</span>
					</td>
					<td><?php echo esc_html( ucfirst( $secret->status ) ); ?></td>
					<td><?php echo esc_html( $secret->last_rotated ?: '—' ); ?></td>
					<td>
						<button type="button" class="button vs-reveal-btn" data-name="<?php echo esc_attr( $secret->name ); ?>">
							<?php echo esc_html__( 'Reveal', 'vs-secrets-manager' ); ?>
						</button>
						<span class="vs-revealed-value" style="display:none; margin-left:8px;"></span>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
