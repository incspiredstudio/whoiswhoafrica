<?php
/**
 * The retention job (REQ-063, REQ-233): once a day it deletes expired signed links, removes old log rows and anonymises nominations
 * past their period, then fires wiw_retention_run so other modules (the newsletter, comments) apply their own periods. Every
 * period comes from config/retention.php. A dry run counts and changes nothing, and a second run finds nothing more to do. What it
 * did is written to the audit log as counts only: no name, address or id of a person. The suppression list and the audit log are
 * never purged, whatever the config says.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Retention;

use WhoIsWho\Core\Awards\NominationPrivacy;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Jobs\JobRunner;

/**
 * Retention job.
 */
final class RetentionJob {

	public const JOB = 'wiw_retention';

	/**
	 * The only tables the job deletes from, without the site prefix. Deny by default: the suppression list and the audit log are not
	 * here and can never be added by config.
	 */
	public const PURGEABLE = array( 'wiw_signed_links', 'wiw_email_events', 'wiw_purge_log' );

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Retention config.
	 *
	 * @var array<string, mixed>
	 */
	private array $config;

	/**
	 * Clock: seconds since the epoch.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger               $audit  Audit logger.
	 * @param array<string, mixed>|null $config Retention config; config/retention.php by default.
	 * @param callable|null             $clock  Clock, for tests.
	 */
	public function __construct( AuditLogger $audit, ?array $config = null, ?callable $clock = null ) {
		$this->audit  = $audit;
		$this->config = $config ?? require dirname( __DIR__, 3 ) . '/config/retention.php';
		$this->clock  = $clock ?? static fn (): int => time();
	}

	/**
	 * The job for this site.
	 */
	public static function for_site(): self {
		return new self( AuditLogger::for_site() );
	}

	/**
	 * Register the daily job.
	 */
	public static function register(): void {
		JobRunner::for_site()->recurring(
			self::JOB,
			86400,
			static function (): void {
				self::for_site()->run();
			}
		);
	}

	/**
	 * Whether the job may delete from a table (without the site prefix): only the listed ones.
	 *
	 * @param string $table Table name without prefix.
	 */
	public static function may_purge( string $table ): bool {
		return in_array( $table, self::PURGEABLE, true );
	}

	/**
	 * Run once.
	 *
	 * @param bool $dry_run Count only; change nothing.
	 * @return array{dry_run: bool, signed_links: int, logs: int, nominations: int}
	 */
	public function run( bool $dry_run = false ): array {
		$result = array(
			'dry_run'      => $dry_run,
			'signed_links' => $this->signed_links( $dry_run ),
			'logs'         => $this->logs( $dry_run ),
			'nominations'  => $this->nominations( $dry_run ),
		);
		if ( ! $dry_run ) {
			$this->apply_module_periods();
			/**
			 * Modules apply their own retention here (newsletter, comments). Periods come from config/retention.php through the
			 * filters set above; each module logs counts only.
			 */
			do_action( 'wiw_retention_run' );
			$this->audit->log( 'retention.run', $this->subject(), null, $result );
		}
		return $result;
	}

	/**
	 * Delete expired and spent signed links: the short-lived email checks after their short period, the rest after the long one.
	 *
	 * @param bool $dry_run Count only.
	 */
	private function signed_links( bool $dry_run ): int {
		$purposes = array_values( array_filter( (array) ( $this->config['signed_link_check_purposes'] ?? array() ), 'is_string' ) );
		$total    = 0;
		if ( array() !== $purposes && $this->days( 'signed_links_check_days' ) > 0 ) {
			$marks  = implode( ',', array_fill( 0, count( $purposes ), '%s' ) );
			$before = $this->before( $this->days( 'signed_links_check_days' ) );
			$total += $this->delete_where( 'wiw_signed_links', "purpose IN ($marks) AND (expires_at < %s OR (used_at IS NOT NULL AND used_at < %s) OR (revoked_at IS NOT NULL AND revoked_at < %s))", array_merge( $purposes, array( $before, $before, $before ) ), $dry_run );
		}
		if ( $this->days( 'signed_links_days' ) > 0 ) {
			$before = $this->before( $this->days( 'signed_links_days' ) );
			$total += $this->delete_where( 'wiw_signed_links', '(expires_at < %s OR (used_at IS NOT NULL AND used_at < %s) OR (revoked_at IS NOT NULL AND revoked_at < %s))', array( $before, $before, $before ), $dry_run );
		}
		return $total;
	}

	/**
	 * Delete log rows past their period. The logs hold ids and counts only.
	 *
	 * @param bool $dry_run Count only.
	 */
	private function logs( bool $dry_run ): int {
		if ( $this->days( 'logs_days' ) < 1 ) {
			return 0;
		}
		$before = $this->before( $this->days( 'logs_days' ) );
		return $this->delete_where( 'wiw_email_events', 'created_at < %s', array( $before ), $dry_run )
			+ $this->delete_where( 'wiw_purge_log', 'created_at < %s', array( $before ), $dry_run );
	}

	/**
	 * Anonymise nominations past their period.
	 *
	 * @param bool $dry_run Count only.
	 */
	private function nominations( bool $dry_run ): int {
		$days = $this->days( 'nominations_days' );
		return $days < 1 ? 0 : ( new NominationPrivacy( $this->audit ) )->retain( $days, $dry_run );
	}

	/**
	 * Hand the configured periods to the modules that apply their own.
	 */
	private function apply_module_periods(): void {
		$nominations = $this->days( 'nominations_days' );
		if ( $nominations > 0 ) {
			add_filter( 'wiw_retention_days_nominations', static fn (): int => $nominations );
		}
		$newsletter = $this->days( 'newsletter_unsubscribed_days' );
		if ( $newsletter > 0 ) {
			add_filter( 'wiw_newsletter_unsubscribed_retention_days', static fn (): int => $newsletter );
		}
	}

	/**
	 * Delete rows (or count them) from a table the job may purge.
	 *
	 * @param string            $table   Table name without prefix.
	 * @param string            $where   Fixed condition with placeholders.
	 * @param array<int, mixed> $args    Values.
	 * @param bool              $dry_run Count only.
	 * @return int Rows deleted or that would be.
	 * @throws \LogicException When the table is not one the job may purge.
	 */
	private function delete_where( string $table, string $where, array $args, bool $dry_run ): int {
		if ( ! self::may_purge( $table ) ) {
			throw new \LogicException( 'The retention job may not purge that table.' );
		}
		global $wpdb;
		$full = $wpdb->prefix . $table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a fixed table name, checked against the allowlist above.
		if ( null === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $full ) ) ) ) {
			return 0;
		}
		if ( $dry_run ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$full} WHERE {$where}", $args ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table and condition, values prepared.
		}
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$full} WHERE {$where}", $args ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table and condition, values prepared.
	}

	/**
	 * A period in days from the config; zero when it is not a number (nothing purged for that class).
	 *
	 * @param string $key Config key.
	 */
	private function days( string $key ): int {
		$value = $this->config[ $key ] ?? null;
		return is_int( $value ) && $value > 0 ? $value : 0;
	}

	/**
	 * The UTC time this many days ago.
	 *
	 * @param int $days Days.
	 */
	private function before( int $days ): string {
		return gmdate( 'Y-m-d H:i:s', ( $this->clock )() - $days * 86400 );
	}

	/**
	 * Audit subject.
	 *
	 * @return array{type: string, id: string}
	 */
	private function subject(): array {
		return array(
			'type' => 'job',
			'id'   => self::JOB,
		);
	}
}
