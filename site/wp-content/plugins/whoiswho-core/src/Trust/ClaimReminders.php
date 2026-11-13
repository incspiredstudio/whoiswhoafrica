<?php
/**
 * Claim reminders and dashboard counts (REQ-256, REQ-075). A claim has five days to be decided. Holders of wiw_review_claims get
 * one email on day 3, one on day 4, then one a day once the claim is overdue; deciding a claim stops them. The job is idempotent:
 * a claim is reminded only when its latest milestone has not already been reminded. "Due soon" runs from day 3 to the decide-by
 * time. Recipients are resolved when the email is queued, so a user who loses the capability stops receiving reminders.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust;

use WhoIsWho\Core\Platform\Dashboard\TileRegistry;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;

/**
 * Claim reminders.
 */
final class ClaimReminders {

	public const JOB            = 'claims.remind';
	public const INTERVAL       = 3600;
	public const FIRST_DAY      = 3;
	public const SECOND_DAY     = 4;
	public const DAY_IN_SECONDS = 86400;

	/**
	 * Claims.
	 *
	 * @var ClaimRepository
	 */
	private ClaimRepository $claims;

	/**
	 * Notifier.
	 *
	 * @var Notifier
	 */
	private Notifier $notifier;

	/**
	 * Constructor.
	 *
	 * @param ClaimRepository $claims   Claims.
	 * @param Notifier        $notifier Notifier.
	 */
	public function __construct( ClaimRepository $claims, Notifier $notifier ) {
		$this->claims   = $claims;
		$this->notifier = $notifier;
	}

	/**
	 * Register the recurring job and the dashboard tiles.
	 */
	public static function register(): void {
		JobRunner::for_site()->recurring(
			self::JOB,
			self::INTERVAL,
			static function (): void {
				( new self( new ClaimRepository(), Notifier::for_site() ) )->run();
			}
		);
		add_action(
			'wiw_dashboard_tiles',
			static function ( TileRegistry $tiles ): void {
				$claims = new ClaimRepository();
				foreach ( array(
					'claims_open'     => array( __( 'Claims open', 'whoiswho' ), 'open' ),
					'claims_due_soon' => array( __( 'Claims due soon', 'whoiswho' ), 'due_soon' ),
					'claims_overdue'  => array( __( 'Claims overdue', 'whoiswho' ), 'overdue' ),
				) as $id => $tile ) {
					if ( isset( $tiles->all()[ $id ] ) ) {
						continue;
					}
					$key = $tile[1];
					$tiles->register(
						$id,
						$tile[0],
						ClaimDecision::CAPABILITY,
						TileRegistry::COUNT,
						static fn (): int => ( new self( $claims, Notifier::for_site() ) )->counts()[ $key ]
					);
				}
			}
		);
	}

	/**
	 * Open claims.
	 *
	 * @return Claim[]
	 */
	private function open_claims(): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . ClaimRepository::table() . ' WHERE status = %s ORDER BY received_at', Claim::NEW ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return array_map( array( Claim::class, 'from_row' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Unix time of a UTC "Y-m-d H:i:s" string.
	 *
	 * @param string $stamp Time.
	 */
	private static function at( string $stamp ): int {
		return (int) strtotime( $stamp . ' UTC' );
	}

	/**
	 * Whether a claim is due a reminder now.
	 *
	 * @param Claim $claim Claim.
	 * @param int   $now   Unix time.
	 */
	public static function is_due( Claim $claim, int $now ): bool {
		if ( ! $claim->is_open() ) {
			return false;
		}
		$last     = null === $claim->last_reminder_at ? 0 : self::at( $claim->last_reminder_at );
		$received = self::at( $claim->received_at );
		$decide   = self::at( $claim->decide_by_at );
		if ( $now >= $decide ) {
			return $last < $decide || $now - $last >= self::DAY_IN_SECONDS;
		}
		$second = $received + self::SECOND_DAY * self::DAY_IN_SECONDS;
		if ( $now >= $second ) {
			return $last < $second;
		}
		$first = $received + self::FIRST_DAY * self::DAY_IN_SECONDS;
		return $now >= $first && $last < $first;
	}

	/**
	 * Send the reminders that are due and record them on the claims.
	 *
	 * @param \DateTimeImmutable|null $at Time to run at (now by default).
	 * @return int Number of claims reminded.
	 */
	public function run( ?\DateTimeImmutable $at = null ): int {
		$at   = $at ?? new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$sent = 0;
		foreach ( $this->open_claims() as $claim ) {
			if ( ! self::is_due( $claim, $at->getTimestamp() ) ) {
				continue;
			}
			$this->notifier->send(
				Events::CLAIM_REMINDER,
				'',
				array(
					'reference'  => $claim->reference,
					'decide_by'  => $claim->decide_by_at,
					'profile_id' => $claim->profile_id,
				)
			);
			$this->claims->mark_reminded( $claim->id, $at );
			++$sent;
		}
		return $sent;
	}

	/**
	 * Counts for the dashboard: open claims, those due soon (day 3 up to the decide-by time) and those overdue.
	 *
	 * @param \DateTimeImmutable|null $at Time (now by default).
	 * @return array{open: int, due_soon: int, overdue: int}
	 */
	public function counts( ?\DateTimeImmutable $at = null ): array {
		$now    = ( $at ?? new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )->getTimestamp();
		$counts = array(
			'open'     => 0,
			'due_soon' => 0,
			'overdue'  => 0,
		);
		foreach ( $this->open_claims() as $claim ) {
			++$counts['open'];
			if ( $now >= self::at( $claim->decide_by_at ) ) {
				++$counts['overdue'];
			} elseif ( $now >= self::at( $claim->received_at ) + self::FIRST_DAY * self::DAY_IN_SECONDS ) {
				++$counts['due_soon'];
			}
		}
		return $counts;
	}
}
