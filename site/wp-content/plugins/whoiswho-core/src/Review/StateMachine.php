<?php
/**
 * The publication state machine: the only code that writes a profile's pub_state and its post status (REQ-210, REQ-208,
 * REQ-209, REQ-123). Every change runs in one transaction: state row, post status and audit row are written together or not
 * at all. Only the two Published states use the public post status; the other four use wiw_hidden.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\StateWriter;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Db\Transaction;

/**
 * State machine.
 */
final class StateMachine {

	/**
	 * Allowed moves: from => to[].
	 */
	public const TRANSITIONS = array(
		PubState::IMPORTED_NOT_PUBLIC => array( PubState::PUBLISHED_PENDING, PubState::PUBLISHED_REVIEWED, PubState::REJECTED, PubState::REMOVED_SUPPRESSED ),
		PubState::PUBLISHED_PENDING   => array( PubState::PUBLISHED_REVIEWED, PubState::REJECTED, PubState::UNPUBLISHED, PubState::REMOVED_SUPPRESSED ),
		PubState::PUBLISHED_REVIEWED  => array( PubState::UNPUBLISHED, PubState::REMOVED_SUPPRESSED ),
		PubState::UNPUBLISHED         => array( PubState::PUBLISHED_PENDING, PubState::PUBLISHED_REVIEWED, PubState::REMOVED_SUPPRESSED ),
		PubState::REJECTED            => array( PubState::IMPORTED_NOT_PUBLIC, PubState::REMOVED_SUPPRESSED ),
		PubState::REMOVED_SUPPRESSED  => array(),
	);

	/**
	 * States a profile may start in.
	 */
	public const INITIAL = array( PubState::IMPORTED_NOT_PUBLIC, PubState::PUBLISHED_PENDING );

	/**
	 * Target states that need a reason.
	 */
	public const REASON_REQUIRED = array( PubState::UNPUBLISHED, PubState::REJECTED, PubState::REMOVED_SUPPRESSED );

	/**
	 * Depth of machine writes in progress; the status guard lets writes through only while this is above zero.
	 *
	 * @var int
	 */
	private static int $writing = 0;

	/**
	 * State writer.
	 *
	 * @var StateWriter
	 */
	private StateWriter $writer;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param StateWriter|null $writer Writer.
	 * @param AuditLogger|null $audit  Logger.
	 */
	public function __construct( ?StateWriter $writer = null, ?AuditLogger $audit = null ) {
		$this->writer = $writer ?? new StateWriter();
		$this->audit  = $audit ?? AuditLogger::for_site();
	}

	/**
	 * Whether the machine is writing right now.
	 */
	public static function is_writing(): bool {
		return self::$writing > 0;
	}

	/**
	 * The post status a state maps to.
	 *
	 * @param string $state State.
	 */
	public static function post_status_for( string $state ): string {
		return PubState::is_public( $state ) ? 'publish' : ProfileType::STATUS_HIDDEN;
	}

	/**
	 * Whether a move is allowed.
	 *
	 * @param string $from From state.
	 * @param string $to   To state.
	 */
	public static function can_move( string $from, string $to ): bool {
		return in_array( $to, self::TRANSITIONS[ $from ] ?? array(), true );
	}

	/**
	 * Give a new profile its state row and post status (import and customer-created profiles).
	 *
	 * @param int                  $profile_id Profile post id.
	 * @param string               $state      Initial state.
	 * @param array<string, mixed> $values     State columns: entity_type (required), tier, origin, source, import_batch, content_hash, importer_id.
	 * @param string               $reason     Why, for the audit row.
	 * @throws StateException When refused.
	 */
	public function enter( int $profile_id, string $state, array $values, string $reason = '' ): void {
		if ( ! in_array( $state, self::INITIAL, true ) ) {
			throw StateException::refuse( 'A profile cannot start in that state.' );
		}
		if ( ProfileType::POST_TYPE !== get_post_type( $profile_id ) ) {
			throw StateException::refuse( 'Not a profile.' );
		}
		if ( null !== $this->writer->get( $profile_id ) ) {
			throw StateException::refuse( 'The profile already has a state.' );
		}
		$this->apply(
			$profile_id,
			null,
			$state,
			$reason,
			function () use ( $profile_id, $state, $values ): void {
				$this->writer->insert( $profile_id, $state, $values );
			}
		);
	}

	/**
	 * Move a profile to another state.
	 *
	 * @param int         $profile_id Profile post id.
	 * @param string      $to         Target state.
	 * @param string      $reason     Why. Required for unpublish, reject, remove and any bulk action.
	 * @param int|null    $actor_id   Reviewer or acting user, recorded on review moves.
	 * @param string|null $batch      Batch reference when part of a bulk action (a reason is then always required).
	 * @throws StateException When refused.
	 */
	public function transition( int $profile_id, string $to, string $reason = '', ?int $actor_id = null, ?string $batch = null ): void {
		$from = $this->writer->pub_state( $profile_id );
		if ( null === $from ) {
			throw StateException::refuse( 'The profile has no state.' );
		}
		if ( ! PubState::is_valid( $to ) || ! self::can_move( $from, $to ) ) {
			throw StateException::refuse( 'That state change is not allowed.' );
		}
		if ( ( in_array( $to, self::REASON_REQUIRED, true ) || null !== $batch ) && '' === trim( $reason ) ) {
			throw StateException::refuse( 'A reason is required.' );
		}
		$this->apply(
			$profile_id,
			$from,
			$to,
			$reason,
			function () use ( $profile_id, $to, $actor_id ): void {
				$this->writer->set_pub_state( $profile_id, $to );
				if ( null !== $actor_id && in_array( $to, array( PubState::PUBLISHED_REVIEWED, PubState::REJECTED ), true ) ) {
					$this->writer->update(
						$profile_id,
						array(
							'reviewer_id' => $actor_id,
							'reviewed_at' => gmdate( 'Y-m-d H:i:s' ),
						)
					);
				}
			},
			$batch
		);
	}

	/**
	 * Write state, post status and audit in one transaction, then announce the change.
	 *
	 * @param int         $profile_id Profile.
	 * @param string|null $from       Previous state.
	 * @param string      $to         New state.
	 * @param string      $reason     Reason.
	 * @param callable    $write_state Writes the state row.
	 * @param string|null $batch      Batch reference.
	 * @throws \Throwable When the write fails; nothing is changed then.
	 */
	private function apply( int $profile_id, ?string $from, string $to, string $reason, callable $write_state, ?string $batch = null ): void {
		++self::$writing;
		try {
			Transaction::run(
				function () use ( $profile_id, $from, $to, $reason, $write_state, $batch ): void {
					$write_state();
					$updated = wp_update_post(
						array(
							'ID'          => $profile_id,
							'post_status' => self::post_status_for( $to ),
						),
						true
					);
					if ( is_wp_error( $updated ) ) {
						throw StateException::refuse( 'The post status could not be set.' );
					}
					/**
					 * Fires inside the transaction after the state and status are written. Hook the search row here; a throw rolls back.
					 *
					 * @param int         $profile_id Profile.
					 * @param string|null $from       Previous state.
					 * @param string      $to         New state.
					 */
					do_action( 'wiw_profile_state_in_transaction', $profile_id, $from, $to );
					$this->audit->log(
						'profile.state_change',
						array(
							'type' => 'profile',
							'id'   => $profile_id,
						),
						array( 'pub_state' => $from ),
						array( 'pub_state' => $to ),
						$reason,
						$batch
					);
				}
			);
		} finally {
			--self::$writing;
		}//end try
		/**
		 * Fires after the change is committed. Queue cache purges here.
		 *
		 * @param int         $profile_id Profile.
		 * @param string|null $from       Previous state.
		 * @param string      $to         New state.
		 */
		do_action( 'wiw_profile_state_changed', $profile_id, $from, $to );
	}
}
