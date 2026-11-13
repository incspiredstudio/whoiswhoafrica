<?php
/**
 * Database triggers that make the audit table refuse UPDATE and DELETE (REQ-051). Installed by a migration step where the
 * database account holds the TRIGGER privilege; a host that refuses them does not fail the migration, and the outcome is
 * recorded so the Audit log screen can say "triggers off". The hash chain and the nightly export protect the log either way.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Audit;

/**
 * Audit trigger installer.
 */
final class AuditGuard {

	/**
	 * Option: "0" when both triggers exist, "1" when they could not be installed.
	 */
	public const OPTION_MISSING = 'wiw_audit_triggers_missing';

	public const TRIGGERS = array(
		'wiw_audit_no_update' => 'UPDATE',
		'wiw_audit_no_delete' => 'DELETE',
	);

	/**
	 * Install (or reinstall) both triggers on an audit table.
	 *
	 * @param string $table Table name without the prefix.
	 * @return bool True when both exist afterwards.
	 */
	public static function install( string $table = 'wiw_audit_log' ): bool {
		global $wpdb;
		$full = $wpdb->prefix . $table;
		$ok   = true;
		$prev = $wpdb->suppress_errors( true );
		foreach ( self::TRIGGERS as $suffix => $event ) {
			$name = self::trigger_name( $table, $suffix );
			$wpdb->query( "DROP TRIGGER IF EXISTS `{$name}`" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed names built from the table prefix.
			$res = $wpdb->query( "CREATE TRIGGER `{$name}` BEFORE {$event} ON `{$full}` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'the audit log is append-only'" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed names; no input.
			$ok  = $ok && false !== $res;
		}
		$wpdb->suppress_errors( $prev );
		$ok = $ok && self::installed( $table );
		update_option( self::OPTION_MISSING, $ok ? '0' : '1', false );
		return $ok;
	}

	/**
	 * Whether both triggers exist on the table.
	 *
	 * @param string $table Table name without the prefix.
	 */
	public static function installed( string $table = 'wiw_audit_log' ): bool {
		global $wpdb;
		$found = 0;
		foreach ( array_keys( self::TRIGGERS ) as $suffix ) {
			$found += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = %s', self::trigger_name( $table, $suffix ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return count( self::TRIGGERS ) === $found;
	}

	/**
	 * "on" when the triggers were installed, otherwise "off" (also before the migration has run).
	 */
	public static function status(): string {
		return '0' === (string) get_option( self::OPTION_MISSING, '' ) ? 'on' : 'off';
	}

	/**
	 * A trigger name: the table's prefixed name plus the suffix, so probe tables never share a trigger with the real log.
	 *
	 * @param string $table  Table name without the prefix.
	 * @param string $suffix Trigger suffix.
	 */
	private static function trigger_name( string $table, string $suffix ): string {
		global $wpdb;
		return 'wiw_audit_log' === $table ? $wpdb->prefix . $suffix : $wpdb->prefix . $table . '_' . $suffix;
	}
}
