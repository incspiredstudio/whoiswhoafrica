<?php
/**
 * The nightly audit export (REQ-051): new audit rows are written, in order, to a signed JSON-lines file in a private folder the
 * owner can download or sync off the host. The file carries exactly the table's columns and nothing else; it is never emailed.
 * Each file has a sidecar .sig holding an HMAC of its contents, so a copy can be checked later. The last run and the last
 * exported row id are recorded.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Audit;

use WhoIsWho\Core\Platform\Jobs\JobRunner;

/**
 * Audit export.
 */
final class AuditExport {

	public const JOB             = 'audit.export';
	public const OPTION_LAST_RUN = 'wiw_audit_export_last_run';
	public const OPTION_LAST_ID  = 'wiw_audit_export_last_id';
	public const BATCH           = 1000;
	public const COLUMNS         = array( 'id', 'at', 'actor_id', 'action', 'subject_type', 'subject_id', 'old_value', 'new_value', 'reason', 'batch_ref', 'prev_hash', 'hash' );

	/**
	 * Folder.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Audit table name without the prefix.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Signing key; empty means a key derived from the site's salts.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Constructor.
	 *
	 * @param string $dir   Private folder for the files.
	 * @param string $table Audit table name without the prefix.
	 * @param string $key   Signing key.
	 */
	public function __construct( string $dir, string $table = 'wiw_audit_log', string $key = '' ) {
		$this->dir   = rtrim( $dir, '/\\' );
		$this->table = $table;
		$this->key   = $key;
	}

	/**
	 * Export for this site: a folder under the uploads directory that the web server is told not to serve.
	 */
	public static function for_site(): self {
		$uploads = wp_upload_dir( null, false );
		return new self( (string) $uploads['basedir'] . '/wiw-private/audit' );
	}

	/**
	 * Register the nightly job.
	 *
	 * @param JobRunner $runner Job runner.
	 */
	public function register( JobRunner $runner ): void {
		$runner->recurring( self::JOB, 86400, array( $this, 'run' ) );
	}

	/**
	 * Export the rows added since the last export.
	 *
	 * @return int Rows exported.
	 */
	public function run(): int {
		$this->prepare_folder();
		$conn  = new WpdbConnection( $this->table );
		$after = (int) get_option( self::OPTION_LAST_ID, 0 );
		$lines = array();
		$first = 0;
		$last  = $after;
		do {
			$rows = $conn->rows( $last, self::BATCH );
			$full = self::BATCH === count( $rows );
			foreach ( $rows as $row ) {
				$out = array();
				foreach ( self::COLUMNS as $column ) {
					$out[ $column ] = $row[ $column ] ?? null;
				}
				$lines[] = (string) wp_json_encode( $out );
				$first   = 0 === $first ? (int) $row['id'] : $first;
				$last    = (int) $row['id'];
			}
		} while ( $full );

		if ( array() !== $lines ) {
			$path = $this->dir . '/audit-' . $first . '-' . $last . '.jsonl';
			$body = implode( "\n", $lines ) . "\n";
			file_put_contents( $path, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $path . '.sig', $this->sign( $body ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			update_option( self::OPTION_LAST_ID, $last, false );
		}
		update_option( self::OPTION_LAST_RUN, time(), false );
		return count( $lines );
	}

	/**
	 * Whether a file still matches its signature.
	 *
	 * @param string $path File path.
	 */
	public function verify_file( string $path ): bool {
		if ( ! is_readable( $path ) || ! is_readable( $path . '.sig' ) ) {
			return false;
		}
		return hash_equals( trim( (string) file_get_contents( $path . '.sig' ) ), $this->sign( (string) file_get_contents( $path ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Unix time of the last run, or 0.
	 */
	public static function last_run(): int {
		return (int) get_option( self::OPTION_LAST_RUN, 0 );
	}

	/**
	 * HMAC of a body.
	 *
	 * @param string $body File contents.
	 */
	private function sign( string $body ): string {
		return hash_hmac( 'sha256', $body, '' !== $this->key ? $this->key : wp_salt( 'secure_auth' ) );
	}

	/**
	 * Create the folder and close it to the web.
	 */
	private function prepare_folder(): void {
		if ( ! is_dir( $this->dir ) ) {
			wp_mkdir_p( $this->dir );
		}
		if ( ! file_exists( $this->dir . '/.htaccess' ) ) {
			file_put_contents( $this->dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( ! file_exists( $this->dir . '/index.php' ) ) {
			file_put_contents( $this->dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}
}
