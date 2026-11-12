<?php
/**
 * Nominations run by staff: add a profile, set stage and status, remove (REQ-267, REQ-202, REQ-034, REQ-226, REQ-198). A stage is
 * Nominated or Winner and a status is Live or Not live; no score is ever stored. Each change is written to the nomination change
 * log and to the audit log with the acting staff member. This service never writes a profile's pub_state (only the Review state
 * machine does) and never touches commercial data (REQ-199).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Nomination service.
 */
final class NominationService {

	public const CAP = 'wiw_manage_awards';

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Returns a profile's publication state, or null when it has none.
	 *
	 * @var callable
	 */
	private $profile_state;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger   $audit         Audit logger.
	 * @param callable|null $profile_state Profile id to publication state (the Directory read API by default).
	 */
	public function __construct( AuditLogger $audit, ?callable $profile_state = null ) {
		$this->audit         = $audit;
		$this->profile_state = $profile_state ?? static fn ( int $id ): ?string => ( new StateRepository() )->pub_state( $id );
	}

	/**
	 * Service for this site.
	 */
	public static function for_site(): self {
		return new self( AuditLogger::for_site() );
	}

	/**
	 * Add an existing profile to the nominations. It starts Nominated and Not live, source staff.
	 *
	 * @param int    $profile_id    Existing profile (reviewed or pending review).
	 * @param int    $category_id   Category.
	 * @param string $note          Optional note.
	 * @param string $contact_email Optional staff-only Contact email.
	 * @return int Nomination id.
	 * @throws \DomainException    When not allowed or the input is invalid.
	 * @throws \RuntimeException   When the row cannot be saved.
	 */
	public function add_profile( int $profile_id, int $category_id, string $note = '', string $contact_email = '' ): int {
		$this->require_cap();
		$state = ( $this->profile_state )( $profile_id );
		if ( ! in_array( $state, array( PubState::PUBLISHED_REVIEWED, PubState::PUBLISHED_PENDING ), true ) ) {
			throw new \DomainException( 'Choose an existing reviewed or pending-review profile.' );
		}
		global $wpdb;
		$archived = $wpdb->get_var( $wpdb->prepare( 'SELECT archived FROM ' . AwardsTables::table( AwardsTables::CATEGORIES ) . ' WHERE id = %d', $category_id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		if ( null === $archived || (int) $archived ) {
			throw new \DomainException( 'Choose a current award category.' );
		}
		$contact_email = trim( $contact_email );
		if ( '' !== $contact_email && ! is_email( $contact_email ) ) {
			throw new \DomainException( 'The Contact email is not a valid address.' );
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::NOMINATIONS ),
			array(
				'category_id'   => $category_id,
				'profile_id'    => $profile_id,
				'contact_email' => '' === $contact_email ? null : $contact_email,
				'evidence'      => '' === trim( $note ) ? null : trim( wp_strip_all_tags( $note ) ),
				'stage'         => 'nominated',
				'status'        => 'not_live',
				'source'        => 'staff',
				'created_at'    => $now,
				'updated_at'    => $now,
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( $id < 1 ) {
			throw new \RuntimeException( 'The nomination could not be saved.' );
		}
		// The audit entry holds ids only: no note and no address.
		$this->audit->log(
			'nomination.add',
			$this->subject( $id ),
			null,
			array(
				'profile'  => $profile_id,
				'category' => $category_id,
				'source'   => 'staff',
			)
		);
		return $id;
	}

	/**
	 * Set the stage: Nominated or Winner.
	 *
	 * @param int    $id    Nomination.
	 * @param string $stage New stage.
	 * @throws \DomainException When not allowed, unknown or invalid.
	 */
	public function set_stage( int $id, string $stage ): void {
		$this->change( $id, 'stage', $stage, AwardsTables::STAGES );
	}

	/**
	 * Set the status: Live or Not live. Live needs a linked, reviewed profile (REQ-226).
	 *
	 * @param int    $id     Nomination.
	 * @param string $status New status.
	 * @throws \DomainException When not allowed, unknown, invalid, or the profile is not reviewed.
	 */
	public function set_status( int $id, string $status ): void {
		$this->change( $id, 'status', $status, AwardsTables::STATUSES );
	}

	/**
	 * The token a caller must present to remove a nomination (confirmation step).
	 *
	 * @param int $id Nomination.
	 */
	public function confirmation_token( int $id ): string {
		return hash_hmac( 'sha256', 'nomination-remove|' . $id . '|' . get_current_user_id(), wp_salt( 'auth' ) );
	}

	/**
	 * Remove a nomination after confirmation. The change log keeps the removal and the audit log records it.
	 *
	 * @param int    $id    Nomination.
	 * @param string $token Token from confirmation_token().
	 * @throws \DomainException When not allowed, unknown or not confirmed.
	 */
	public function remove( int $id, string $token ): void {
		$this->require_cap();
		$row = $this->require_row( $id );
		if ( '' === $token || ! hash_equals( $this->confirmation_token( $id ), $token ) ) {
			throw new \DomainException( 'Confirm the removal first.' );
		}
		global $wpdb;
		$wpdb->delete( AwardsTables::table( AwardsTables::NOMINATIONS ), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->record( $id, 'removal', $row['stage'] . '/' . $row['status'], null );
		$this->audit->log(
			'nomination.remove',
			$this->subject( $id ),
			array(
				'stage'   => $row['stage'],
				'status'  => $row['status'],
				'profile' => (int) $row['profile_id'],
			),
			null
		);
		$this->purge();
	}

	/**
	 * A nomination row.
	 *
	 * @param int $id Nomination.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Change history of a nomination, oldest first.
	 *
	 * @param int $id Nomination.
	 * @return array<int, array<string, mixed>>
	 */
	public function history( int $id ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT change_type, old_value, new_value, user_id, changed_at FROM ' . AwardsTables::table( AwardsTables::CHANGES ) . ' WHERE nomination_id = %d ORDER BY id', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	/**
	 * Apply a stage or status change.
	 *
	 * @param int      $id      Nomination.
	 * @param string   $column  stage or status.
	 * @param string   $value   New value.
	 * @param string[] $allowed Allowed values.
	 * @throws \DomainException When refused.
	 */
	private function change( int $id, string $column, string $value, array $allowed ): void {
		$this->require_cap();
		$row = $this->require_row( $id );
		if ( ! in_array( $value, $allowed, true ) ) {
			throw new \DomainException( 'That value is not allowed.' );
		}
		if ( 'status' === $column && 'live' === $value && PubState::PUBLISHED_REVIEWED !== ( $row['profile_id'] ? ( $this->profile_state )( (int) $row['profile_id'] ) : null ) ) {
			throw new \DomainException( 'A nomination cannot go live until its profile has been reviewed.' );
		}
		$old = (string) $row[ $column ];
		if ( $old === $value ) {
			return;
		}
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::NOMINATIONS ),
			array(
				$column      => $value,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id )
		);
		$this->record( $id, $column, $old, $value );
		$this->audit->log( 'nomination.' . $column, $this->subject( $id ), array( $column => $old ), array( $column => $value ) );
		/**
		 * A nomination's stage or status changed; the nominee and winners pages follow.
		 *
		 * @param int    $id     Nomination.
		 * @param string $column stage or status.
		 * @param string $value  New value.
		 */
		do_action( 'wiw_nomination_changed', $id, $column, $value );
		$this->purge();
	}

	/**
	 * Write one line of the change log.
	 *
	 * @param int         $id     Nomination.
	 * @param string      $type   Change type.
	 * @param string|null $old    Old value.
	 * @param string|null $after  New value.
	 */
	private function record( int $id, string $type, ?string $old, ?string $after ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::CHANGES ),
			array(
				'nomination_id' => $id,
				'change_type'   => $type,
				'old_value'     => $old,
				'new_value'     => $after,
				'user_id'       => get_current_user_id(),
				'changed_at'    => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * Row or refusal.
	 *
	 * @param int $id Nomination.
	 * @return array<string, mixed>
	 * @throws \DomainException When unknown.
	 */
	private function require_row( int $id ): array {
		$row = $this->find( $id );
		if ( null === $row ) {
			throw new \DomainException( 'That nomination does not exist.' );
		}
		return $row;
	}

	/**
	 * Audit subject.
	 *
	 * @param int $id Nomination.
	 * @return array{type: string, id: int}
	 */
	private function subject( int $id ): array {
		return array(
			'type' => 'nomination',
			'id'   => $id,
		);
	}

	/**
	 * Deny by default.
	 *
	 * @throws \DomainException When the user may not manage awards.
	 */
	private function require_cap(): void {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'You are not allowed to manage nominations.' );
		}
	}

	/**
	 * Purge the pages that show nominees and winners.
	 */
	private function purge(): void {
		do_action( 'wiw_purge_urls', array( home_url( '/awards/nominees/' ), home_url( '/awards/winners/' ), home_url( '/awards/' ) ), false );
	}
}
