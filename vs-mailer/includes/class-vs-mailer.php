<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Mailer {

	public static function init(): void {
		load_plugin_textdomain(
			'vs-mailer',
			false,
			dirname( plugin_basename( VS_MAILER_PLUGIN_DIR ) ) . '/languages'
		);

		if ( ! function_exists( 'vs_secrets_manager_get' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_missing_dependency' ) );
			return;
		}

		self::load_dependencies();
		self::register_hooks();
	}

	public static function notice_missing_dependency(): void {
		echo '<div class="notice notice-error"><p>';
		echo '<strong>VS Mailer</strong>: ';
		esc_html_e( 'VSecrets Manager plugin is required and must be active.', 'vs-mailer' );
		echo '</p></div>';
	}

	private static function load_dependencies(): void {
		require_once VS_MAILER_PLUGIN_DIR . 'includes/class-activator.php';
		require_once VS_MAILER_PLUGIN_DIR . 'includes/class-deactivator.php';
		require_once VS_MAILER_PLUGIN_DIR . 'includes/class-logger.php';

		require_once VS_MAILER_PLUGIN_DIR . 'includes/providers/abstract-class-mail-provider.php';
		require_once VS_MAILER_PLUGIN_DIR . 'includes/providers/class-smtp-provider.php';
		require_once VS_MAILER_PLUGIN_DIR . 'includes/providers/class-brevo-provider.php';
		require_once VS_MAILER_PLUGIN_DIR . 'includes/providers/class-mailgun-provider.php';

		if ( is_admin() ) {
			require_once VS_MAILER_PLUGIN_DIR . 'includes/admin/class-admin.php';
		}
	}

	private static function register_hooks(): void {
		add_filter( 'wp_mail_from', array( __CLASS__, 'filter_from_email' ) );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'filter_from_name' ) );

		add_action( 'phpmailer_init', array( __CLASS__, 'configure_phpmailer' ) );
		add_filter( 'pre_wp_mail', array( __CLASS__, 'handle_pre_wp_mail' ), 10, 2 );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( 'VS_Mailer_Admin', 'register_menu' ) );
			add_action( 'admin_post_vs_mailer_save_settings', array( 'VS_Mailer_Admin', 'handle_save_settings' ) );
			add_action( 'admin_post_vs_mailer_send_test', array( 'VS_Mailer_Admin', 'handle_send_test' ) );
			add_action( 'admin_post_vs_mailer_clear_log', array( 'VS_Mailer_Admin', 'handle_clear_log' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		}
	}

	public static function filter_from_email( string $email ): string {
		$configured = get_option( 'vs_mailer_from_email', '' );
		return ! empty( $configured ) ? $configured : $email;
	}

	public static function filter_from_name( string $name ): string {
		$configured = get_option( 'vs_mailer_from_name', '' );
		return ! empty( $configured ) ? $configured : $name;
	}

	public static function configure_phpmailer( PHPMailer\PHPMailer\PHPMailer $phpmailer ): void {
		$mailer = get_option( 'vs_mailer_mailer', 'smtp' );
		if ( 'smtp' !== $mailer ) {
			return;
		}

		VS_Mailer_SMTP_Provider::configure( $phpmailer );
	}

	public static function handle_pre_wp_mail( $null, array $atts ) {
		$mailer = get_option( 'vs_mailer_mailer', 'smtp' );

		if ( 'brevo' === $mailer ) {
			$result = VS_Mailer_Brevo_Provider::send(
				$atts['to'],
				$atts['subject'],
				$atts['message'],
				$atts['headers'],
				$atts['attachments']
			);

			VS_Mailer_Logger::log(
				is_array( $atts['to'] ) ? implode( ', ', $atts['to'] ) : $atts['to'],
				$atts['subject'],
				'brevo',
				$result,
				$result ? '' : __( 'Brevo send failed', 'vs-mailer' )
			);

			return $result;
		}

		if ( 'mailgun' === $mailer ) {
			$result = VS_Mailer_Mailgun_Provider::send(
				$atts['to'],
				$atts['subject'],
				$atts['message'],
				$atts['headers'],
				$atts['attachments']
			);

			VS_Mailer_Logger::log(
				is_array( $atts['to'] ) ? implode( ', ', $atts['to'] ) : $atts['to'],
				$atts['subject'],
				'mailgun',
				$result,
				$result ? '' : __( 'Mailgun send failed', 'vs-mailer' )
			);

			return $result;
		}

		return $null;
	}

	public static function enqueue_admin_assets( string $hook ): void {
		if ( false === strpos( $hook, 'vs-mailer' ) ) {
			return;
		}

		wp_enqueue_style(
			'vs-mailer-admin',
			VS_MAILER_PLUGIN_URL . 'assets/admin.css',
			array(),
			VS_MAILER_VERSION
		);
	}
}
