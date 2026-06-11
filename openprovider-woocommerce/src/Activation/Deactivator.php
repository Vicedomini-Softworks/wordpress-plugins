<?php
/**
 * Deactivator class for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Activation
 */

namespace OpenProviderWooCommerce\Activation;

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

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
