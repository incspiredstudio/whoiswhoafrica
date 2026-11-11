<?php
/**
 * Runs a callable in one database transaction. When a transaction is already open (a caller's, or the test suite's), it uses a
 * savepoint instead of starting a new transaction, so it never commits a caller's work. Any throwable rolls everything back.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Db;

/**
 * Transaction helper.
 */
final class Transaction {

	/**
	 * Nesting depth, for unique savepoint names.
	 *
	 * @var int
	 */
	private static int $depth = 0;

	/**
	 * Run a callable atomically.
	 *
	 * @template T
	 * @param callable():T $work Work to do.
	 * @return T
	 * @throws \Throwable Whatever the work throws, after rolling back.
	 */
	public static function run( callable $work ) {
		global $wpdb;
		$nested = self::$depth > 0 || self::in_transaction();
		$name   = 'wiw_sp_' . self::$depth;
		if ( $nested ) {
			$wpdb->query( "SAVEPOINT {$name}" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- generated name.
		} else {
			$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB
		}
		++self::$depth;
		try {
			$result = $work();
		} catch ( \Throwable $e ) {
			--self::$depth;
			$wpdb->query( $nested ? "ROLLBACK TO SAVEPOINT {$name}" : 'ROLLBACK' ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- generated name.
			throw $e;
		}
		--self::$depth;
		$wpdb->query( $nested ? "RELEASE SAVEPOINT {$name}" : 'COMMIT' ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- generated name.
		return $result;
	}

	/**
	 * Whether the connection is inside a transaction (MariaDB reports it; other servers report nothing, meaning no).
	 */
	private static function in_transaction(): bool {
		global $wpdb;
		$suppress = $wpdb->suppress_errors( true );
		$value    = $wpdb->get_var( 'SELECT @@in_transaction' ); // phpcs:ignore WordPress.DB
		$wpdb->suppress_errors( $suppress );
		return '1' === (string) $value;
	}
}
