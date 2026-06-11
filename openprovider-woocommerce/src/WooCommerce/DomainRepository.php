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
			'order_id'           => 0,
			'order_item_id'      => null,
			'customer_id'        => null,
			'domain_name'        => '',
			'tld'                => '',
			'registration_period' => 1,
			'status'             => 'pending',
			'registered_at'      => null,
			'expires_at'         => null,
			'error_message'      => null,
		);

		$data = wp_parse_args( $data, $defaults );

		$wpdb->insert(
			$this->table,
			array(
				'order_id'           => $data['order_id'],
				'order_item_id'      => $data['order_item_id'],
				'customer_id'        => $data['customer_id'],
				'domain_name'        => $data['domain_name'],
				'tld'                => $data['tld'],
				'registration_period' => $data['registration_period'],
				'status'             => $data['status'],
				'registered_at'      => $data['registered_at'],
				'expires_at'         => $data['expires_at'],
				'error_message'      => $data['error_message'],
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
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
				'status'              => 'registered',
				'openprovider_domain_id' => $openprovider_domain_id,
				'openprovider_order_id' => $openprovider_order_id,
				'registered_at'       => current_time( 'mysql' ),
				'expires_at'          => $expires_at,
				'error_message'       => null,
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
				'status'         => 'failed',
				'error_message'  => $error_message,
			)
		);
	}
}
