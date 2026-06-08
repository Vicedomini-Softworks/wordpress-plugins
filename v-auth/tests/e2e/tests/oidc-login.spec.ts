import { test, expect } from '@playwright/test';
import { login, logout, addMockProvider, setLoginMode, MOCK_IDP_URL } from './helpers';

test.describe( 'OIDC SSO login', () => {

	test.beforeAll( async ( { browser } ) => {
		const page = await browser.newPage();
		await login( page );
		await addMockProvider( page );
		await page.close();
	} );

	test( 'V-Auth menu item exists and provider is listed', async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/options-general.php?page=v-auth' );
		await expect( page.locator( 'h1' ) ).toContainText( 'V-Auth' );
		await expect( page.locator( 'table.widefat' ) ).toContainText( 'Mock IdP' );
		await expect( page.locator( 'table.widefat' ) ).toContainText( MOCK_IDP_URL );
	} );

	test( 'SSO button appears on the login screen in button mode', async ( { page } ) => {
		await setLoginMode( page, 'button' );
		await logout( page );

		await page.goto( '/wp-login.php' );
		const button = page.locator( '.v-auth-sso-button', { hasText: 'Mock IdP' } );
		await expect( button ).toBeVisible();
		await expect( button ).toHaveAttribute( 'href', /\/wp-json\/v-auth\/v1\/oidc\/.+\/authorize/ );
	} );

	test.skip( 'completes the authorization-code flow and provisions a new WP user', async ( { page } ) => {
		await setLoginMode( page, 'button' );
		await logout( page );

		await page.goto( '/wp-login.php' );
		await Promise.all([
			page.waitForNavigation({ timeout: 60000 }),
			page.click( '.v-auth-sso-button' ),
		]);

		// After login, visit front‑end to see admin bar (subscriber can see it on front‑end)
		await page.goto( '/' );
		await expect( page.locator( '#wpadminbar' ) ).toBeVisible();





		// New account auto-created and mapped by email from the ID token claims.
		await page.goto( '/wp-admin/users.php?s=sso-user%40example.com' );
		await expect( page.locator( 'td.username' ) ).toContainText( 'sso-user@example.com' );
	} );

	test( 'force-redirect mode sends wp-login straight to the IdP, with a bypass escape hatch', async ( { page } ) => {
		await page.context().clearCookies();
		await login( page );
		await setLoginMode( page, 'force_redirect' );
		await page.context().clearCookies();

		// Without bypass, wp-login redirects into the OIDC flow (and back to wp-admin via mock IdP auto-approve).
		await page.goto( '/wp-login.php' );
		// Expect automatic redirect to the IdP authorize endpoint
		await page.waitForURL( /\/wp-json\/v-auth\/v1\/oidc\/.+\/authorize/, { timeout: 60000 } );
		// Follow through to admin (final redirect after callback)
		await page.waitForLoadState('networkidle');

		// After login, visit front‑end to verify admin bar visibility
		await page.goto( '/' );
		await expect( page.locator( '#wpadminbar' ) ).toBeVisible();






		await logout( page );

		// Bypass param reaches the normal login form untouched.
		await page.goto( '/wp-login.php?v_auth_bypass=1' );
		await expect( page.locator( '#loginform' ) ).toBeVisible();

		// Reset to button mode for test isolation.
		await page.context().clearCookies();
		await login( page );
		await setLoginMode( page, 'button' );
	} );
} );
