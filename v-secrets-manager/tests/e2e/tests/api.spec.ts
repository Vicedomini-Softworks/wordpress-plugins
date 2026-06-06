import { test, expect } from '@playwright/test';

const API_BASE = '/wp-json/vs-secrets-manager/v1';

async function login( page: any ): Promise<void> {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForLoadState( 'networkidle' );
}

type APIResponse = { status: number; body: any };

async function apiFetch(
	page: any,
	{ method = 'GET', path, data }: { method?: string; path: string; data?: any }
): Promise<APIResponse> {
	return page.evaluate(
		async ( { method, path, data }: { method: string; path: string; data?: any } ) => {
			const nonce = ( window as any ).vsSecretsManager?.nonce || '';
			const res = await fetch( path, {
				method,
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				credentials: 'include',
				body: data !== undefined ? JSON.stringify( data ) : undefined,
			} );
			const body = await res.json().catch( () => null );
			return { status: res.status, body };
		},
		{ method, path, data }
	);
}

test.describe( 'REST API — secrets CRUD', () => {

	test.beforeEach( async ( { page } ) => {
		await login( page );
		// Navigate to the plugin page so wpApiSettings.nonce is available.
		await page.goto( '/wp-admin/admin.php?page=vs-secrets-manager' );
		await page.waitForLoadState( 'networkidle' );
	} );

	test( 'GET /secrets returns empty list', async ( { page } ) => {
		const res = await apiFetch( page, { path: `${ API_BASE }/secrets` } );
		expect( res.status ).toBe( 200 );
		expect( Array.isArray( res.body ) ).toBeTruthy();
	} );

	test( 'POST /secrets creates a secret', async ( { page } ) => {
		const res = await apiFetch( page, {
			method: 'POST',
			path: `${ API_BASE }/secrets`,
			data: {
				name: 'test_api_key',
				title: 'Test API Key',
				value: 'sk_test_12345',
				provider: 'db',
			},
		} );
		expect( res.status ).toBe( 201 );
		expect( res.body.name ).toBe( 'test_api_key' );
		expect( res.body.provider ).toBe( 'db' );
	} );

	test( 'GET /secrets/{id} returns decrypted value', async ( { page } ) => {
		const listRes = await apiFetch( page, { path: `${ API_BASE }/secrets` } );
		expect( Array.isArray( listRes.body ) ).toBeTruthy();
		expect( listRes.body.length ).toBeGreaterThanOrEqual( 1 );

		const target = listRes.body.find( ( s: any ) => s.name === 'test_api_key' );
		expect( target ).toBeDefined();

		const res = await apiFetch( page, { path: `${ API_BASE }/secrets/${ target.id }` } );
		expect( res.status ).toBe( 200 );
		expect( res.body.value ).toBe( 'sk_test_12345' );
		expect( res.body.title ).toBe( 'Test API Key' );
	} );

	test( 'PUT /secrets/{id} updates a secret', async ( { page } ) => {
		const listRes = await apiFetch( page, { path: `${ API_BASE }/secrets` } );
		const target = listRes.body.find( ( s: any ) => s.name === 'test_api_key' );

		const updateRes = await apiFetch( page, {
			method: 'PUT',
			path: `${ API_BASE }/secrets/${ target.id }`,
			data: {
				value: 'sk_updated_67890',
				title: 'Updated API Key',
			},
		} );
		expect( updateRes.status ).toBe( 200 );

		const getRes = await apiFetch( page, { path: `${ API_BASE }/secrets/${ target.id }` } );
		expect( getRes.body.value ).toBe( 'sk_updated_67890' );
		expect( getRes.body.title ).toBe( 'Updated API Key' );
	} );

	test( 'DELETE /secrets/{id} removes a secret', async ( { page } ) => {
		const listRes = await apiFetch( page, { path: `${ API_BASE }/secrets` } );
		const target = listRes.body.find( ( s: any ) => s.name === 'test_api_key' );

		const delRes = await apiFetch( page, {
			method: 'DELETE',
			path: `${ API_BASE }/secrets/${ target.id }`,
		} );
		expect( delRes.status ).toBe( 200 );

		const getRes = await apiFetch( page, { path: `${ API_BASE }/secrets/${ target.id }` } );
		expect( getRes.status ).toBe( 404 );
	} );

	test( 'POST /test-connection for db provider', async ( { page } ) => {
		const res = await apiFetch( page, {
			method: 'POST',
			path: `${ API_BASE }/test-connection`,
			data: { provider: 'db' },
		} );
		expect( res.status ).toBe( 200 );
		expect( res.body.success ).toBeTruthy();
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
		await login( page );
		await page.goto( '/wp-admin/admin.php?page=vs-secrets-manager' );
		await expect( page.locator( 'h1' ) ).toContainText( 'Secrets' );
	} );

	test( 'Add New secret page renders form fields', async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/admin.php?page=vs-secrets-manager-add' );
		await expect( page.locator( '#vs-secret-name' ) ).toBeVisible();
		await expect( page.locator( '#vs-secret-title' ) ).toBeVisible();
		await expect( page.locator( '#vs-secret-provider' ) ).toBeVisible();
		await expect( page.locator( '#vs-secret-value' ) ).toBeVisible();
	} );

	test( 'Settings page has AWS and Vault sections', async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/admin.php?page=vs-secrets-manager-settings' );
		await expect( page.locator( 'h2' ).first() ).toContainText( 'AWS' );
		await expect( page.locator( '#vs-vault-address' ) ).toBeVisible();
	} );

	test( 'settings form saves successfully', async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/admin.php?page=vs-secrets-manager-settings' );
		await page.fill( '#vs-vault-mount', 'my-secrets' );
		await page.click( 'button:has-text("Save Settings")' );
		await expect( page.locator( '.notice-success' ) ).toBeVisible();
	} );
} );
