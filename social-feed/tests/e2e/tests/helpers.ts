import { Page } from '@playwright/test';

export async function login( page: Page ): Promise<void> {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForLoadState( 'networkidle' );
}

export async function getNonce( page: Page ): Promise<string> {
	return page.evaluate( () => ( window as any ).wpApiSettings?.nonce ?? '' );
}

/**
 * Create a WordPress page with a shortcode via REST API, return its URL.
 */
export async function createPageWithShortcode(
	page: Page,
	title: string,
	shortcode: string
): Promise<string> {
	await page.goto( '/wp-admin/' );
	const nonce = await getNonce( page );

	const res = await page.request.post( '/wp-json/wp/v2/pages', {
		headers: {
			'X-WP-Nonce': nonce,
			'Content-Type': 'application/json',
		},
		data: {
			title,
			content: shortcode,
			status: 'publish',
		},
	} );

	const body = await res.json();
	return body.link as string;
}

/**
 * Create a social-feed feed via admin form and return the feed slug.
 */
export async function createFeed(
	page: Page,
	opts: {
		slug: string;
		platform?: string;
		mode?: string;
		account?: string;
		layout?: string;
		theme?: string;
		limit?: number;
	}
): Promise<void> {
	await page.goto( '/wp-admin/admin.php?page=social-feed-add' );

	await page.fill( '#feed_slug', opts.slug );

	if ( opts.platform ) {
		await page.selectOption( '#platform', opts.platform );
	}
	if ( opts.mode ) {
		await page.selectOption( '#mode', opts.mode );
	}
	if ( opts.account ) {
		await page.fill( '#account', opts.account );
	}
	if ( opts.layout ) {
		await page.selectOption( '#display_type', opts.layout );
	}
	if ( opts.theme ) {
		await page.selectOption( '#display_theme', opts.theme );
	}
	if ( opts.limit !== undefined ) {
		await page.fill( '#display_limit', String( opts.limit ) );
	}

	await page.click( 'button[name="social_feed_save_feed"]' );
	await page.waitForLoadState( 'networkidle' );
}
