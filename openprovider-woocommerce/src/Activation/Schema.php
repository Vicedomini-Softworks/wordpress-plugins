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
	private const CURRENT_VERSION = '1.1.0';

	/**
	 * Get domains table SQL.
	 *
	 * @return string SQL for creating wp_op_domains table.
	 */
	public static function get_domains_table_sql(): string {
		global $wpdb;

		$charset_collate = self::get_charset_collate();

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
			auto_renew TINYINT(1) NOT NULL DEFAULT 0,
			renewal_period SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			transfer_auth_code VARCHAR(255) DEFAULT NULL,
			transfer_id VARCHAR(64) DEFAULT NULL,
			transfer_status VARCHAR(32) DEFAULT NULL,
			transfer_initiated_at DATETIME DEFAULT NULL,
			transfer_completed_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY order_id (order_id),
			KEY customer_id (customer_id),
			KEY domain_name (domain_name),
			KEY status (status),
			KEY expires_at (expires_at),
			KEY transfer_id (transfer_id),
			KEY transfer_status (transfer_status)
		) {$charset_collate};";
	}

	/**
	 * Get domain notifications table SQL.
	 *
	 * @return string SQL for creating wp_op_domain_notifications table.
	 */
	public static function get_domain_notifications_table_sql(): string {
		global $wpdb;

		$charset_collate = self::get_charset_collate();

		return "CREATE TABLE {$wpdb->prefix}op_domain_notifications (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			domain_id BIGINT(20) UNSIGNED NOT NULL,
			notification_type VARCHAR(32) NOT NULL,
			sent_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY domain_notification (domain_id, notification_type),
			KEY sent_at (sent_at)
		) {$charset_collate};";
	}

	/**
	 * Get charset/collate clause for table creation.
	 *
	 * @return string Charset/collate clause.
	 */
	private static function get_charset_collate(): string {
		global $wpdb;

		$charset_collate = '';
		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARSET={$wpdb->charset}";
		}
		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE={$wpdb->collate}";
		}

		return $charset_collate;
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
		dbDelta( self::get_domain_notifications_table_sql() );
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
