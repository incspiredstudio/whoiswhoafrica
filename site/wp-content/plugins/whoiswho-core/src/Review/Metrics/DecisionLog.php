<?php
/**
 * One row per review decision (REQ-149): who decided, what, the rejection reason code, and how long the item waited. Holds
 * staff user ids and codes only, never names or free text.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Metrics;

/**
 * Decision log.
 */
final class DecisionLog {

	public const APPROVED = 'approved';
	public const REJECTED = 'rejected';

	/**
	 * The fixed list of rejection reason codes; anything else is stored as other.
	 */
	public const REASON_CODES = array( 'duplicate', 'inaccurate', 'unverifiable', 'out_of_scope', 'policy', 'other' );

	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		profile_id bigint(20) unsigned NOT NULL,
		actor_id bigint(20) unsigned NOT NULL,
		decision varchar(10) NOT NULL,
		reason_code varchar(20) NOT NULL,
		waited_seconds int(10) unsigned NOT NULL,
		decided_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY decided_at (decided_at),
		KEY decision (decision,reason_code)';

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wiw_review_decisions';
	}

	/**
	 * Normalise a reason code.
	 *
	 * @param string $code Code from the caller.
	 */
	public static function normalise_code( string $code ): string {
		return in_array( $code, self::REASON_CODES, true ) ? $code : 'other';
	}

	/**
	 * Record a decision.
	 *
	 * @param int    $profile_id     Profile.
	 * @param int    $actor_id       Reviewer.
	 * @param string $decision       approved or rejected.
	 * @param string $reason_code    Rejection reason code; empty for an approval.
	 * @param int    $waited_seconds How long the item waited in the queue.
	 */
	public function record( int $profile_id, int $actor_id, string $decision, string $reason_code, int $waited_seconds ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			array(
				'profile_id'     => $profile_id,
				'actor_id'       => $actor_id,
				'decision'       => $decision,
				'reason_code'    => self::REJECTED === $decision ? self::normalise_code( $reason_code ) : '',
				'waited_seconds' => max( 0, $waited_seconds ),
				'decided_at'     => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * Newest decisions first.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int, array{profile_id: int, actor_id: int, decision: string, reason_code: string, waited_seconds: int, decided_at: string}>
	 */
	public function recent( int $limit = 50 ): array {
		global $wpdb;
		$found = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values prepared.
			$wpdb->prepare( 'SELECT profile_id, actor_id, decision, reason_code, waited_seconds, decided_at FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d', $limit ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values prepared.
			ARRAY_A
		);
		$out   = array();
		foreach ( $found as $row ) {
			$out[] = array(
				'profile_id'     => (int) $row['profile_id'],
				'actor_id'       => (int) $row['actor_id'],
				'decision'       => (string) $row['decision'],
				'reason_code'    => (string) $row['reason_code'],
				'waited_seconds' => (int) $row['waited_seconds'],
				'decided_at'     => (string) $row['decided_at'],
			);
		}
		return $out;
	}
}
