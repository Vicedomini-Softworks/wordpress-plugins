<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Mailer_Activator {

	public static function activate(): void {
		$defaults = array(
			'vs_mailer_mailer'          => 'smtp',
			'vs_mailer_from_name'       => get_bloginfo( 'name' ),
			'vs_mailer_from_email'      => get_bloginfo( 'admin_email' ),
			'vs_mailer_smtp_host'       => '',
			'vs_mailer_smtp_port'       => 587,
			'vs_mailer_smtp_encryption' => 'tls',
			'vs_mailer_smtp_auth'       => 'yes',
			'vs_mailer_smtp_username'   => '',
			'vs_mailer_brevo_domain'    => '',
			'vs_mailer_mailgun_domain'  => '',
			'vs_mailer_mailgun_region'  => 'us',
			'vs_mailer_log_emails'      => 'no',
			'vs_mailer_log'             => array(),
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value, '', false );
			}
		}
	}
}
