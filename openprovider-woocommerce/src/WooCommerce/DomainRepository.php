<?php
/**
 * Domain Repository class for OpenProvider WooCommerce
 *
 * CRUD operations for wp_op_domains table.
 *
 * @package OpenProviderWooCommerce\WooCommerce
 */

namespace OpenProviderWooCommerce\WooCommerce;

/**
 * Domain Repository class.
 */
class DomainRepository {

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
		$this->table = $wpdb->prefix . 'op_domains';
	}

	/**
	 * Insert a new domain record.
	 *
	 * @param array $data Domain data.
	 * @return int Inserted ID.
	 */
	public function insert( array $data ): int {
		global $wpdb;

		$defaults = array(
			'order_id'            => 0,
			'order_item_id'       => null,
			'customer_id'         => null,
			'domain_name'         => '',
			'tld'                 => '',
			'registration_period' => 1,
			'status'              => 'pending',
			'registered_at'       => null,
			'expires_at'          => null,
			'error_message'       => null,
		);

		$data = wp_parse_args( $data, $defaults );

		$wpdb->insert(
			$this->table,
			array(
				'order_id'            => $data['order_id'],
				'order_item_id'       => $data['order_item_id'],
				'customer_id'         => $data['customer_id'],
				'domain_name'         => $data['domain_name'],
				'tld'                 => $data['tld'],
				'registration_period' => $data['registration_period'],
				'status'              => $data['status'],
				'registered_at'       => $data['registered_at'],
				'expires_at'          => $data['expires_at'],
				'error_message'       => $data['error_message'],
				'created_at'          => current_time( 'mysql' ),
				'updated_at'          => current_time( 'mysql' ),
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a domain record.
	 *
	 * @param int   $id Domain ID.
	 * @param array $data Data to update.
	 * @return bool True on success.
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		return (bool) $wpdb->update(
			$this->table,
			$data,
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Find a domain by ID.
	 *
	 * @param int $id Domain ID.
	 * @return object|null Domain record or null if not found.
	 */
	public function find( int $id ): ?object {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$id
			)
		);

		return $result;
	}

	/**
	 * Find domains by order ID.
	 *
	 * @param int $order_id Order ID.
	 * @return array Domain records.
	 */
	public function find_by_order( int $order_id ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE order_id = %d",
				$order_id
			)
		);

		return (array) $results;
	}

	/**
	 * Find a domain by domain name and TLD.
	 *
	 * @param string $domain_name Domain name.
	 * @param string $tld TLD.
	 * @return object|null Domain record or null if not found.
	 */
	public function find_by_domain( string $domain_name, string $tld ): ?object {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE domain_name = %s AND tld = %s",
				$domain_name,
				$tld
			)
		);

		return $result;
	}

	/**
	 * Mark a domain as registered.
	 *
	 * @param int      $id Domain ID.
	 * @param string   $openprovider_domain_id OpenProvider domain ID.
	 * @param string   $openprovider_order_id OpenProvider order ID.
	 * @param string|null $expires_at Expiry date.
	 * @return bool True on success.
	 */
	public function mark_registered( int $id, string $openprovider_domain_id, string $openprovider_order_id, ?string $expires_at = null ): bool {
		return $this->update(
			$id,
			array(
				'status'                 => 'registered',
				'openprovider_domain_id' => $openprovider_domain_id,
				'openprovider_order_id'  => $openprovider_order_id,
				'registered_at'          => current_time( 'mysql' ),
				'expires_at'             => $expires_at,
				'error_message'          => null,
			)
		);
	}

	/**
	 * Mark a domain as failed.
	 *
	 * @param int    $id Domain ID.
	 * @param string $error_message Error message.
	 * @return bool True on success.
	 */
	public function mark_failed( int $id, string $error_message ): bool {
		return $this->update(
			$id,
			array(
				'status'        => 'failed',
				'error_message' => $error_message,
			)
		);
	}

	/**
	 * Find a domain by OpenProvider domain ID.
	 *
	 * @param string $openprovider_domain_id OpenProvider domain ID.
	 * @return object|null Domain record or null if not found.
	 */
	public function find_by_openprovider_id( string $openprovider_domain_id ): ?object {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE openprovider_domain_id = %s",
				$openprovider_domain_id
			)
		);

		return $result;
	}

	/**
	 * Find a domain by transfer ID.
	 *
	 * @param string $transfer_id Transfer ID.
	 * @return object|null Domain record or null if not found.
	 */
	public function find_by_transfer_id( string $transfer_id ): ?object {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE transfer_id = %s",
				$transfer_id
			)
		);

		return $result;
	}

	/**
	 * Get paginated domains for a customer.
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $page Page number (1-indexed).
	 * @param int $per_page Items per page.
	 * @return array{domains: array, total: int, pages: int}
	 */
	public function find_by_customer( int $customer_id, int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$page     = max( 1, $page );
		$per_page = max( 1, $per_page );

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE customer_id = %d",
				$customer_id
			)
		);

		$domains = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE customer_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$customer_id,
				$per_page,
				( $page - 1 ) * $per_page
			)
		);

		return array(
			'domains' => (array) $domains,
			'total'   => $total,
			'pages'   => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Find registered domains expiring within a number of days.
	 *
	 * @param int $days Number of days from now.
	 * @return array Domain records.
	 */
	public function find_expiring_within( int $days ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE status = 'registered'
				AND expires_at IS NOT NULL
				AND expires_at BETWEEN %s AND %s",
				current_time( 'mysql' ),
				gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) + $days * DAY_IN_SECONDS )
			)
		);

		return (array) $results;
	}

	/**
	 * Find registered domains with auto-renewal due within a number of days.
	 *
	 * @param int $days Number of days from now.
	 * @return array Domain records.
	 */
	public function find_auto_renew_due( int $days ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE status = 'registered'
				AND auto_renew = 1
				AND expires_at IS NOT NULL
				AND expires_at <= %s",
				gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) + $days * DAY_IN_SECONDS )
			)
		);

		return (array) $results;
	}

	/**
	 * Set auto-renewal flag for a domain.
	 *
	 * @param int  $id Domain ID.
	 * @param bool $enabled Whether auto-renewal is enabled.
	 * @return bool True on success.
	 */
	public function set_auto_renew( int $id, bool $enabled ): bool {
		return $this->update( $id, array( 'auto_renew' => $enabled ? 1 : 0 ) );
	}

	/**
	 * Mark a domain as renewed with a new expiry date.
	 *
	 * @param int    $id Domain ID.
	 * @param string $expires_at New expiry date (MySQL datetime).
	 * @param int    $renewal_period Renewal period in years.
	 * @return bool True on success.
	 */
	public function mark_renewed( int $id, string $expires_at, int $renewal_period = 1 ): bool {
		return $this->update(
			$id,
			array(
				'expires_at'     => $expires_at,
				'renewal_period' => $renewal_period,
				'error_message'  => null,
			)
		);
	}

	/**
	 * Store transfer auth code and create a pending transfer record.
	 *
	 * @param int    $id Domain ID.
	 * @param string $auth_code Transfer auth/EPP code.
	 * @return bool True on success.
	 */
	public function set_transfer_auth_code( int $id, string $auth_code ): bool {
		return $this->update( $id, array( 'transfer_auth_code' => $auth_code ) );
	}

	/**
	 * Mark a transfer as initiated.
	 *
	 * @param int    $id Domain ID.
	 * @param string $transfer_id OpenProvider transfer ID.
	 * @param string $status Transfer status.
	 * @return bool True on success.
	 */
	public function mark_transfer_initiated( int $id, string $transfer_id, string $status ): bool {
		return $this->update(
			$id,
			array(
				'transfer_id'           => $transfer_id,
				'transfer_status'       => $status,
				'transfer_initiated_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Update transfer status.
	 *
	 * @param int    $id Domain ID.
	 * @param string $status Transfer status.
	 * @return bool True on success.
	 */
	public function update_transfer_status( int $id, string $status ): bool {
		return $this->update( $id, array( 'transfer_status' => $status ) );
	}

	/**
	 * Mark a transfer as completed and the domain as registered.
	 *
	 * @param int         $id Domain ID.
	 * @param string|null $expires_at New expiry date (MySQL datetime).
	 * @return bool True on success.
	 */
	public function mark_transfer_completed( int $id, ?string $expires_at = null ): bool {
		return $this->update(
			$id,
			array(
				'transfer_status'       => 'completed',
				'transfer_completed_at' => current_time( 'mysql' ),
				'status'                => 'registered',
				'registered_at'         => current_time( 'mysql' ),
				'expires_at'            => $expires_at,
			)
		);
	}

	/**
	 * Mark a transfer as failed.
	 *
	 * @param int    $id Domain ID.
	 * @param string $error_message Error message.
	 * @return bool True on success.
	 */
	public function mark_transfer_failed( int $id, string $error_message ): bool {
		return $this->update(
			$id,
			array(
				'transfer_status' => 'failed',
				'status'          => 'failed',
				'error_message'   => $error_message,
			)
		);
	}
}
