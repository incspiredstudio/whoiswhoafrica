<?php
/**
 * Pairs of probable duplicate profiles (REQ-123, REQ-145). A pair is stored once, smaller profile id first, with a score, the
 * basis of the match and a status: open (flagged for a reviewer), merged or dismissed (a human decision). Nothing here merges
 * or changes a profile: a flagged pair keeps both profiles.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Duplicates;

/**
 * Duplicate repository.
 */
final class DuplicateRepository {

	public const TABLE = 'wiw_duplicates';

	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		profile_a bigint(20) unsigned NOT NULL,
		profile_b bigint(20) unsigned NOT NULL,
		score tinyint(3) unsigned NOT NULL,
		basis varchar(30) NOT NULL,
		status varchar(12) NOT NULL DEFAULT \'open\',
		decided_by bigint(20) unsigned NULL,
		decided_at datetime NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY pair (profile_a, profile_b),
		KEY profile_b (profile_b),
		KEY status (status)';

	public const STATUSES = array( 'open', 'merged', 'dismissed' );

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Flag a pair. A pair already on file is kept as it is, with the higher score, and a decided pair is not reopened.
	 *
	 * @param int    $one   One profile id.
	 * @param int    $other The other profile id.
	 * @param int    $score Score 0 to 100.
	 * @param string $basis Basis code: rc_number, wikidata_qid or name_org_location.
	 * @return bool True when a new pair was stored.
	 */
	public function flag( int $one, int $other, int $score, string $basis ): bool {
		global $wpdb;
		if ( $one === $other || $one < 1 || $other < 1 ) {
			return false;
		}
		list( $a, $b ) = $one < $other ? array( $one, $other ) : array( $other, $one );
		$score         = max( 0, min( 100, $score ) );
		$existing      = $this->get( $a, $b );
		if ( null !== $existing ) {
			if ( $score > (int) $existing['score'] && 'open' === $existing['status'] ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					self::table(),
					array(
						'score' => $score,
						'basis' => $basis,
					),
					array( 'id' => (int) $existing['id'] )
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
			return false;
		}
		return false !== $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'profile_a'  => $a,
				'profile_b'  => $b,
				'score'      => $score,
				'basis'      => $basis,
				'status'     => 'open',
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * One pair.
	 *
	 * @param int $one   Profile id.
	 * @param int $other Profile id.
	 * @return array<string, mixed>|null
	 */
	public function get( int $one, int $other ): ?array {
		global $wpdb;
		list( $a, $b ) = $one < $other ? array( $one, $other ) : array( $other, $one );
		$row           = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE profile_a = %d AND profile_b = %d', $a, $b ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Pairs with a status, highest score first.
	 *
	 * @param string $status Status.
	 * @param int    $limit  Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function with_status( string $status = 'open', int $limit = 100 ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE status = %s ORDER BY score DESC, id ASC LIMIT %d', $status, max( 1, $limit ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	/**
	 * Whether a profile is in an open pair (the review queue shows a "probable duplicate" flag).
	 *
	 * @param int $profile_id Profile id.
	 */
	public function is_flagged( int $profile_id ): bool {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE status = 'open' AND ( profile_a = %d OR profile_b = %d )", $profile_id, $profile_id ) ) > 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	/**
	 * Partners of a profile in open pairs.
	 *
	 * @param int $profile_id Profile id.
	 * @return int[]
	 */
	public function partners( int $profile_id ): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT profile_a, profile_b FROM ' . self::table() . " WHERE status = 'open' AND ( profile_a = %d OR profile_b = %d )", $profile_id, $profile_id ), ARRAY_N ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		$out  = array();
		foreach ( $rows as $row ) {
			$out[] = (int) $row[0] === $profile_id ? (int) $row[1] : (int) $row[0];
		}
		return $out;
	}

	/**
	 * Record a human decision on a pair.
	 *
	 * @param int    $one     Profile id.
	 * @param int    $other   Profile id.
	 * @param string $status  merged or dismissed.
	 * @param int    $user_id Reviewer.
	 * @throws \InvalidArgumentException For an unknown status.
	 */
	public function decide( int $one, int $other, string $status, int $user_id ): void {
		global $wpdb;
		if ( ! in_array( $status, array( 'merged', 'dismissed' ), true ) ) {
			throw new \InvalidArgumentException( 'A duplicate decision is merged or dismissed.' );
		}
		list( $a, $b ) = $one < $other ? array( $one, $other ) : array( $other, $one );
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'status'     => $status,
				'decided_by' => $user_id,
				'decided_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array(
				'profile_a' => $a,
				'profile_b' => $b,
			)
		);
	}
}
