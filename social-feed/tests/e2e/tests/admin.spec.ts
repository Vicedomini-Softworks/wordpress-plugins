import { test, expect } from '@playwright/test';
import { login, createFeed, deleteAllFeeds } from './helpers';

const PLATFORMS = [ 'instagram', 'facebook', 'tiktok', 'x', 'threads', 'bluesky', 'youtube' ];

test.describe( 'Admin — Feeds', () => {

	test.beforeAll( async ( { browser } ) => {
		const page = await browser.newPage();
		await login( page );
		await deleteAllFeeds( page );
		await page.close();
	} );

	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'Social Feed menu item exists', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=social-feed' );
		await expect( page.locator( 'h1' ) ).toContainText( 'Social Feed' );
	} );

	test( 'empty state shows on feeds list', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=social-feed' );
		await expect( page.locator( '.notice-info' ) ).toBeVisible();
		await expect( page.locator( '.notice-info' ) ).toContainText( 'No feeds' );
	} );

	test( 'Add Feed form renders all required fields', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=social-feed-add' );
		await expect( page.locator( '#feed_slug' ) ).toBeVisible();
		await expect( page.locator( '#platform' ) ).toBeVisible();
		await expect( page.locator( '#mode' ) ).toBeVisible();
		await expect( page.locator( '#account' ) ).toBeVisible();
		await expect( page.locator( '#display_type' ) ).toBeVisible();
		await expect( page.locator( '#display_theme' ) ).toBeVisible();
		await expect( page.locator( '#display_limit' ) ).toBeVisible();
		await expect( page.locator( '#cache_hours' ) ).toBeVisible();
	} );

	test( 'Add Feed form defaults: mode=embed, theme=light, limit=8', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=social-feed-add' );
		await expect( page.locator( '#mode' ) ).toHaveValue( 'embed' );
		await expect( page.locator( '#display_theme' ) ).toHaveValue( 'light' );
		await expect( page.locator( '#display_limit' ) ).toHaveValue( '8' );
		await expect( page.locator( '#cache_hours' ) ).toHaveValue( '8' );
	} );

	test( 'X platform + oauth mode shows cost warning', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=social-feed-add' );
		await page.selectOption( '#platform', 'x' );
		await page.selectOption( '#mode', 'oauth' );
		await expect( page.locator( '#x-cost-warning' ) ).toBeVisible();
	} );

	test( 'TikTok + oauth mode shows approval notice', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=social-feed-add' );
		await page.selectOption( '#platform', 'tiktok' );
		await page.selectOption( '#mode', 'oauth' );
		await expect( page.locator( '#tiktok-approval-notice' ) ).toBeVisible();
	} );

	test( 'create feed and verify it appears in list with shortcode', async ( { page } ) => {
		await createFeed( page, {
			slug: 'test-yt',
			platform: 'youtube',
			mode: 'embed',
			account: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
			layout: 'grid',
			theme: 'light',
			limit: 4,
		} );

		await expect( page.locator( '.notice-success' ) ).toBeVisible();

		const row = page.locator( 'tr', { hasText: 'test-yt' } );
		await expect( row ).toBeVisible();
		await expect( row ).toContainText( 'youtube' );
		await expect( row.locator( 'code' ) ).toContainText( '[social_feed id="test-yt"' );
	} );

	test( 'edit feed link navigates to pre-filled form', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=social-feed' );
		const editLink = page.locator( 'tr', { hasText: 'test-yt' } ).locator( 'a', { hasText: 'Edit' } );
		await editLink.click();
		await page.waitForLoadState( 'networkidle' );

		await expect( page.locator( '#feed_slug' ) ).toHaveValue( 'test-yt' );
		await expect( page.locator( '#platform' ) ).toHaveValue( 'youtube' );
		// Slug field is readonly on edit
		await expect( page.locator( '#feed_slug' ) ).toHaveAttribute( 'readonly' );
	} );

	test( 'delete feed removes it from list', async ( { page } ) => {
		// Create a feed to delete
		await createFeed( page, {
			slug: 'delete-me',
			platform: 'bluesky',
		} );

		await page.goto( '/wp-admin/admin.php?page=social-feed' );
		const row = page.locator( 'tr', { hasText: 'delete-me' } );
		await expect( row ).toBeVisible();

		page.on( 'dialog', ( dialog ) => dialog.accept() );
		await row.locator( 'button.delete' ).click();
		await page.waitForLoadState( 'networkidle' );

		await expect( page.locator( '.notice-success' ) ).toBeVisible();
		await expect( page.locator( 'tr', { hasText: 'delete-me' } ) ).not.toBeVisible();
	} );

} );

test.describe( 'Admin — Platform Settings', () => {

	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'platform settings page renders all platform tabs', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=social-feed-platform-settings' );
		for ( const platform of PLATFORMS ) {
			await expect( page.locator( `.nav-tab[href*="platform=${ platform }"]` ) ).toBeVisible();
		}
	} );

	test( 'each platform tab shows client_id and client_secret fields', async ( { page } ) => {
		for ( const platform of PLATFORMS ) {
			await page.goto( `/wp-admin/admin.php?page=social-feed-platform-settings&platform=${ platform }` );
			await expect( page.locator( '#client_id' ) ).toBeVisible();
			await expect( page.locator( '#client_secret' ) ).toBeVisible();
		}
	} );

	test( 'OAuth redirect URI is shown for each platform', async ( { page } ) => {
		for ( const platform of PLATFORMS ) {
			await page.goto( `/wp-admin/admin.php?page=social-feed-platform-settings&platform=${ platform }` );
			const uriCode = page.locator( 'code', { hasText: `/oauth/${ platform }/callback` } );
			await expect( uriCode ).toBeVisible();
		}
	} );

	test( 'X platform shows API cost warning', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=social-feed-platform-settings&platform=x' );
		const warning = page.locator( '.notice-warning', { hasText: 'X API Costs' } );
		await expect( warning ).toBeVisible();
	} );

	test( 'TikTok platform shows approval notice', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=social-feed-platform-settings&platform=tiktok' );
		const notice = page.locator( '.notice-info', { hasText: 'audit' } );
		await expect( notice ).toBeVisible();
	} );

	test( 'saving credentials shows success redirect', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=social-feed-platform-settings&platform=youtube' );
		await page.fill( '#client_id', 'fake-client-id-123' );
		await page.click( 'button[name="social_feed_save_platform"]' );
		await page.waitForLoadState( 'networkidle' );

		await expect( page.locator( '.notice-success' ) ).toBeVisible();
	} );

	test( 'cache reset button clears cache and shows success', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=social-feed-platform-settings&platform=instagram' );
		await page.click( 'button[name="social_feed_reset_cache"]' );
		await page.waitForLoadState( 'networkidle' );

		await expect( page ).toHaveURL( /reset=1/ );
		await expect( page.locator( '.notice-success' ) ).toBeVisible();
	} );

} );
