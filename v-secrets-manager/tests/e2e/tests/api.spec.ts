import { test, expect } from '@playwright/test';

const API_BASE = '/wp-json/vs-secrets-manager/v1';

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

test.describe( 'REST API — secrets CRUD', () => {

	test.beforeEach( async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/' );
	} );

	test( 'GET /secrets returns empty list', async ( { request, page } ) => {
		const nonce = await getNonce( page );

		const res = await request.get( `${ API_BASE }/secrets`, {
			headers: { 'X-WP-Nonce': nonce },
		} );
		expect( res.status() ).toBe( 200 );
		const body = await res.json();
		expect( Array.isArray( body ) ).toBeTruthy();
	} );

	test( 'POST /secrets creates a secret', async ( { request, page } ) => {
		const nonce = await getNonce( page );

		const res = await request.post( `${ API_BASE }/secrets`, {
			headers: { 'X-WP-Nonce': nonce },
			data: {
				name: 'test_api_key',
				title: 'Test API Key',
				value: 'sk_test_12345',
				provider: 'db',
			},
		} );
		expect( res.status() ).toBe( 201 );

		const body = await res.json();
		expect( body.name ).toBe( 'test_api_key' );
		expect( body.provider ).toBe( 'db' );
	} );

	test( 'GET /secrets/{id} returns decrypted value', async ( { request, page } ) => {
		const nonce = await getNonce( page );

		const listRes = await request.get( `${ API_BASE }/secrets`, {
			headers: { 'X-WP-Nonce': nonce },
		} );
		const secrets = await listRes.json();
		expect( secrets.length ).toBeGreaterThanOrEqual( 1 );

		const target = secrets.find( ( s: any ) => s.name === 'test_api_key' );
		expect( target ).toBeDefined();

		const res = await request.get( `${ API_BASE }/secrets/${ target.id }`, {
			headers: { 'X-WP-Nonce': nonce },
		} );
		expect( res.status() ).toBe( 200 );

		const body = await res.json();
		expect( body.value ).toBe( 'sk_test_12345' );
		expect( body.title ).toBe( 'Test API Key' );
	} );

	test( 'PUT /secrets/{id} updates a secret', async ( { request, page } ) => {
		const nonce = await getNonce( page );

		const listRes = await request.get( `${ API_BASE }/secrets`, {
			headers: { 'X-WP-Nonce': nonce },
		} );
		const secrets = await listRes.json();
		const target = secrets.find( ( s: any ) => s.name === 'test_api_key' );

		const updateRes = await request.put( `${ API_BASE }/secrets/${ target.id }`, {
			headers: { 'X-WP-Nonce': nonce },
			data: {
				value: 'sk_updated_67890',
				title: 'Updated API Key',
			},
		} );
		expect( updateRes.status() ).toBe( 200 );

		const getRes = await request.get( `${ API_BASE }/secrets/${ target.id }`, {
			headers: { 'X-WP-Nonce': nonce },
		} );
		const updated = await getRes.json();
		expect( updated.value ).toBe( 'sk_updated_67890' );
		expect( updated.title ).toBe( 'Updated API Key' );
	} );

	test( 'DELETE /secrets/{id} removes a secret', async ( { request, page } ) => {
		const nonce = await getNonce( page );

		const listRes = await request.get( `${ API_BASE }/secrets`, {
			headers: { 'X-WP-Nonce': nonce },
		} );
		const secrets = await listRes.json();
		const target = secrets.find( ( s: any ) => s.name === 'test_api_key' );

		const delRes = await request.delete( `${ API_BASE }/secrets/${ target.id }`, {
			headers: { 'X-WP-Nonce': nonce },
		} );
		expect( delRes.status() ).toBe( 200 );

		const getRes = await request.get( `${ API_BASE }/secrets/${ target.id }`, {
			headers: { 'X-WP-Nonce': nonce },
		} );
		expect( getRes.status() ).toBe( 404 );
	} );

	test( 'POST /test-connection for db provider', async ( { request, page } ) => {
		const nonce = await getNonce( page );

		const res = await request.post( `${ API_BASE }/test-connection`, {
			headers: { 'X-WP-Nonce': nonce },
			data: { provider: 'db' },
		} );
		expect( res.status() ).toBe( 200 );

		const body = await res.json();
		expect( body.success ).toBeTruthy();
	} );

	test( 'POST /secrets without auth returns 401', async ( { request } ) => {
		const res = await request.post( `${ API_BASE }/secrets`, {
			data: { name: 'nope', value: 'test' },
		} );
		expect( res.status() ).toBe( 401 );
	} );
} );

test.describe( 'Admin UI', () => {

	test( 'plugin menu item exists', async ( { page } ) => {
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'admin' );
		await page.fill( '#user_pass', 'password' );
		await page.click( '#wp-submit' );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( '/wp-admin/admin.php?page=vs-secrets-manager' );
		await expect( page.locator( 'h1' ) ).toContainText( 'Secrets' );
	} );

	test( 'Add New secret page renders form fields', async ( { page } ) => {
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'admin' );
		await page.fill( '#user_pass', 'password' );
		await page.click( '#wp-submit' );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( '/wp-admin/admin.php?page=vs-secrets-manager-add' );
		await expect( page.locator( '#vs-secret-name' ) ).toBeVisible();
		await expect( page.locator( '#vs-secret-title' ) ).toBeVisible();
		await expect( page.locator( '#vs-secret-provider' ) ).toBeVisible();
		await expect( page.locator( '#vs-secret-value' ) ).toBeVisible();
	} );

	test( 'Settings page has AWS and Vault sections', async ( { page } ) => {
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'admin' );
		await page.fill( '#user_pass', 'password' );
		await page.click( '#wp-submit' );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( '/wp-admin/admin.php?page=vs-secrets-manager-settings' );
		await expect( page.locator( 'h2' ).first() ).toContainText( 'AWS' );
		await expect( page.locator( '#vs-vault-address' ) ).toBeVisible();
	} );

	test( 'settings form saves successfully', async ( { page } ) => {
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'admin' );
		await page.fill( '#user_pass', 'password' );
		await page.click( '#wp-submit' );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( '/wp-admin/admin.php?page=vs-secrets-manager-settings' );
		await page.fill( '#vs-vault-mount', 'my-secrets' );
		await page.click( 'button:has-text("Save Settings")' );

		await expect( page.locator( '.notice-success' ) ).toBeVisible();
	} );
} );
