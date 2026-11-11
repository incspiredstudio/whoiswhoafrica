<?php
/**
 * Daily log files (wiw-YYYY-MM-DD.log) in one folder, one JSON line per event. Files older than the retention period are removed
 * by purge(), which the recurring retention job calls.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Log;

/**
 * File sink.
 */
final class FileSink implements LogSink {

	public const RETENTION_DAYS = 30;

	/**
	 * Folder.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Clock returning Unix time.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param string        $dir   Folder (created on first write).
	 * @param callable|null $clock Returns Unix time.
	 */
	public function __construct( string $dir, ?callable $clock = null ) {
		$this->dir   = rtrim( $dir, '/\\' );
		$this->clock = $clock ?? static fn (): int => time();
	}

	/**
	 * Sink for this site: WIW_LOG_DIR, or a folder in wp-content.
	 */
	public static function for_site(): self {
		$dir = defined( 'WIW_LOG_DIR' ) ? (string) constant( 'WIW_LOG_DIR' ) : WP_CONTENT_DIR . '/wiw-logs';
		return new self( $dir );
	}

	/**
	 * Write a line.
	 *
	 * @param string $line Line.
	 */
	public function write( string $line ): void {
		if ( ! is_dir( $this->dir ) && ! @mkdir( $this->dir, 0750, true ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
			return;
		}
		$name = 'wiw-' . gmdate( 'Y-m-d', (int) ( $this->clock )() ) . '.log';
		@file_put_contents( $this->dir . '/' . $name, $line . "\n", FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
		$guard = $this->dir . '/.htaccess';
		if ( ! file_exists( $guard ) ) {
			@file_put_contents( $guard, "Require all denied\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
		}
	}

	/**
	 * Delete log files older than the retention period.
	 *
	 * @param int $days Days to keep.
	 * @return int Files removed.
	 */
	public function purge( int $days = self::RETENTION_DAYS ): int {
		$cutoff  = gmdate( 'Y-m-d', (int) ( $this->clock )() - $days * 86400 );
		$removed = 0;
		$files   = glob( $this->dir . '/wiw-*.log' );
		foreach ( false === $files ? array() : $files as $file ) {
			if ( 1 === preg_match( '/wiw-(\d{4}-\d{2}-\d{2})\.log$/', $file, $m ) && $m[1] < $cutoff ) {
				if ( @unlink( $file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
					++$removed;
				}
			}
		}
		return $removed;
	}
}
