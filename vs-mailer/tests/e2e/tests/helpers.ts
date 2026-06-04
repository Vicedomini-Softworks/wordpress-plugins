import { Page } from '@playwright/test';

export async function login( page: Page ): Promise<void> {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForLoadState( 'networkidle' );
}

export async function getNonce( page: Page ): Promise<string> {
	return await page.evaluate( () => {
		return ( window as any ).wpApiSettings?.nonce || '';
	} );
}
