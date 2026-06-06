<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$result  = isset( $_GET['result'] ) ? sanitize_key( $_GET['result'] ) : '';
$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
?>

<h2><?php esc_html_e( 'Send a Test Email', 'vs-mailer' ); ?></h2>

<?php if ( '1' === $result ) : ?>
	<div class="notice notice-success">
		<p><?php echo esc_html( $message ); ?></p>
	</div>
<?php elseif ( '0' === $result ) : ?>
	<div class="notice notice-error">
		<p><?php echo esc_html( $message ); ?></p>
	</div>
<?php endif; ?>

<p><?php esc_html_e( 'Enter an email address to receive a test message using your current mailer configuration.', 'vs-mailer' ); ?></p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="vs_mailer_send_test">
	<?php wp_nonce_field( 'vs_mailer_send_test' ); ?>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Send To', 'vs-mailer' ); ?></th>
			<td>
				<input type="email" name="test_email" value="" class="regular-text">
				<p class="description"><?php esc_html_e( 'The email address that will receive the test.', 'vs-mailer' ); ?></p>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Send Test Email', 'vs-mailer' ); ?></button>
	</p>
</form>
