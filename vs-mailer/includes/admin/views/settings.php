<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_mailer = get_option( 'vs_mailer_mailer', 'smtp' );

$smtp_host       = get_option( 'vs_mailer_smtp_host', '' );
$smtp_port       = get_option( 'vs_mailer_smtp_port', 587 );
$smtp_encryption = get_option( 'vs_mailer_smtp_encryption', 'tls' );
$smtp_auth       = get_option( 'vs_mailer_smtp_auth', 'yes' );
$smtp_username   = get_option( 'vs_mailer_smtp_username', '' );
$smtp_password   = VS_Mailer_Admin::get_masked_value( 'vs_mailer_smtp_password' );

$brevo_api_key = VS_Mailer_Admin::get_masked_value( 'vs_mailer_brevo_api_key' );
$brevo_domain  = get_option( 'vs_mailer_brevo_domain', '' );

$mailgun_api_key = VS_Mailer_Admin::get_masked_value( 'vs_mailer_mailgun_api_key' );
$mailgun_domain  = get_option( 'vs_mailer_mailgun_domain', '' );
$mailgun_region  = get_option( 'vs_mailer_mailgun_region', 'us' );

$log_emails = get_option( 'vs_mailer_log_emails', 'no' );
$from_name  = get_option( 'vs_mailer_from_name', get_bloginfo( 'name' ) );
$from_email = get_option( 'vs_mailer_from_email', get_bloginfo( 'admin_email' ) );
?>

<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="notice notice-success">
		<p><?php esc_html_e( 'Settings saved.', 'vs-mailer' ); ?></p>
	</div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="vs_mailer_save_settings">
	<?php wp_nonce_field( 'vs_mailer_save_settings' ); ?>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'From Name', 'vs-mailer' ); ?></th>
			<td>
				<input type="text" name="vs_mailer_from_name" value="<?php echo esc_attr( $from_name ); ?>" class="regular-text">
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'From Email', 'vs-mailer' ); ?></th>
			<td>
				<input type="email" name="vs_mailer_from_email" value="<?php echo esc_attr( $from_email ); ?>" class="regular-text">
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Mailer', 'vs-mailer' ); ?></th>
			<td>
				<select name="vs_mailer_mailer" id="vs-mailer-select">
					<option value="smtp" <?php selected( $current_mailer, 'smtp' ); ?>>SMTP</option>
					<option value="brevo" <?php selected( $current_mailer, 'brevo' ); ?>>Brevo (Sendinblue)</option>
					<option value="mailgun" <?php selected( $current_mailer, 'mailgun' ); ?>>Mailgun</option>
				</select>
			</td>
		</tr>
	</table>

	<div id="vs-mailer-smtp-settings" class="vs-mailer-section">
		<h2><?php esc_html_e( 'SMTP Settings', 'vs-mailer' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'SMTP Host', 'vs-mailer' ); ?></th>
				<td><input type="text" name="vs_mailer_smtp_host" value="<?php echo esc_attr( $smtp_host ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'SMTP Port', 'vs-mailer' ); ?></th>
				<td><input type="number" name="vs_mailer_smtp_port" value="<?php echo esc_attr( $smtp_port ); ?>" class="small-text"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Encryption', 'vs-mailer' ); ?></th>
				<td>
					<select name="vs_mailer_smtp_encryption">
						<option value="tls" <?php selected( $smtp_encryption, 'tls' ); ?>>TLS</option>
						<option value="ssl" <?php selected( $smtp_encryption, 'ssl' ); ?>>SSL</option>
						<option value="none" <?php selected( $smtp_encryption, 'none' ); ?>><?php esc_html_e( 'None', 'vs-mailer' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Authentication', 'vs-mailer' ); ?></th>
				<td>
					<select name="vs_mailer_smtp_auth">
						<option value="yes" <?php selected( $smtp_auth, 'yes' ); ?>><?php esc_html_e( 'Yes', 'vs-mailer' ); ?></option>
						<option value="no" <?php selected( $smtp_auth, 'no' ); ?>><?php esc_html_e( 'No', 'vs-mailer' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'SMTP Username', 'vs-mailer' ); ?></th>
				<td><input type="text" name="vs_mailer_smtp_username" value="<?php echo esc_attr( $smtp_username ); ?>" class="regular-text" autocomplete="off"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'SMTP Password', 'vs-mailer' ); ?></th>
				<td>
					<input type="password" name="vs_mailer_smtp_password" value="" class="regular-text" placeholder="<?php echo $smtp_password ? esc_attr( str_repeat( '•', 20 ) ) : ''; ?>" autocomplete="new-password">
					<p class="description"><?php esc_html_e( 'Leave blank to keep current password.', 'vs-mailer' ); ?></p>
				</td>
			</tr>
		</table>
	</div>

	<div id="vs-mailer-brevo-settings" class="vs-mailer-section">
		<h2><?php esc_html_e( 'Brevo Settings', 'vs-mailer' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'API Key', 'vs-mailer' ); ?></th>
				<td>
					<input type="password" name="vs_mailer_brevo_api_key" value="" class="regular-text" placeholder="<?php echo $brevo_api_key ? esc_attr( substr( $brevo_api_key, 0, 8 ) . '••••••••' ) : ''; ?>" autocomplete="new-password">
					<p class="description"><?php esc_html_e( 'Leave blank to keep current key.', 'vs-mailer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sender Domain', 'vs-mailer' ); ?></th>
				<td><input type="text" name="vs_mailer_brevo_domain" value="<?php echo esc_attr( $brevo_domain ); ?>" class="regular-text" placeholder="mail.example.com"></td>
			</tr>
		</table>
	</div>

	<div id="vs-mailer-mailgun-settings" class="vs-mailer-section">
		<h2><?php esc_html_e( 'Mailgun Settings', 'vs-mailer' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'API Key', 'vs-mailer' ); ?></th>
				<td>
					<input type="password" name="vs_mailer_mailgun_api_key" value="" class="regular-text" placeholder="<?php echo $mailgun_api_key ? esc_attr( substr( $mailgun_api_key, 0, 4 ) . '••••••••' ) : ''; ?>" autocomplete="new-password">
					<p class="description"><?php esc_html_e( 'Leave blank to keep current key.', 'vs-mailer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Domain', 'vs-mailer' ); ?></th>
				<td><input type="text" name="vs_mailer_mailgun_domain" value="<?php echo esc_attr( $mailgun_domain ); ?>" class="regular-text" placeholder="mg.example.com"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Region', 'vs-mailer' ); ?></th>
				<td>
					<select name="vs_mailer_mailgun_region">
						<option value="us" <?php selected( $mailgun_region, 'us' ); ?>><?php esc_html_e( 'US', 'vs-mailer' ); ?></option>
						<option value="eu" <?php selected( $mailgun_region, 'eu' ); ?>><?php esc_html_e( 'EU', 'vs-mailer' ); ?></option>
					</select>
				</td>
			</tr>
		</table>
	</div>

	<h2><?php esc_html_e( 'Logging', 'vs-mailer' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Log Emails', 'vs-mailer' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="vs_mailer_log_emails" value="yes" <?php checked( $log_emails, 'yes' ); ?>>
					<?php esc_html_e( 'Log all sent emails (last 100 entries)', 'vs-mailer' ); ?>
				</label>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Settings', 'vs-mailer' ) ); ?>
</form>

<script>
(function() {
	var select = document.getElementById('vs-mailer-select');
	var sections = {
		'smtp': document.getElementById('vs-mailer-smtp-settings'),
		'brevo': document.getElementById('vs-mailer-brevo-settings'),
		'mailgun': document.getElementById('vs-mailer-mailgun-settings'),
	};

	function toggleSections() {
		var val = select.value;
		for (var key in sections) {
			sections[key].style.display = (key === val) ? '' : 'none';
		}
	}

	select.addEventListener('change', toggleSections);
	toggleSections();
})();
</script>
