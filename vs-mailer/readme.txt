=== VS Mailer ===
Contributors: vicedominisoftworks
Tags: smtp, brevo, mailgun, email, wp-mail, phpmailer
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.2.24
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Complete SMTP, Brevo (Sendinblue), and Mailgun email delivery plugin. Credentials stored securely via VSecrets Manager.

== Description ==

VS Mailer replaces WordPress default `wp_mail()` with your choice of SMTP, Brevo API, or Mailgun API. All sensitive credentials (passwords, API keys) are stored securely using the VSecrets Manager plugin.

= Features =

* Three mailer modes: SMTP, Brevo API, Mailgun API
* SMTP with TLS/SSL, authentication, custom port
* Brevo transactional email API v3
* Mailgun Messages API with US/EU region support
* Secure credential storage via VSecrets Manager (AES-256-CBC)
* Test email tool
* Optional email logging
* From name and email configuration
* Full header support (CC, BCC, Reply-To, Content-Type)

= Requirements =

This plugin requires the VSecrets Manager plugin to be installed and active.

== Installation ==

1. Ensure VSecrets Manager is installed and active.
2. Upload `vs-mailer` to `/wp-content/plugins/`.
3. Activate the plugin from the Plugins screen.
4. Go to Settings > VS Mailer and configure your mailer.

== Frequently Asked Questions ==

= Why do I need VSecrets Manager? =

All sensitive credentials (SMTP passwords, Brevo API keys, Mailgun API keys) are stored via VSecrets Manager using AES-256-CBC encryption instead of plain text in wp_options.

= Can I use VS Mailer with other SMTP plugins? =

No. VS Mailer replaces the `wp_mail()` implementation entirely. Deactivate other SMTP/mailer plugins before using.

== Changelog ==

= 1.0.0 =
* Initial release.
