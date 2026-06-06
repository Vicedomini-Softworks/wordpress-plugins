<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Mailer_Admin {

	public static function register_menu(): void {
		add_options_page(
			__( 'VS Mailer', 'vs-mailer' ),
			__( 'VS Mailer', 'vs-mailer' ),
			'manage_options',
			'vs-mailer',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'vs-mailer' ) );
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'VS Mailer', 'vs-mailer' ) . '</h1>';

		echo '<h2 class="nav-tab-wrapper">';
		echo '<a href="?page=vs-mailer&tab=settings" class="nav-tab ' . ( 'settings' === $active_tab ? 'nav-tab-active' : '' ) . '">'
			. esc_html__( 'Settings', 'vs-mailer' ) . '</a>';
		echo '<a href="?page=vs-mailer&tab=test" class="nav-tab ' . ( 'test' === $active_tab ? 'nav-tab-active' : '' ) . '">'
			. esc_html__( 'Test Email', 'vs-mailer' ) . '</a>';
		echo '<a href="?page=vs-mailer&tab=log" class="nav-tab ' . ( 'log' === $active_tab ? 'nav-tab-active' : '' ) . '">'
			. esc_html__( 'Log', 'vs-mailer' ) . '</a>';
		echo '</h2>';

		if ( 'test' === $active_tab ) {
			include VS_MAILER_PLUGIN_DIR . 'includes/admin/views/test-email.php';
		} elseif ( 'log' === $active_tab ) {
			self::render_log();
		} else {
			include VS_MAILER_PLUGIN_DIR . 'includes/admin/views/settings.php';
		}

		echo '</div>';
	}

	public static function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'vs-mailer' ) );
		}

		check_admin_referer( 'vs_mailer_save_settings' );

		$mailer = isset( $_POST['vs_mailer_mailer'] )
			? sanitize_key( $_POST['vs_mailer_mailer'] )
			: 'smtp';

		$allowed_mailers = array( 'smtp', 'brevo', 'mailgun' );
		if ( ! in_array( $mailer, $allowed_mailers, true ) ) {
			$mailer = 'smtp';
		}

		update_option( 'vs_mailer_mailer', $mailer, false );

		$text_fields = array(
			'vs_mailer_from_name',
			'vs_mailer_from_email',
			'vs_mailer_smtp_host',
			'vs_mailer_smtp_username',
			'vs_mailer_brevo_domain',
			'vs_mailer_mailgun_domain',
			'vs_mailer_mailgun_region',
		);

		foreach ( $text_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_option( $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), false );
			}
		}

		if ( isset( $_POST['vs_mailer_smtp_port'] ) ) {
			update_option(
				'vs_mailer_smtp_port',
				absint( $_POST['vs_mailer_smtp_port'] ),
				false
			);
		}

		if ( isset( $_POST['vs_mailer_smtp_encryption'] ) ) {
			$enc         = sanitize_key( $_POST['vs_mailer_smtp_encryption'] );
			$allowed_enc = array( 'ssl', 'tls', 'none' );
			update_option(
				'vs_mailer_smtp_encryption',
				in_array( $enc, $allowed_enc, true ) ? $enc : 'tls',
				false
			);
		}

		if ( isset( $_POST['vs_mailer_smtp_auth'] ) ) {
			update_option(
				'vs_mailer_smtp_auth',
				'yes' === sanitize_key( $_POST['vs_mailer_smtp_auth'] ) ? 'yes' : 'no',
				false
			);
		}

		update_option(
			'vs_mailer_log_emails',
			isset( $_POST['vs_mailer_log_emails'] ) && 'yes' === sanitize_key( $_POST['vs_mailer_log_emails'] ) ? 'yes' : 'no',
			false
		);

		if ( isset( $_POST['vs_mailer_smtp_password'] ) && ! empty( $_POST['vs_mailer_smtp_password'] ) ) {
			VS_Secrets_Manager_Secret_Manager::set(
				'vs_mailer_smtp_password',
				sanitize_text_field( wp_unslash( $_POST['vs_mailer_smtp_password'] ) ),
				array(
					'title'    => 'VS Mailer SMTP Password',
					'provider' => 'db',
				)
			);
		}

		if ( isset( $_POST['vs_mailer_brevo_api_key'] ) && ! empty( $_POST['vs_mailer_brevo_api_key'] ) ) {
			VS_Secrets_Manager_Secret_Manager::set(
				'vs_mailer_brevo_api_key',
				sanitize_text_field( wp_unslash( $_POST['vs_mailer_brevo_api_key'] ) ),
				array(
					'title'    => 'VS Mailer Brevo API Key',
					'provider' => 'db',
				)
			);
		}

		if ( isset( $_POST['vs_mailer_mailgun_api_key'] ) && ! empty( $_POST['vs_mailer_mailgun_api_key'] ) ) {
			VS_Secrets_Manager_Secret_Manager::set(
				'vs_mailer_mailgun_api_key',
				sanitize_text_field( wp_unslash( $_POST['vs_mailer_mailgun_api_key'] ) ),
				array(
					'title'    => 'VS Mailer Mailgun API Key',
					'provider' => 'db',
				)
			);
		}

		$redirect = add_query_arg(
			array(
				'page'  => 'vs-mailer',
				'saved' => '1',
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	public static function handle_send_test(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'vs-mailer' ) );
		}

		check_admin_referer( 'vs_mailer_send_test' );

		$to_email = isset( $_POST['test_email'] )
			? sanitize_email( wp_unslash( $_POST['test_email'] ) )
			: '';

		if ( empty( $to_email ) ) {
			$redirect = add_query_arg(
				array(
					'page'    => 'vs-mailer',
					'tab'     => 'test',
					'result'  => '0',
					'message' => rawurlencode( __( 'Please enter a valid email address.', 'vs-mailer' ) ),
				),
				admin_url( 'options-general.php' )
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		$subject  = __( 'VS Mailer Test Email', 'vs-mailer' );
		$message  = '<h2>' . __( 'VS Mailer Test', 'vs-mailer' ) . '</h2>';
		$message .= '<p>' . sprintf(
			/* translators: %s: date/time string */
			__( 'This is a test email sent at %s.', 'vs-mailer' ),
			current_time( 'mysql' )
		) . '</p>';
		$message .= '<p>' . __( 'If you receive this, your mailer configuration is working correctly.', 'vs-mailer' ) . '</p>';

		$result = wp_mail( $to_email, $subject, $message, array( 'Content-Type: text/html' ) );

		if ( $result ) {
			/* translators: %s: recipient email address */
			$result_msg = rawurlencode( sprintf( __( 'Test email sent to %s.', 'vs-mailer' ), $to_email ) );
		} else {
			$result_msg = rawurlencode( __( 'Failed to send test email.', 'vs-mailer' ) );
		}

		$redirect = add_query_arg(
			array(
				'page'    => 'vs-mailer',
				'tab'     => 'test',
				'result'  => $result ? '1' : '0',
				'message' => $result_msg,
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	public static function handle_clear_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'vs-mailer' ) );
		}

		check_admin_referer( 'vs_mailer_clear_log' );

		VS_Mailer_Logger::clear();

		$redirect = add_query_arg(
			array(
				'page'    => 'vs-mailer',
				'tab'     => 'log',
				'cleared' => '1',
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	public static function get_masked_value( string $secret_name ): string {
		$value = vs_secrets_manager_get( $secret_name );
		if ( empty( $value ) ) {
			return '';
		}

		$len = strlen( $value );
		if ( $len <= 8 ) {
			return str_repeat( '•', $len );
		}

		return substr( $value, 0, 4 ) . str_repeat( '•', $len - 8 ) . substr( $value, -4 );
	}

	private static function render_log(): void {
		$log     = VS_Mailer_Logger::get_log();
		$enabled = VS_Mailer_Logger::is_enabled();
		?>
		<h2><?php esc_html_e( 'Email Log', 'vs-mailer' ); ?></h2>

		<?php if ( ! $enabled ) : ?>
			<div class="notice notice-info">
				<p><?php esc_html_e( 'Email logging is disabled. Enable it in Settings to start tracking sent emails.', 'vs-mailer' ); ?></p>
			</div>
		<?php elseif ( empty( $log ) ) : ?>
			<div class="notice notice-info">
				<p><?php esc_html_e( 'No emails logged yet.', 'vs-mailer' ); ?></p>
			</div>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:1em;">
				<input type="hidden" name="action" value="vs_mailer_clear_log">
				<?php wp_nonce_field( 'vs_mailer_clear_log' ); ?>
				<button type="submit" class="button button-delete"><?php esc_html_e( 'Clear Log', 'vs-mailer' ); ?></button>
			</form>

			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'vs-mailer' ); ?></th>
						<th><?php esc_html_e( 'To', 'vs-mailer' ); ?></th>
						<th><?php esc_html_e( 'Subject', 'vs-mailer' ); ?></th>
						<th><?php esc_html_e( 'Mailer', 'vs-mailer' ); ?></th>
						<th><?php esc_html_e( 'Status', 'vs-mailer' ); ?></th>
						<th><?php esc_html_e( 'Error', 'vs-mailer' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $log as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( $entry['time'] ?? '' ); ?></td>
							<td><?php echo esc_html( $entry['to'] ?? '' ); ?></td>
							<td><?php echo esc_html( $entry['subject'] ?? '' ); ?></td>
							<td><?php echo esc_html( $entry['mailer'] ?? '' ); ?></td>
							<td>
								<?php if ( ! empty( $entry['success'] ) ) : ?>
									<span style="color:#46b450;"><?php esc_html_e( 'Sent', 'vs-mailer' ); ?></span>
								<?php else : ?>
									<span style="color:#dc3232;"><?php esc_html_e( 'Failed', 'vs-mailer' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $entry['error_message'] ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}
}
