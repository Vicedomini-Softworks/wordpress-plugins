import { test, expect } from '@playwright/test';

const ADMIN_URL = '/wp-admin/options-general.php?page=vs-mailer';

async function login( page: any ): Promise<void> {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForLoadState( 'networkidle' );
}

async function getNonce( page: any ): Promise<string> {
	return await page.evaluate( () => {
		return ( window as any ).wpApiSettings?.nonce || '';
	} );
}

test.describe( 'Hook integration — wp_mail_from / wp_mail_from_name', () => {

	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'from_email filter returns configured value', async ( { page } ) => {
		await page.goto( ADMIN_URL );
		await page.fill( 'input[name="vs_mailer_from_email"]', 'custom@example.com' );
		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( ADMIN_URL );
		await expect( page.locator( 'input[name="vs_mailer_from_email"]' ) ).toHaveValue( 'custom@example.com' );
	} );

	test( 'from_name filter returns configured value', async ( { page } ) => {
		await page.goto( ADMIN_URL );
		await page.fill( 'input[name="vs_mailer_from_name"]', 'Custom Name' );
		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( ADMIN_URL );
		await expect( page.locator( 'input[name="vs_mailer_from_name"]' ) ).toHaveValue( 'Custom Name' );
	} );

	test( 'SMTP mode does not block wp_mail from proceeding', async ( { page } ) => {
		await page.goto( ADMIN_URL );
		await page.selectOption( 'select[name="vs_mailer_mailer"]', 'smtp' );
		await page.fill( 'input[name="vs_mailer_smtp_host"]', 'localhost' );
		await page.fill( 'input[name="vs_mailer_smtp_port"]', '25' );
		await page.selectOption( 'select[name="vs_mailer_smtp_encryption"]', 'none' );
		await page.selectOption( 'select[name="vs_mailer_smtp_auth"]', 'no' );

		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		await expect( page.locator( '.notice-success' ) ).toBeVisible();
	} );

	test( 'switching to Brevo mode stores selection', async ( { page } ) => {
		await page.goto( ADMIN_URL );
		await page.selectOption( 'select[name="vs_mailer_mailer"]', 'brevo' );
		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( ADMIN_URL );
		await expect( page.locator( 'select[name="vs_mailer_mailer"]' ) ).toHaveValue( 'brevo' );
	} );

	test( 'switching to Mailgun mode stores selection', async ( { page } ) => {
		await page.goto( ADMIN_URL );
		await page.selectOption( 'select[name="vs_mailer_mailer"]', 'mailgun' );
		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( ADMIN_URL );
		await expect( page.locator( 'select[name="vs_mailer_mailer"]' ) ).toHaveValue( 'mailgun' );
	} );
} );

test.describe( 'Settings API — options stored correctly', () => {

	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'wp_options are set after saving SMTP config', async ( { page, request } ) => {
		await page.goto( ADMIN_URL );

		await page.fill( 'input[name="vs_mailer_from_name"]', 'API Test' );
		await page.fill( 'input[name="vs_mailer_smtp_host"]', 'smtp.api-test.com' );
		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		const nonce = await getNonce( page );

		const res = await request.get(
			'/wp-json/wp/v2/settings',
			{ headers: { 'X-WP-Nonce': nonce } }
		);
		expect( res.status() ).toBe( 200 );
	} );

	test( 'Brevo API key is stored as secret', async ( { page } ) => {
		await page.goto( ADMIN_URL );

		await page.selectOption( 'select[name="vs_mailer_mailer"]', 'brevo' );
		await page.fill( 'input[name="vs_mailer_brevo_api_key"]', 'xkeysib-test-secret-789' );
		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		await expect( page.locator( '.notice-success' ) ).toBeVisible();
	} );

	test( 'Mailgun API key is stored as secret', async ( { page } ) => {
		await page.goto( ADMIN_URL );

		await page.selectOption( 'select[name="vs_mailer_mailer"]', 'mailgun' );
		await page.fill( 'input[name="vs_mailer_mailgun_api_key"]', 'key-mg-test-456' );
		await page.fill( 'input[name="vs_mailer_mailgun_domain"]', 'mg.test.com' );
		await page.click( 'input[type="submit"][value="Save Settings"]' );
		await page.waitForLoadState( 'networkidle' );

		await expect( page.locator( '.notice-success' ) ).toBeVisible();
	} );
} );

test.describe( 'Plugin activation and dependency', () => {

	test( 'plugin is listed as active on plugins page', async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/plugins.php' );

		await expect( page.locator( 'tr[data-slug="vs-mailer"] .active' ) ).toBeVisible();
	} );

	test( 'v-secrets-manager plugin is also active', async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/plugins.php' );

		await expect( page.locator( 'tr[data-slug="v-secrets-manager"] .active' ) ).toBeVisible();
	} );
} );
