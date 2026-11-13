<?php
/**
 * Storage of profile claims in wiw_profile_claims (REQ-251, REQ-252, REQ-256). The table is not called wiw_claims: that is the
 * import fact-claims table. A claim moves only from new to approved or rejected, and only through decide(); this class does not
 * write the profile's claimed flag (the review flow in the Trust module does that when it approves).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust;

/**
 * Claim repository.
 */
class ClaimRepository {

	public const TABLE           = 'wiw_profile_claims';
	public const SEQUENCE_OPTION = 'wiw_claim_sequence';
	public const DECIDE_DAYS     = 5;

	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		reference varchar(16) NOT NULL,
		profile_id bigint(20) unsigned NOT NULL,
		relationship varchar(20) NOT NULL,
		name varchar(190) NOT NULL DEFAULT \'\',
		role varchar(120) NOT NULL DEFAULT \'\',
		work_email varchar(190) NOT NULL DEFAULT \'\',
		phone varchar(40) NOT NULL DEFAULT \'\',
		evidence text NULL,
		consent_at datetime NULL,
		status varchar(16) NOT NULL DEFAULT \'new\',
		received_at datetime NOT NULL,
		decide_by_at datetime NOT NULL,
		last_reminder_at datetime NULL,
		decided_by bigint(20) unsigned NULL,
		decided_at datetime NULL,
		reason text NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY reference (reference),
		KEY profile (profile_id),
		KEY status_due (status, decide_by_at)';

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Store a new claim. Input is already validated by the claim form's validator.
	 *
	 * @param array<string, mixed>    $input    profile_id, relationship, name, role, work_email, phone, evidence, consent (bool).
	 * @param \DateTimeImmutable|null $received Arrival time; now when null.
	 * @throws \InvalidArgumentException For a missing profile, a bad relationship or no consent.
	 * @throws \RuntimeException When the claim cannot be stored.
	 */
	public function create( array $input, ?\DateTimeImmutable $received = null ): Claim {
		global $wpdb;
		$relationship = (string) ( $input['relationship'] ?? '' );
		if ( (int) ( $input['profile_id'] ?? 0 ) < 1 || ! in_array( $relationship, Claim::RELATIONSHIPS, true ) || empty( $input['consent'] ) ) {
			throw new \InvalidArgumentException( 'A claim needs a profile, a relationship and consent.' );
		}
		$utc      = new \DateTimeZone( 'UTC' );
		$received = ( $received ?? new \DateTimeImmutable( 'now', $utc ) )->setTimezone( $utc );
		$row      = array(
			'reference'    => $this->next_reference(),
			'profile_id'   => (int) $input['profile_id'],
			'relationship' => $relationship,
			'name'         => (string) ( $input['name'] ?? '' ),
			'role'         => (string) ( $input['role'] ?? '' ),
			'work_email'   => (string) ( $input['work_email'] ?? '' ),
			'phone'        => (string) ( $input['phone'] ?? '' ),
			'evidence'     => (string) ( $input['evidence'] ?? '' ),
			'consent_at'   => $received->format( 'Y-m-d H:i:s' ),
			'status'       => Claim::NEW,
			'received_at'  => $received->format( 'Y-m-d H:i:s' ),
			'decide_by_at' => $received->modify( '+' . self::DECIDE_DAYS . ' days' )->format( 'Y-m-d H:i:s' ),
		);
		if ( false === $wpdb->insert( self::table(), $row ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			throw new \RuntimeException( 'The claim could not be stored.' );
		}
		$claim = $this->find( (int) $wpdb->insert_id );
		if ( null === $claim ) {
			throw new \RuntimeException( 'The claim could not be read back.' );
		}
		return $claim;
	}

	/**
	 * One claim by id.
	 *
	 * @param int $id Id.
	 */
	public function find( int $id ): ?Claim {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? Claim::from_row( $row ) : null;
	}

	/**
	 * One claim by reference.
	 *
	 * @param string $reference Reference.
	 */
	public function find_by_reference( string $reference ): ?Claim {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE reference = %s', $reference ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? Claim::from_row( $row ) : null;
	}

	/**
	 * Record a decision. Only a new claim can be decided, and only once; a rejection needs a reason.
	 *
	 * @param int                     $id      Claim id.
	 * @param string                  $status  approved or rejected.
	 * @param int                     $user_id Deciding user.
	 * @param string                  $reason  Reason (required when rejecting).
	 * @param \DateTimeImmutable|null $at      Moment; now when null.
	 * @throws \DomainException When the change is not allowed.
	 */
	public function decide( int $id, string $status, int $user_id, string $reason = '', ?\DateTimeImmutable $at = null ): void {
		global $wpdb;
		if ( ! in_array( $status, array( Claim::APPROVED, Claim::REJECTED ), true ) ) {
			throw new \DomainException( 'A claim is decided as approved or rejected.' );
		}
		if ( Claim::REJECTED === $status && '' === trim( $reason ) ) {
			throw new \DomainException( 'A rejection needs a reason.' );
		}
		$stamp   = ( $at ?? new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$changed = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'UPDATE ' . self::table() . ' SET status = %s, decided_by = %d, decided_at = %s, reason = %s WHERE id = %d AND status = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
				$status,
				$user_id,
				$stamp,
				$reason,
				$id,
				Claim::NEW
			)
		);
		if ( 1 !== $changed ) {
			throw new \DomainException( 'Only a new claim can be decided.' );
		}
	}

	/**
	 * Claims, newest first.
	 *
	 * @param string $status new, approved or rejected; empty for all.
	 * @param int    $limit  Maximum rows.
	 * @return Claim[]
	 */
	public function list_by_status( string $status = '', int $limit = 200 ): array {
		global $wpdb;
		$sql  = 'SELECT * FROM ' . self::table();
		$args = array();
		if ( '' !== $status ) {
			$sql   .= ' WHERE status = %s';
			$args[] = $status;
		}
		$sql   .= ' ORDER BY received_at DESC, id DESC LIMIT %d';
		$args[] = max( 1, $limit );
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name and fixed clauses.
		return array_map( array( Claim::class, 'from_row' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Record that a reminder went out.
	 *
	 * @param int                     $id Claim id.
	 * @param \DateTimeImmutable|null $at Moment.
	 */
	public function mark_reminded( int $id, ?\DateTimeImmutable $at = null ): void {
		global $wpdb;
		$stamp = ( $at ?? new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$wpdb->update( self::table(), array( 'last_reminder_at' => $stamp ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * The next reference in sequence (CLM-0001 style). The counter moves in one atomic statement.
	 */
	private function next_reference(): string {
		global $wpdb;
		add_option( self::SEQUENCE_OPTION, 0, '', false );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(option_value + 1) WHERE option_name = %s", self::SEQUENCE_OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- core table name.
		$number = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_delete( self::SEQUENCE_OPTION, 'options' );
		return 'CLM-' . str_pad( (string) $number, 4, '0', STR_PAD_LEFT );
	}
}
