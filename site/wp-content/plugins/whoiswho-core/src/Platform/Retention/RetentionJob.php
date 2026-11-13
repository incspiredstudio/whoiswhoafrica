<?php
/**
 * The scheduled data-retention job (REQ-063). It deletes or anonymises personal data once it is older than the period set for
 * its retention class in the PII catalogue. Periods are not decided yet (D-051), so none are built in: until an Administrator
 * stores a number of days per class in the option, the job does nothing. A dry run counts what would change without changing it.
 *
 * Classes handled here: consent_life (consents revoked longer ago than the period are deleted) and log_life (email event rows
 * are deleted; the address WordPress stored with an old comment is cleared). Other modules add rules for their own tables
 * through the wiw_retention_rules filter: each rule is a callable( int $cutoff_unix, bool $dry_run ): int returning the number
 * of rows changed (or that would be).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Retention;

/**
 * Retention job.
 */
final class RetentionJob {

	public const OPTION_PERIODS = 'wiw_retention_periods';
	public const JOB_NAME       = 'retention.run';

	/**
	 * Classes that are kept on purpose or purged by another mechanism, with the reason.
	 *
	 * @var array<string, string>
	 */
	private const NOT_SCHEDULED = array(
		'account_life'    => 'Lives as long as the account; removed by erasure or deactivation.',
		'permanent'       => 'Kept on purpose (public record, audit trail).',
		'profile_life'    => 'Removed with the profile or when it leaves the public states.',
		'link_lifetime'   => 'Purged by the links.purge_spent job.',
		'case_life'       => 'Ends with the case; module rules arrive through wiw_retention_rules.',
		'support_request' => 'Ends with the request; module rules arrive through wiw_retention_rules.',
		'import_evidence' => 'Kept with the import batch; module rules arrive through wiw_retention_rules.',
	);

	/**
	 * Clock returning Unix time.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param callable|null $clock Returns Unix time.
	 */
	public function __construct( ?callable $clock = null ) {
		$this->clock = $clock ?? static fn (): int => time();
	}

	/**
	 * Every retention class this job knows how to treat or has a stated reason for leaving.
	 *
	 * @return string[]
	 */
	public static function known_classes(): array {
		return array_merge( array( 'consent_life', 'log_life' ), array_keys( self::NOT_SCHEDULED ) );
	}

	/**
	 * Days stored for each class; classes without a positive number are skipped.
	 *
	 * @return array<string, int>
	 */
	public function periods(): array {
		$stored = get_option( self::OPTION_PERIODS, array() );
		$out    = array();
		foreach ( is_array( $stored ) ? $stored : array() as $class => $days ) {
			if ( is_int( $days ) || ( is_string( $days ) && ctype_digit( $days ) ) ) {
				if ( (int) $days > 0 ) {
					$out[ (string) $class ] = (int) $days;
				}
			}
		}
		return $out;
	}

	/**
	 * Run every rule whose class has a period.
	 *
	 * @param bool $dry_run Count only.
	 * @return array<string, int> Rows changed (or that would change) by class.
	 */
	public function run( bool $dry_run = false ): array {
		$rules   = $this->rules();
		$report  = array();
		$periods = $this->periods();
		foreach ( $rules as $class => $rule ) {
			if ( ! isset( $periods[ $class ] ) ) {
				continue;
			}
			$cutoff           = ( $this->clock )() - $periods[ $class ] * 86400;
			$report[ $class ] = (int) $rule( $cutoff, $dry_run );
		}
		return $report;
	}

	/**
	 * Rules by class, built in plus those added by modules.
	 *
	 * @return array<string, callable>
	 */
	private function rules(): array {
		$rules = array(
			'consent_life' => array( $this, 'consents' ),
			'log_life'     => array( $this, 'logs' ),
		);
		/**
		 * Add retention rules: class => callable( int $cutoff_unix, bool $dry_run ): int.
		 *
		 * @param array<string, callable> $rules Rules by retention class.
		 */
		return apply_filters( 'wiw_retention_rules', $rules );
	}

	/**
	 * Delete consent rows revoked before the cutoff.
	 *
	 * @param int  $cutoff  Unix time.
	 * @param bool $dry_run Count only.
	 */
	private function consents( int $cutoff, bool $dry_run ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'wiw_consents';
		$when  = gmdate( 'Y-m-d H:i:s', $cutoff );
		if ( $dry_run ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE revoked_at IS NOT NULL AND revoked_at < %s", $when ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table name is built from the prefix.
		}
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE revoked_at IS NOT NULL AND revoked_at < %s", $when ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table name is built from the prefix.
	}

	/**
	 * Delete old email event rows and clear the address stored with old comments.
	 *
	 * @param int  $cutoff  Unix time.
	 * @param bool $dry_run Count only.
	 */
	private function logs( int $cutoff, bool $dry_run ): int {
		global $wpdb;
		$when     = gmdate( 'Y-m-d H:i:s', $cutoff );
		$events   = $wpdb->prefix . 'wiw_email_events';
		$comments = $wpdb->comments;
		if ( $dry_run ) {
			$a = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events} WHERE created_at < %s", $when ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table name is built from the prefix.
			$b = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$comments} WHERE comment_author_IP <> '' AND comment_date_gmt < %s", $when ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- core table.
			return $a + $b;
		}
		$a   = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$events} WHERE created_at < %s", $when ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table name is built from the prefix.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$comments} WHERE comment_author_IP <> '' AND comment_date_gmt < %s", $when ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- core table.
		$b   = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$comments} SET comment_author_IP = '' WHERE comment_author_IP <> '' AND comment_date_gmt < %s", $when ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- core table.
		if ( array() !== $ids ) {
			clean_comment_cache( array_map( 'intval', $ids ) );
		}
		return $a + $b;
	}
}
