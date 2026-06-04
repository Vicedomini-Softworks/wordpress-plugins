<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Secrets_Manager_Activator {

	public static function activate(): void {
		self::create_tables();
	}

	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'vsecrets_secrets';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			title VARCHAR(255) NOT NULL DEFAULT '',
			provider VARCHAR(20) NOT NULL DEFAULT 'db',
			value LONGTEXT,
			encryption_method VARCHAR(50) DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			last_rotated DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY name (name)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		add_option( 'vs_secrets_manager_db_version', VSECRETS_MANAGER_DB_VERSION );
	}
}
