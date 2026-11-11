<?php
/**
 * Installs database triggers that make the versions table append-only: any UPDATE or DELETE is rejected by the database
 * itself, whoever issues it (REQ-125). Used by the migration. Reports failure instead of hiding it, so a host that refuses
 * triggers is visible rather than silently unprotected.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\State;

/**
 * Trigger installer.
 */
final class VersionGuard {

	/**
	 * Option set to "1" when the triggers could not be installed.
	 */
	public const OPTION_MISSING = 'wiw_versions_triggers_missing';

	/**
	 * Install (or reinstall) both triggers.
	 *
	 * @return bool True when both exist afterwards.
	 */
	public static function install(): bool {
		global $wpdb;
		$table = VersionRepository::table();
		$ok    = true;
		foreach ( array(
			Tables::VERSIONS_TRIGGERS[0] => 'UPDATE',
			Tables::VERSIONS_TRIGGERS[1] => 'DELETE',
		) as $suffix => $event ) {
			$name = $wpdb->prefix . $suffix;
			$wpdb->query( "DROP TRIGGER IF EXISTS `{$name}`" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed names built from the table prefix.
			$res = $wpdb->query( "CREATE TRIGGER `{$name}` BEFORE {$event} ON `{$table}` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wiw_profile_versions is append-only'" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed names; no input.
			$ok  = $ok && false !== $res;
		}
		update_option( self::OPTION_MISSING, $ok && self::installed() ? '0' : '1', false );
		return $ok && self::installed();
	}

	/**
	 * Whether both triggers exist.
	 */
	public static function installed(): bool {
		global $wpdb;
		$found = 0;
		foreach ( Tables::VERSIONS_TRIGGERS as $suffix ) {
			$found += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = %s', $wpdb->prefix . $suffix ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return count( Tables::VERSIONS_TRIGGERS ) === $found;
	}
}
