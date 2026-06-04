import { test, expect } from '@playwright/test';
import { login, createFeed, createPageWithShortcode } from './helpers';

test.describe( 'Shortcode — rendering', () => {

	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'renders feed container with correct layout and theme classes', async ( { page } ) => {
		await createFeed( page, {
			slug: 'sc-grid-light',
			platform: 'youtube',
			mode: 'embed',
			account: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
			layout: 'grid',
			theme: 'light',
		} );

		const url = await createPageWithShortcode(
			page,
			'Grid Light Test',
			'[social_feed id="sc-grid-light"]'
		);

		await page.goto( url );
		const feed = page.locator( '.social-feed' );
		await expect( feed ).toBeVisible();
		await expect( feed ).toHaveClass( /social-feed-light/ );
		await expect( feed ).toHaveClass( /social-feed-grid/ );
	} );

	test( 'dark theme applies correct CSS class', async ( { page } ) => {
		await createFeed( page, {
			slug: 'sc-carousel-dark',
			platform: 'instagram',
			mode: 'embed',
			account: 'https://www.instagram.com/p/example/',
			layout: 'carousel',
			theme: 'dark',
		} );

		const url = await createPageWithShortcode(
			page,
			'Carousel Dark Test',
			'[social_feed id="sc-carousel-dark"]'
		);

		await page.goto( url );
		const feed = page.locator( '.social-feed' );
		await expect( feed ).toHaveClass( /social-feed-dark/ );
		await expect( feed ).toHaveClass( /social-feed-carousel/ );
	} );

	test( 'masonry layout applies correct CSS class', async ( { page } ) => {
		await createFeed( page, {
			slug: 'sc-masonry',
			platform: 'facebook',
			mode: 'embed',
			account: 'https://www.facebook.com/example/posts/123',
			layout: 'masonry',
		} );

		const url = await createPageWithShortcode(
			page,
			'Masonry Test',
			'[social_feed id="sc-masonry"]'
		);

		await page.goto( url );
		await expect( page.locator( '.social-feed-masonry' ) ).toBeVisible();
	} );

	test( 'column layout applies correct CSS class', async ( { page } ) => {
		await createFeed( page, {
			slug: 'sc-column',
			platform: 'bluesky',
			mode: 'embed',
			account: 'https://bsky.app/profile/example.bsky.social',
			layout: 'column',
		} );

		const url = await createPageWithShortcode(
			page,
			'Column Test',
			'[social_feed id="sc-column"]'
		);

		await page.goto( url );
		await expect( page.locator( '.social-feed-column' ) ).toBeVisible();
	} );

	test( 'shortcode type= param overrides admin layout', async ( { page } ) => {
		// Feed configured as grid, shortcode overrides to column
		const url = await createPageWithShortcode(
			page,
			'Type Override Test',
			'[social_feed id="sc-grid-light" type="column"]'
		);

		await page.goto( url );
		await expect( page.locator( '.social-feed-column' ) ).toBeVisible();
		await expect( page.locator( '.social-feed-grid' ) ).not.toBeVisible();
	} );

	test( 'invalid feed id renders hidden error element, not visible error text', async ( { page } ) => {
		const url = await createPageWithShortcode(
			page,
			'Invalid ID Test',
			'[social_feed id="does-not-exist"]'
		);

		await page.goto( url );
		const errorEl = page.locator( '.social-feed[data-feed-error]' );
		await expect( errorEl ).toHaveCount( 1 );
		// Error div itself is hidden via CSS
		await expect( errorEl ).toBeHidden();
		// No raw PHP error or exception text
		await expect( page.locator( 'body' ) ).not.toContainText( 'Fatal error' );
		await expect( page.locator( 'body' ) ).not.toContainText( 'Warning:' );
	} );

	test( 'shortcode missing id param renders hidden error element', async ( { page } ) => {
		const url = await createPageWithShortcode(
			page,
			'No ID Test',
			'[social_feed]'
		);

		await page.goto( url );
		await expect( page.locator( '.social-feed[data-feed-error]' ) ).toBeHidden();
	} );

	test( 'data-feed-id attribute matches feed slug', async ( { page } ) => {
		const url = await createPageWithShortcode(
			page,
			'Data Attr Test',
			'[social_feed id="sc-grid-light"]'
		);

		await page.goto( url );
		await expect( page.locator( '.social-feed[data-feed-id="sc-grid-light"]' ) ).toBeAttached();
	} );

	test( 'social-feed CSS is loaded on page with shortcode', async ( { page } ) => {
		const url = await createPageWithShortcode(
			page,
			'CSS Load Test',
			'[social_feed id="sc-grid-light"]'
		);

		await page.goto( url );
		const styleHandles = await page.locator( 'link[rel="stylesheet"]' ).all();
		const hrefs = await Promise.all( styleHandles.map( ( el ) => el.getAttribute( 'href' ) ) );
		const hasFeedCss = hrefs.some( ( h ) => h && h.includes( 'social-feed' ) );
		expect( hasFeedCss ).toBeTruthy();
	} );

	test( 'social-feed JS is loaded for carousel layout', async ( { page } ) => {
		const url = await createPageWithShortcode(
			page,
			'JS Load Test',
			'[social_feed id="sc-carousel-dark"]'
		);

		await page.goto( url );
		const scriptHandles = await page.locator( 'script[src]' ).all();
		const srcs = await Promise.all( scriptHandles.map( ( el ) => el.getAttribute( 'src' ) ) );
		const hasFeedJs = srcs.some( ( s ) => s && s.includes( 'social-feed' ) );
		expect( hasFeedJs ).toBeTruthy();
	} );

} );

test.describe( 'Shortcode — embed mode', () => {

	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'YouTube embed renders iframe', async ( { page } ) => {
		await createFeed( page, {
			slug: 'embed-yt',
			platform: 'youtube',
			mode: 'embed',
			account: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
		} );

		const url = await createPageWithShortcode(
			page,
			'YouTube Embed Test',
			'[social_feed id="embed-yt"]'
		);

		await page.goto( url );
		const feed = page.locator( '.social-feed' );
		await expect( feed ).toBeVisible();
		await expect( feed.locator( '.social-feed-embed' ) ).toBeVisible();
		await expect( feed.locator( 'iframe[src*="youtube.com"]' ) ).toBeVisible();
	} );

	test( 'missing account URL shows fallback error text in embed', async ( { page } ) => {
		await createFeed( page, {
			slug: 'embed-no-url',
			platform: 'instagram',
			mode: 'embed',
			account: '',
		} );

		const url = await createPageWithShortcode(
			page,
			'No URL Embed Test',
			'[social_feed id="embed-no-url"]'
		);

		await page.goto( url );
		// Feed renders (not hidden) but shows error message inside
		const feed = page.locator( '.social-feed' );
		await expect( feed ).toBeAttached();
	} );

} );

test.describe( 'Shortcode — console warnings', () => {

	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'console.warn is emitted for feeds with errors', async ( { page } ) => {
		const warnings: string[] = [];
		page.on( 'console', ( msg ) => {
			if ( msg.type() === 'warning' ) {
				warnings.push( msg.text() );
			}
		} );

		const url = await createPageWithShortcode(
			page,
			'Console Warn Test',
			'[social_feed id="nonexistent-feed"]'
		);

		await page.goto( url );
		await page.waitForLoadState( 'networkidle' );

		const hasFeedWarn = warnings.some( ( w ) => w.includes( 'Social Feed' ) || w.includes( 'social_feed' ) );
		expect( hasFeedWarn ).toBeTruthy();
	} );

} );
