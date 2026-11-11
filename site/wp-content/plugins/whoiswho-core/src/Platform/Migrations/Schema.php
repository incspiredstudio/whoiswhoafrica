<?php
/**
 * Table creation helper: dbDelta with utf8mb4 and InnoDB.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Migrations;

/**
 * Schema helpers for migrations.
 */
final class Schema {

	/**
	 * Create or update a table. Pass the columns and keys only, for example "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, PRIMARY KEY  (id)".
	 * The prefix, charset and engine are added here. Table and column names are fixed in code, never from input.
	 *
	 * @param string $name       Table name without the prefix.
	 * @param string $definition Column and key definitions.
	 * @return string The full table name.
	 */
	public static function create_table( string $name, string $definition ): string {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = $wpdb->prefix . $name;
		$charset = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
		dbDelta( "CREATE TABLE {$table} ( {$definition} ) ENGINE=InnoDB {$charset};" );
		return $table;
	}
}
