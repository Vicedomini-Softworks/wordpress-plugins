<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$edit_id  = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
$is_edit  = $edit_id > 0;
$secret   = null;
$name     = '';
$title    = '';
$provider = 'db';

if ( $is_edit ) {
	$secret = VS_Secrets_Manager_Secret_Manager::get_record_by_id( $edit_id );
	if ( $secret ) {
		$name     = $secret->name;
		$title    = $secret->title;
		$provider = $secret->provider;
	}
}
?>
<div class="wrap">
	<h1><?php echo $is_edit ? esc_html__( 'Edit Secret', 'vs-secrets-manager' ) : esc_html__( 'Add New Secret', 'vs-secrets-manager' ); ?></h1>

	<form id="vs-secret-form" method="post">
		<?php wp_nonce_field( 'vs_secrets_manager_save', '_vsnonce' ); ?>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="vs-secret-name"><?php echo esc_html__( 'Name (slug)', 'vs-secrets-manager' ); ?></label>
					</th>
					<td>
						<input type="text" id="vs-secret-name" name="name" class="regular-text"
							value="<?php echo esc_attr( $name ); ?>" <?php echo $is_edit ? 'readonly' : 'required'; ?>>
						<p class="description"><?php echo esc_html__( 'Unique identifier used to retrieve the secret via code.', 'vs-secrets-manager' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="vs-secret-title"><?php echo esc_html__( 'Title', 'vs-secrets-manager' ); ?></label>
					</th>
					<td>
						<input type="text" id="vs-secret-title" name="title" class="regular-text"
							value="<?php echo esc_attr( $title ); ?>">
						<p class="description"><?php echo esc_html__( 'Human-readable description.', 'vs-secrets-manager' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="vs-secret-provider"><?php echo esc_html__( 'Provider', 'vs-secrets-manager' ); ?></label>
					</th>
					<td>
						<select id="vs-secret-provider" name="provider" <?php echo $is_edit ? 'disabled' : ''; ?>>
							<option value="db" <?php selected( $provider, 'db' ); ?>><?php echo esc_html__( 'Database (Encrypted)', 'vs-secrets-manager' ); ?></option>
							<option value="aws" <?php selected( $provider, 'aws' ); ?>><?php echo esc_html__( 'AWS Secrets Manager', 'vs-secrets-manager' ); ?></option>
							<option value="vault" <?php selected( $provider, 'vault' ); ?>><?php echo esc_html__( 'Hashicorp Vault / OpenBao', 'vs-secrets-manager' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="vs-secret-value"><?php echo esc_html__( 'Secret Value', 'vs-secrets-manager' ); ?></label>
					</th>
					<td>
						<textarea id="vs-secret-value" name="value" rows="5" class="large-text code" required></textarea>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php echo $is_edit ? esc_html__( 'Update Secret', 'vs-secrets-manager' ) : esc_html__( 'Save Secret', 'vs-secrets-manager' ); ?>
			</button>
		</p>
	</form>
</div>
