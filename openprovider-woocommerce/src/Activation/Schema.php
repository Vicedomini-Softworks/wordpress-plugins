<?php
/**
 * Schema class for OpenProvider WooCommerce
 *
 * Database table definitions and migrations.
 *
 * @package OpenProviderWooCommerce\Activation
 */

namespace OpenProviderWooCommerce\Activation;

use OpenProviderWooCommerce\Support\Settings;

/**
 * Schema class.
 */
class Schema {

	/**
	 * Database version option key.
	 */
	private const VERSION_OPTION = 'opwc_db_version';

	/**
	 * Current database version.
	 */
	private const CURRENT_VERSION = '1.0.0';

	/**
	 * Get domains table SQL.
	 *
	 * @return string SQL for creating wp_op_domains table.
	 */
	public static function get_domains_table_sql(): string {
		global $wpdb;

		$charset_collate = '';
		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARSET={$wpdb->charset}";
		}
		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE={$wpdb->collate}";
		}

		return "CREATE TABLE {$wpdb->prefix}op_domains (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT(20) UNSIGNED NOT NULL,
			order_item_id BIGINT(20) UNSIGNED DEFAULT NULL,
			customer_id BIGINT(20) UNSIGNED DEFAULT NULL,
			domain_name VARCHAR(191) NOT NULL,
			tld VARCHAR(63) NOT NULL,
			registration_period SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			openprovider_domain_id VARCHAR(64) DEFAULT NULL,
			openprovider_order_id VARCHAR(64) DEFAULT NULL,
			status VARCHAR(32) NOT NULL DEFAULT 'pending',
			registered_at DATETIME DEFAULT NULL,
			expires_at DATETIME DEFAULT NULL,
			error_message TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY order_id (order_id),
			KEY customer_id (customer_id),
			KEY domain_name (domain_name),
			KEY status (status)
		) {$charset_collate};";
	}

	/**
	 * Create or upgrade database tables.
	 */
	public static function maybe_upgrade(): void {
		$installed_version = get_option( self::VERSION_OPTION, '0' );

		if ( version_compare( $installed_version, self::CURRENT_VERSION, '<' ) ) {
			self::create_tables();
			update_option( self::VERSION_OPTION, self::CURRENT_VERSION );
		}
	}

	/**
	 * Create database tables.
	 */
	private static function create_tables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( self::get_domains_table_sql() );
	}

	/**
	 * Get current database version.
	 *
	 * @return string Current version.
	 */
	public static function get_current_version(): string {
		return self::CURRENT_VERSION;
	}
}
