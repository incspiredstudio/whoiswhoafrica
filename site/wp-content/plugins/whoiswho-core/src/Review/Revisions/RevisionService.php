<?php
/**
 * Decides a pending revision of a claimed profile (REQ-253, REQ-125). An edit by the assigned user of a claimed profile is stored
 * as a pending row in the append-only versions table (see ProfileEditor). A Reviewer or Administrator holding wiw_review_records
 * approves it, which applies the new values, queues a purge and tells the assigned user, or rejects it with a reason. The person
 * who made the change cannot decide it. A decision is a new row that points back at the pending one, so the previous version and
 * the proposal both stay in the history. Every decision is audited. Domain service; screens call it.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Revisions;

use WhoIsWho\Core\Directory\Edit\SideFields;
use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Purge\PurgeQueue;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Review\StateException;

/**
 * Revision service.
 */
final class RevisionService {

	public const CAPABILITY = 'wiw_review_records';

	/**
	 * Versions.
	 *
	 * @var VersionRepository
	 */
	private VersionRepository $versions;

	/**
	 * Tells the author the outcome: fn( int $user_id, string $outcome, string $reason, int $profile_id ): void.
	 *
	 * @var callable
	 */
	private $notify;

	/**
	 * Purge queue, or null for the site's own.
	 *
	 * @var PurgeQueue|null
	 */
	private ?PurgeQueue $purge;

	/**
	 * Constructor.
	 *
	 * @param VersionRepository|null $versions Versions.
	 * @param callable|null          $notify   Notification callback; the default sends the revision_decision email.
	 * @param PurgeQueue|null        $purge    Purge queue; the default is the site's.
	 */
	public function __construct( ?VersionRepository $versions = null, ?callable $notify = null, ?PurgeQueue $purge = null ) {
		$this->versions = $versions ?? new VersionRepository();
		$this->notify   = $notify ?? array( self::class, 'send_email' );
		$this->purge    = $purge;
	}

	/**
	 * Pending revisions awaiting a decision, oldest first.
	 *
	 * @param int $profile_id Profile.
	 * @return array<int, array<string, mixed>>
	 */
	public function pending( int $profile_id ): array {
		return array_values(
			array_filter(
				$this->versions->pending_for_profile( $profile_id ),
				static fn ( array $row ): bool => 'revision' === $row['created_via']
			)
		);
	}

	/**
	 * Approve: apply the proposed values, record the decision, purge and notify.
	 *
	 * @param int $version_id The pending revision row.
	 * @param int $actor_id   The deciding user.
	 * @return int The decision row id.
	 * @throws StateException When refused.
	 */
	public function approve( int $version_id, int $actor_id ): int {
		$row = $this->decidable( $version_id, $actor_id );
		$id  = (int) $row['profile_id'];
		$new = (array) json_decode( (string) $row['new_value'], true );
		$old = (array) json_decode( (string) $row['old_value'], true );

		$fields = new ProfileFields( new FieldAllowlist() );
		foreach ( $new as $key => $value ) {
			$key = (string) $key;
			if ( SideFields::is_side( $key ) ) {
				SideFields::apply( $id, $key, $value );
				continue;
			}
			if ( 0 === strpos( $key, 'terms:' ) ) {
				$taxonomy = substr( $key, 6 );
				if ( in_array( $taxonomy, Taxonomies::all(), true ) ) {
					wp_set_object_terms( $id, array_map( 'intval', (array) $value ), $taxonomy );
				}
				continue;
			}
			$fields->set( $id, $key, null === $value ? '' : $value );
		}
		$decision = $this->versions->append( $id, $actor_id, 'decision', (string) $row['reason'], $old, $new, 'approved', $version_id );
		AuditLogger::for_site()->log(
			'profile.revision_approved',
			array(
				'type' => 'profile',
				'id'   => $id,
			),
			self::without_contact_values( $old ),
			self::without_contact_values( $new ),
			(string) $row['reason']
		);
		$state = ( new StateRepository() )->get( $id );
		if ( null !== $state && PubState::is_public( (string) $state['pub_state'] ) ) {
			( $this->purge ?? PurgeQueue::for_site() )->queue( array( $id ), 'revision' );
		}
		( $this->notify )( (int) $row['author_id'], 'approved', '', $id );
		return $decision;
	}

	/**
	 * A value map with the contact details reduced to a marker, for the audit trail: it names the detail that changed, never its value.
	 *
	 * @param array<string, mixed> $values Values.
	 * @return array<string, mixed>
	 */
	private static function without_contact_values( array $values ): array {
		foreach ( array_keys( SideFields::CONTACT ) as $key ) {
			if ( array_key_exists( $key, $values ) ) {
				$values[ $key ] = empty( $values[ $key ] ) ? '' : '(changed)';
			}
		}
		return $values;
	}

	/**
	 * Reject with a reason: nothing is applied.
	 *
	 * @param int    $version_id The pending revision row.
	 * @param string $reason     Why; required.
	 * @param int    $actor_id   The deciding user.
	 * @return int The decision row id.
	 * @throws StateException When refused.
	 */
	public function reject( int $version_id, string $reason, int $actor_id ): int {
		$reason = trim( $reason );
		if ( '' === $reason ) {
			throw StateException::refuse( 'A reason is required to reject a change.' );
		}
		$row      = $this->decidable( $version_id, $actor_id );
		$id       = (int) $row['profile_id'];
		$old      = (array) json_decode( (string) $row['old_value'], true );
		$new      = (array) json_decode( (string) $row['new_value'], true );
		$decision = $this->versions->append( $id, $actor_id, 'decision', $reason, $old, $new, 'rejected', $version_id );
		AuditLogger::for_site()->log(
			'profile.revision_rejected',
			array(
				'type' => 'profile',
				'id'   => $id,
			),
			self::without_contact_values( $new ),
			self::without_contact_values( $old ),
			$reason
		);
		( $this->notify )( (int) $row['author_id'], 'rejected', $reason, $id );
		return $decision;
	}

	/**
	 * The pending row, after the checks every decision needs.
	 *
	 * @param int $version_id Row id.
	 * @param int $actor_id   Deciding user.
	 * @return array<string, mixed>
	 * @throws StateException When refused.
	 */
	private function decidable( int $version_id, int $actor_id ): array {
		if ( ! user_can( $actor_id, self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw StateException::refuse( 'You are not allowed to review records.' );
		}
		global $wpdb;
		$table = VersionRepository::table();
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE id = %d', $version_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		if ( ! is_array( $row ) || 'revision' !== $row['created_via'] || 'pending' !== $row['approval_status'] || ProfileType::POST_TYPE !== get_post_type( (int) $row['profile_id'] ) ) {
			throw StateException::refuse( 'That change is not waiting for review.' );
		}
		$decided = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $table . ' WHERE revision_of = %d', $version_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		if ( $decided > 0 ) {
			throw StateException::refuse( 'That change has already been decided.' );
		}
		if ( (int) $row['author_id'] === $actor_id ) {
			throw StateException::refuse( 'The person who made a change cannot approve or reject it.' );
		}
		return $row;
	}

	/**
	 * Default notification: the revision_decision email to the author, with the outcome and a short reason.
	 *
	 * @param int    $user_id    Author.
	 * @param string $outcome    approved or rejected.
	 * @param string $reason     Reason, empty when approved.
	 * @param int    $profile_id Profile.
	 */
	public static function send_email( int $user_id, string $outcome, string $reason, int $profile_id ): void {
		$user = get_userdata( $user_id );
		if ( false === $user || '' === (string) $user->user_email ) {
			return;
		}
		Notifier::for_site()->send(
			Events::REVISION_DECISION,
			(string) $user->user_email,
			array(
				'profile_id' => $profile_id,
				'outcome'    => $outcome,
				'reason'     => $reason,
			)
		);
	}
}
