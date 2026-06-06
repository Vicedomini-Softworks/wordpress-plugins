import { test, expect } from '@playwright/test';

const ADMIN_URL = '/wp-admin/options-general.php?page=vs-mailer';

async function login( page: any ): Promise<void> {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForLoadState( 'networkidle' );
}

test.describe( 'Admin UI — page rendering', () => {

	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'settings page loads with title and tabs', async ( { page } ) => {
		await page.goto( ADMIN_URL );
		await expect( page.locator( 'h1' ) ).toContainText( 'VS Mailer' );

		const tabs = page.locator( '.nav-tab' );
		await expect( tabs ).toHaveCount( 3 );
		await expect( tabs.nth( 0 ) ).toContainText( 'Settings' );
		await expect( tabs.nth( 1 ) ).toContainText( 'Test Email' );
		await expect( tabs.nth( 2 ) ).toContainText( 'Log' );
	} );

	test( 'settings tab is active by default', async ( { page } ) => {
		await page.goto( ADMIN_URL );
		await expect( page.locator( '.nav-tab-active' ) ).toContainText( 'Settings' );
	} );

	test( 'all common form fields are present', async ( { page } ) => {
		await page.goto( ADMIN_URL );

		await expect( page.locator( 'input[name="vs_mailer_from_name"]' ) ).toBeVisible();
		await expect( page.locator( 'input[name="vs_mailer_from_email"]' ) ).toBeVisible();
		await expect( page.locator( 'select[name="vs_mailer_mailer"]' ) ).toBeVisible();
		await expect( page.locator( 'input[name="vs_mailer_log_emails"]' ) ).toBeVisible();
	} );

	test( 'test email tab renders form', async ( { page } ) => {
		await page.goto( ADMIN_URL + '&tab=test' );
		await expect( page.locator( '.nav-tab-active' ) ).toContainText( 'Test Email' );
		await expect( page.locator( 'h2:not(.nav-tab-wrapper)' ) ).toContainText( 'Send a Test Email' );
		await expect( page.locator( 'input[name="test_email"]' ) ).toBeVisible();
		await expect( page.locator( 'button:has-text("Send Test Email")' ) ).toBeVisible();
	} );

	test( 'log tab renders empty state', async ( { page } ) => {
		await page.goto( ADMIN_URL + '&tab=log' );
		await expect( page.locator( '.nav-tab-active' ) ).toContainText( 'Log' );
		await expect( page.locator( 'h2:not(.nav-tab-wrapper)' ) ).toContainText( 'Email Log' );
	} );

	test( 'default mailer is SMTP with visible section', async ( { page } ) => {
		await page.goto( ADMIN_URL );

		await expect( page.locator( 'select[name="vs_mailer_mailer"]' ) ).toHaveValue( 'smtp' );
		await expect( page.locator( '#vs-mailer-smtp-settings' ) ).toBeVisible();
		await expect( page.locator( '#vs-mailer-smtp-settings h2' ) ).toContainText( 'SMTP Settings' );
	} );
} );

test.describe( 'Admin UI — mailer section toggling', () => {

	test.beforeEach( async ( { page } ) => {
		await login( page );
		await page.goto( ADMIN_URL );
	} );

	test( 'switching to Brevo hides SMTP and shows Brevo section', async ( { page } ) => {
		await page.selectOption( 'select[name="vs_mailer_mailer"]', 'brevo' );
		await expect( page.locator( '#vs-mailer-smtp-settings' ) ).not.toBeVisible();
		await expect( page.locator( '#vs-mailer-brevo-settings' ) ).toBeVisible();
		await expect( page.locator( '#vs-mailer-brevo-settings h2' ) ).toContainText( 'Brevo Settings' );
	} );

	test( 'switching to Mailgun shows Mailgun section', async ( { page } ) => {
		await page.selectOption( 'select[name="vs_mailer_mailer"]', 'mailgun' );
		await expect( page.locator( '#vs-mailer-smtp-settings' ) ).not.toBeVisible();
		await expect( page.locator( '#vs-mailer-brevo-settings' ) ).not.toBeVisible();
		await expect( page.locator( '#vs-mailer-mailgun-settings' ) ).toBeVisible();
		await expect( page.locator( '#vs-mailer-mailgun-settings h2' ) ).toContainText( 'Mailgun Settings' );
	} );

	test( 'switching back to SMTP shows SMTP section again', async ( { page } ) => {
		await page.selectOption( 'select[name="vs_mailer_mailer"]', 'mailgun' );
		await expect( page.locator( '#vs-mailer-smtp-settings' ) ).not.toBeVisible();

		await page.selectOption( 'select[name="vs_mailer_mailer"]', 'smtp' );
		await expect( page.locator( '#vs-mailer-smtp-settings' ) ).toBeVisible();
	} );
} );

test.describe( 'Admin UI — saving settings', () => {

	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'saves SMTP settings and shows success notice', async ( { page } ) => {
		await page.goto( ADMIN_URL );

		await page.fill( 'input[name="vs_mailer_from_name"]', 'Test Sender' );
		await page.fill( 'input[name="vs_mailer_from_email"]', 'test@example.com' );
		await page.fill( 'input[name="vs_mailer_smtp_host"]', 'smtp.example.com' );
		await page.fill( 'input[name="vs_mailer_smtp_port"]', '587' );
		await page.selectOption( 'select[name="vs_mailer_smtp_encryption"]', 'tls' );
		await page.fill( 'input[name="vs_mailer_smtp_username"]', 'user@example.com' );
		await page.fill( 'input[name="vs_mailer_smtp_password"]', 'secret123' );

		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		await expect( page.locator( '.notice-success' ) ).toBeVisible();
	} );

	test( 'SMTP settings persist after reload', async ( { page } ) => {
		await page.goto( ADMIN_URL );

		await page.fill( 'input[name="vs_mailer_from_name"]', 'Persistent Sender' );
		await page.fill( 'input[name="vs_mailer_smtp_host"]', 'mail.persistent.com' );
		await page.fill( 'input[name="vs_mailer_smtp_port"]', '465' );
		await page.selectOption( 'select[name="vs_mailer_smtp_encryption"]', 'ssl' );

		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( ADMIN_URL );
		await page.waitForLoadState( 'networkidle' );

		await expect( page.locator( 'input[name="vs_mailer_from_name"]' ) ).toHaveValue( 'Persistent Sender' );
		await expect( page.locator( 'input[name="vs_mailer_smtp_host"]' ) ).toHaveValue( 'mail.persistent.com' );
		await expect( page.locator( 'input[name="vs_mailer_smtp_port"]' ) ).toHaveValue( '465' );
		await expect( page.locator( 'select[name="vs_mailer_smtp_encryption"]' ) ).toHaveValue( 'ssl' );
	} );

	test( 'saves Brevo settings', async ( { page } ) => {
		await page.goto( ADMIN_URL );

		await page.selectOption( 'select[name="vs_mailer_mailer"]', 'brevo' );
		await page.fill( 'input[name="vs_mailer_from_name"]', 'Brevo Sender' );
		await page.fill( 'input[name="vs_mailer_brevo_api_key"]', 'brevo_test_key_123' );
		await page.fill( 'input[name="vs_mailer_brevo_domain"]', 'mail.mysite.com' );

		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		await expect( page.locator( '.notice-success' ) ).toBeVisible();
	} );

	test( 'Brevo mailer selection persists', async ( { page } ) => {
		await page.goto( ADMIN_URL );

		await page.selectOption( 'select[name="vs_mailer_mailer"]', 'brevo' );
		await page.fill( 'input[name="vs_mailer_brevo_domain"]', 'brevo.example.com' );
		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( ADMIN_URL );
		await expect( page.locator( 'select[name="vs_mailer_mailer"]' ) ).toHaveValue( 'brevo' );
		await expect( page.locator( 'input[name="vs_mailer_brevo_domain"]' ) ).toHaveValue( 'brevo.example.com' );
	} );

	test( 'saves Mailgun settings', async ( { page } ) => {
		await page.goto( ADMIN_URL );

		await page.selectOption( 'select[name="vs_mailer_mailer"]', 'mailgun' );
		await page.fill( 'input[name="vs_mailer_mailgun_api_key"]', 'mg_key_test_456' );
		await page.fill( 'input[name="vs_mailer_mailgun_domain"]', 'mg.example.com' );
		await page.selectOption( 'select[name="vs_mailer_mailgun_region"]', 'eu' );

		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		await expect( page.locator( '.notice-success' ) ).toBeVisible();
	} );

	test( 'Mailgun region persists', async ( { page } ) => {
		await page.goto( ADMIN_URL );

		await page.selectOption( 'select[name="vs_mailer_mailer"]', 'mailgun' );
		await page.selectOption( 'select[name="vs_mailer_mailgun_region"]', 'eu' );
		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( ADMIN_URL );
		await expect( page.locator( 'select[name="vs_mailer_mailgun_region"]' ) ).toHaveValue( 'eu' );
	} );

	test( 'toggles email logging', async ( { page } ) => {
		await page.goto( ADMIN_URL );

		await page.check( 'input[name="vs_mailer_log_emails"]' );
		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( ADMIN_URL );
		await expect( page.locator( 'input[name="vs_mailer_log_emails"]' ) ).toBeChecked();

		await page.uncheck( 'input[name="vs_mailer_log_emails"]' );
		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( ADMIN_URL );
		await expect( page.locator( 'input[name="vs_mailer_log_emails"]' ) ).not.toBeChecked();
	} );

	test( 'test email form validates empty input', async ( { page } ) => {
		await page.goto( ADMIN_URL + '&tab=test' );

		await page.click( 'button:has-text("Send Test Email")' );

		await expect( page.locator( '.notice-error' ) ).toBeVisible();
	} );
} );

test.describe( 'Admin UI — log page', () => {

	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'log shows entries after email is sent with logging enabled', async ( { page } ) => {
		// Clear any pre-existing log entries for isolation.
		await page.goto( ADMIN_URL + '&tab=log' );
		if ( await page.locator( 'button:has-text("Clear Log")' ).isVisible() ) {
			await page.click( 'button:has-text("Clear Log")' );
			await page.waitForLoadState( 'networkidle' );
		}

		await page.goto( ADMIN_URL );
		await page.check( 'input[name="vs_mailer_log_emails"]' );
		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( ADMIN_URL + '&tab=test' );
		await page.fill( 'input[name="test_email"]', 'test@example.com' );
		await page.click( 'button:has-text("Send Test Email")' );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( ADMIN_URL + '&tab=log' );
		await expect( page.locator( '.widefat tbody tr' ) ).toHaveCount( 1 );
	} );

	test( 'clear log button empties the log', async ( { page } ) => {
		// Clear any pre-existing log entries for isolation.
		await page.goto( ADMIN_URL + '&tab=log' );
		if ( await page.locator( 'button:has-text("Clear Log")' ).isVisible() ) {
			await page.click( 'button:has-text("Clear Log")' );
			await page.waitForLoadState( 'networkidle' );
		}

		await page.goto( ADMIN_URL );
		await page.check( 'input[name="vs_mailer_log_emails"]' );
		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( ADMIN_URL + '&tab=test' );
		await page.fill( 'input[name="test_email"]', 'clear@example.com' );
		await page.click( 'button:has-text("Send Test Email")' );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( ADMIN_URL + '&tab=log' );
		await expect( page.locator( '.widefat tbody tr' ) ).toHaveCount( 1 );

		await page.click( 'button:has-text("Clear Log")' );
		await page.waitForLoadState( 'networkidle' );

		await expect( page.locator( '.notice-info' ) ).toContainText( 'No emails logged yet' );
	} );
} );
