<?php
/**
 * Activator class for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Activation
 */

namespace OpenProviderWooCommerce\Activation;

use OpenProviderWooCommerce\Support\Settings;

/**
 * Activator class.
 */
class Activator {

	/**
	 * Activate plugin.
	 */
	public static function activate(): void {
		// Create/upgrade database tables.
		Schema::maybe_upgrade();

		// Set default options if not present.
		self::set_default_options();

		// Flush rewrite rules (if any).
		flush_rewrite_rules();
	}

	/**
	 * Set default options.
	 */
	private static function set_default_options(): void {
		$settings = new Settings();
		$all = $settings->all();

		// Only set if option doesn't exist.
		if ( false === get_option( Settings::OPTION_KEY ) ) {
			update_option( Settings::OPTION_KEY, $all );
		}
	}
}
