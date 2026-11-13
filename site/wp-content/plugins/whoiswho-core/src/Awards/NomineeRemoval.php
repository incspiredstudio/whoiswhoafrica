<?php
/**
 * An upheld removal request from a nominee (REQ-198, REQ-147, REQ-207). The Compliance module fires `wiw_removal_upheld` once
 * staff have judged a removal valid; this listener hides every nomination of that person or organisation (the linked profile, or
 * the typed name on a public-form nomination that has no profile) and makes sure the name is on the suppression list, so a new
 * nomination of the same name shows the duplicate warning and a hidden one can never be set Live again. Everything is audited
 * with the support item id; the requester's details are never read here.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Nominee removal.
 */
final class NomineeRemoval {

	public const ACTION = 'wiw_removal_upheld';

	/**
	 * Nomination service.
	 *
	 * @var NominationService
	 */
	private NominationService $nominations;

	/**
	 * Suppression list.
	 *
	 * @var Suppression
	 */
	private Suppression $suppression;

	/**
	 * Constructor.
	 *
	 * @param NominationService $nominations Nominations.
	 * @param Suppression       $suppression Suppression list.
	 */
	public function __construct( NominationService $nominations, Suppression $suppression ) {
		$this->nominations = $nominations;
		$this->suppression = $suppression;
	}

	/**
	 * Listener for this site.
	 */
	public static function hooks(): void {
		add_action(
			self::ACTION,
			static function ( $support_item_id, $profile_id, $name, $entity_type, $external_ids = array() ): void {
				$audit = AuditLogger::for_site();
				( new self( new NominationService( $audit ), new Suppression( $audit ) ) )->handle( (int) $support_item_id, (int) $profile_id, (string) $name, (string) $entity_type, is_array( $external_ids ) ? $external_ids : array() );
			},
			10,
			5
		);
	}

	/**
	 * Handle an upheld removal.
	 *
	 * @param int                  $support_item_id The upheld removal item.
	 * @param int                  $profile_id      The profile the request is about, or 0.
	 * @param string               $name            The person or organisation.
	 * @param string               $entity_type     Entity type.
	 * @param array<string,string> $external_ids    Source => id.
	 * @return int How many nominations were hidden.
	 */
	public function handle( int $support_item_id, int $profile_id, string $name, string $entity_type, array $external_ids = array() ): int {
		if ( null === $this->suppression->match( $name, $entity_type, $external_ids ) ) {
			$this->suppression->add_from_removal( $name, $entity_type, $external_ids, $support_item_id );
		}
		$hidden = 0;
		foreach ( $this->live_nominations_of( $profile_id, $name ) as $id ) {
			if ( $this->nominations->hide_for_removal( $id, $support_item_id ) ) {
				++$hidden;
			}
		}
		return $hidden;
	}

	/**
	 * Live nominations of a profile, or of a typed name that has no profile.
	 *
	 * @param int    $profile_id Profile, or 0.
	 * @param string $name       Name.
	 * @return int[]
	 */
	private function live_nominations_of( int $profile_id, string $name ): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, profile_id, nominee_name FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ) . " WHERE status = 'live' AND ( profile_id = %d OR profile_id IS NULL OR profile_id = 0 )", $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		$key  = Suppression::normalise_name( $name );
		$ids  = array();
		foreach ( $rows as $row ) {
			$by_profile = $profile_id > 0 && (int) $row['profile_id'] === $profile_id;
			$by_name    = '' !== $key && (int) $row['profile_id'] < 1 && Suppression::normalise_name( (string) $row['nominee_name'] ) === $key;
			if ( $by_profile || $by_name ) {
				$ids[] = (int) $row['id'];
			}
		}
		return $ids;
	}
}
