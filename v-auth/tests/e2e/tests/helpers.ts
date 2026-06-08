import { Page } from '@playwright/test';

export async function login( page: Page ): Promise<void> {
	await page.goto( '/wp-login.php' );
	// If login form is present, perform login; otherwise assume already authenticated.
	const loginInput = await page.$( '#user_login' );
	if ( loginInput ) {
		await page.waitForSelector( '#user_login', { timeout: 120000 } );
		await page.fill( '#user_login', 'admin' );
		await page.fill( '#user_pass', 'password' );
		await page.click( '#wp-submit' );
		await page.waitForLoadState( 'networkidle' );
	}
}

export async function logout( page: Page ): Promise<void> {
	await page.goto( '/wp-login.php?action=logout&_wpnonce=skip' );
	await page.goto( '/wp-login.php' );
}

const MOCK_IDP_PORT = process.env.MOCK_IDP_PORT || '9402';
export const MOCK_IDP_URL = `http://127.0.0.1:${ MOCK_IDP_PORT }`;

/**
 * Add the mock IdP as a V-Auth provider via the admin form, return its slug.
 */
export async function addMockProvider( page: Page, displayName = 'Mock IdP' ): Promise<void> {
	await page.goto( '/wp-admin/options-general.php?page=v-auth' );
	await page.fill( '#display_name', displayName );
	await page.fill( '#issuer', MOCK_IDP_URL );
	await page.fill( '#client_id', 'mock-client-id' );
	await page.fill( '#client_secret', 'mock-client-secret' );
	// WP's submit_button() renders <input type="submit">, exposed with accessible role "button".
	await page.getByRole( 'button', { name: 'Add Provider', exact: true } ).click();
	await page.waitForLoadState( 'networkidle' );
}

export async function setLoginMode( page: Page, mode: 'button' | 'force_redirect' ): Promise<void> {
	await page.goto( '/wp-admin/options-general.php?page=v-auth' );
	await page.check( `input[name="v_auth_login_mode"][value="${ mode }"]` );
	await page.getByRole( 'button', { name: 'Save Settings', exact: true } ).click();
	await page.waitForLoadState( 'networkidle' );
}
