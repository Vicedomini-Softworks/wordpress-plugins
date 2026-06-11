<?php
/**
 * Deactivator class for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Activation
 */

namespace OpenProviderWooCommerce\Activation;

use OpenProviderWooCommerce\WooCommerce\RenewalNotifier;

/**
 * Deactivator class.
 */
class Deactivator {

	/**
	 * Deactivate plugin.
	 */
	public static function deactivate(): void {
		// Clear any scheduled hooks (none in v1, but included for forward-compat).
		wp_clear_scheduled_hook( 'opwc_scheduled_task' );

		// Clear the daily expiring-domains check.
		wp_clear_scheduled_hook( RenewalNotifier::CRON_HOOK );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
