<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = array(
	'vs_mailer_mailer',
	'vs_mailer_from_name',
	'vs_mailer_from_email',
	'vs_mailer_smtp_host',
	'vs_mailer_smtp_port',
	'vs_mailer_smtp_encryption',
	'vs_mailer_smtp_auth',
	'vs_mailer_smtp_username',
	'vs_mailer_brevo_domain',
	'vs_mailer_mailgun_domain',
	'vs_mailer_mailgun_region',
	'vs_mailer_log_emails',
	'vs_mailer_log',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

if ( function_exists( 'vs_secrets_manager_get' ) && class_exists( 'VS_Secrets_Manager_Secret_Manager' ) ) {
	$secrets = array(
		'vs_mailer_smtp_password',
		'vs_mailer_brevo_api_key',
		'vs_mailer_mailgun_api_key',
	);

	foreach ( $secrets as $secret ) {
		VS_Secrets_Manager_Secret_Manager::delete( $secret );
	}
}
