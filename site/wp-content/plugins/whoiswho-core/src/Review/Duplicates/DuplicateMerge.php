<?php
/**
 * Human merge of two probable duplicates (REQ-123, REQ-145, REQ-146). Nothing here runs by itself: a reviewer chooses which
 * profile survives and which of the other profile's field values come across. The merge writes the chosen fields onto the
 * survivor, one version and one audit row, sends the old address to the survivor, takes the other profile down with a reason
 * (410), and marks the pair merged. Marking a pair "not a duplicate" needs a reason.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Duplicates;

use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Directory\Url\ProfileSlugs;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Db\Transaction;
use WhoIsWho\Core\Review\StateException;
use WhoIsWho\Core\Review\StateMachine;

/**
 * Duplicate merge.
 */
final class DuplicateMerge {

	public const CAPABILITY = 'wiw_resolve_duplicates';

	/**
	 * Pairs.
	 *
	 * @var DuplicateRepository
	 */
	private DuplicateRepository $pairs;

	/**
	 * State machine.
	 *
	 * @var StateMachine
	 */
	private StateMachine $machine;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param DuplicateRepository|null $pairs   Pairs.
	 * @param StateMachine|null        $machine State machine.
	 * @param AuditLogger|null         $audit   Audit logger.
	 */
	public function __construct( ?DuplicateRepository $pairs = null, ?StateMachine $machine = null, ?AuditLogger $audit = null ) {
		$this->pairs   = $pairs ?? new DuplicateRepository();
		$this->machine = $machine ?? new StateMachine();
		$this->audit   = $audit ?? AuditLogger::for_site();
	}

	/**
	 * Merge one profile into the other.
	 *
	 * @param int      $keep   The profile that survives.
	 * @param int      $drop   The profile that is merged in and taken down.
	 * @param string[] $take   Field keys whose value comes from the profile being merged in.
	 * @param string   $reason Why; required.
	 * @param int      $actor  The reviewer.
	 * @throws StateException When refused.
	 */
	public function merge( int $keep, int $drop, array $take, string $reason, int $actor ): void {
		$this->authorise( $actor );
		if ( '' === trim( $reason ) ) {
			throw StateException::refuse( 'A reason is required.' );
		}
		$this->require_open_pair( $keep, $drop );
		$states = new StateRepository();
		$keeper = $states->get( $keep );
		$other  = $states->get( $drop );
		if ( null === $keeper || null === $other ) {
			throw StateException::refuse( 'Both profiles need a state.' );
		}
		$from = (string) $states->pub_state( $drop );
		$to   = PubState::is_public( $from ) ? PubState::UNPUBLISHED : PubState::REJECTED;
		if ( ! StateMachine::can_move( $from, $to ) ) {
			throw StateException::refuse( 'The other profile cannot be taken down from its current state.' );
		}

		$allow  = new FieldAllowlist();
		$fields = new ProfileFields( $allow );
		$old    = array();
		$new    = array();
		foreach ( array_values( array_unique( $take ) ) as $key ) {
			if ( ! $allow->is_allowed( (string) $keeper['entity_type'], $key ) || ! $allow->is_allowed( (string) $other['entity_type'], $key ) ) {
				throw StateException::refuse( 'A chosen field cannot be stored on this profile.' );
			}
			$old[ $key ] = self::value( $keep, $key );
			$new[ $key ] = self::value( $drop, $key );
		}

		Transaction::run(
			function () use ( $keep, $drop, $old, $new, $reason, $actor, $fields, $to ): void {
				foreach ( $new as $key => $value ) {
					$fields->set( $keep, $key, $value );
				}
				( new VersionRepository() )->append( $keep, $actor, 'edit', $reason, $old, $new );
				$this->redirect_slugs( $keep, $drop );
				$this->machine->transition( $drop, $to, 'Merged into profile ' . $keep . '. ' . $reason, $actor );
				$this->pairs->decide( $keep, $drop, 'merged', $actor, $reason );
				$this->audit->log(
					'duplicates.merge',
					array(
						'type' => 'profile',
						'id'   => $keep,
					),
					array( 'merged_from' => $drop ) + $old,
					array( 'fields' => array_keys( $new ) ),
					$reason
				);
			}
		);
	}

	/**
	 * Mark a pair as not a duplicate.
	 *
	 * @param int    $one    One profile.
	 * @param int    $other  The other profile.
	 * @param string $reason Why; required.
	 * @param int    $actor  The reviewer.
	 * @throws StateException When refused.
	 */
	public function dismiss( int $one, int $other, string $reason, int $actor ): void {
		$this->authorise( $actor );
		if ( '' === trim( $reason ) ) {
			throw StateException::refuse( 'A reason is required.' );
		}
		$this->require_open_pair( $one, $other );
		$this->pairs->decide( $one, $other, 'dismissed', $actor, $reason );
		$this->audit->log(
			'duplicates.dismiss',
			array(
				'type' => 'profile',
				'id'   => min( $one, $other ),
			),
			array( 'pair' => array( $one, $other ) ),
			array( 'status' => 'dismissed' ),
			$reason
		);
	}

	/**
	 * A field's current value as the screen and the version show it.
	 *
	 * @param int    $profile_id Profile.
	 * @param string $key        Field key.
	 * @return string|string[]
	 */
	public static function value( int $profile_id, string $key ) {
		if ( 'name' === $key ) {
			return (string) get_the_title( $profile_id );
		}
		$raw     = (string) get_post_meta( $profile_id, (string) FieldAllowlist::meta_key( $key ), true );
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? array_map( 'strval', $decoded ) : $raw;
	}

	/**
	 * The merged-in profile's address, and any earlier ones, now lead to the survivor; the profile gives up its slug.
	 *
	 * @param int $keep Survivor.
	 * @param int $drop Merged in.
	 */
	private function redirect_slugs( int $keep, int $drop ): void {
		$slugs = array_merge( array( (string) get_post_field( 'post_name', $drop ) ), array_map( 'strval', get_post_meta( $drop, ProfileSlugs::OLD_SLUG_META, false ) ) );
		wp_update_post(
			array(
				'ID'        => $drop,
				'post_name' => 'merged-' . $drop,
			)
		);
		delete_post_meta( $drop, ProfileSlugs::OLD_SLUG_META );
		$known = array_map( 'strval', get_post_meta( $keep, ProfileSlugs::OLD_SLUG_META, false ) );
		$own   = (string) get_post_field( 'post_name', $keep );
		foreach ( array_unique( $slugs ) as $slug ) {
			if ( '' !== $slug && $slug !== $own && ! in_array( $slug, $known, true ) ) {
				add_post_meta( $keep, ProfileSlugs::OLD_SLUG_META, $slug );
			}
		}
	}

	/**
	 * Only holders of the capability resolve duplicates.
	 *
	 * @param int $actor User.
	 * @throws StateException Without it.
	 */
	private function authorise( int $actor ): void {
		if ( ! user_can( $actor, self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw StateException::refuse( 'Not allowed.' );
		}
	}

	/**
	 * The two profiles must be an open, flagged pair of profiles.
	 *
	 * @param int $one   Profile.
	 * @param int $other Profile.
	 * @throws StateException When not.
	 */
	private function require_open_pair( int $one, int $other ): void {
		$pair = $this->pairs->get( $one, $other );
		if ( null === $pair || 'open' !== $pair['status'] ) {
			throw StateException::refuse( 'That pair is not open for review.' );
		}
		foreach ( array( $one, $other ) as $id ) {
			if ( ProfileType::POST_TYPE !== get_post_type( $id ) ) {
				throw StateException::refuse( 'Not a profile.' );
			}
		}
	}
}
