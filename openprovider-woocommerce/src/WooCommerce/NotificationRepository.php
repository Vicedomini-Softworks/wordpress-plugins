<?php
/**
 * Notification Repository class for OpenProvider WooCommerce
 *
 * CRUD operations for wp_op_domain_notifications table.
 *
 * @package OpenProviderWooCommerce\WooCommerce
 */

namespace OpenProviderWooCommerce\WooCommerce;

/**
 * Notification Repository class.
 */
class NotificationRepository {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'op_domain_notifications';
	}

	/**
	 * Check whether a notification has already been sent for a domain.
	 *
	 * @param int    $domain_id Domain ID.
	 * @param string $notification_type Notification type.
	 * @return bool True if already sent.
	 */
	public function has_been_sent( int $domain_id, string $notification_type ): bool {
		global $wpdb;

		$sent_at = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT sent_at FROM {$this->table} WHERE domain_id = %d AND notification_type = %s",
				$domain_id,
				$notification_type
			)
		);

		return ! empty( $sent_at );
	}

	/**
	 * Mark a notification as sent for a domain.
	 *
	 * @param int    $domain_id Domain ID.
	 * @param string $notification_type Notification type.
	 * @return bool True on success.
	 */
	public function mark_sent( int $domain_id, string $notification_type ): bool {
		global $wpdb;

		return false !== $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$this->table} (domain_id, notification_type, sent_at, created_at)
				VALUES (%d, %s, %s, %s)
				ON DUPLICATE KEY UPDATE sent_at = VALUES(sent_at)",
				$domain_id,
				$notification_type,
				current_time( 'mysql' ),
				current_time( 'mysql' )
			)
		);
	}

	/**
	 * Reset notifications for a domain (e.g. after renewal pushes expiry out).
	 *
	 * @param int $domain_id Domain ID.
	 * @return bool True on success.
	 */
	public function clear_for_domain( int $domain_id ): bool {
		global $wpdb;

		return false !== $wpdb->delete( $this->table, array( 'domain_id' => $domain_id ), array( '%d' ) );
	}
}
