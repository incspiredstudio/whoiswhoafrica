<?php
/**
 * Hides a profile's nominations when a removal request is upheld (REQ-198, REQ-147, REQ-207). Compliance has already
 * taken the profile down and added the name to the suppression list when it fires `wiw_removal_upheld`; this listener only
 * makes sure the nominee and winner pages stop showing it. Each nomination hidden is logged and audited with ids only.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Nominee removal listener.
 */
final class NomineeRemoval {

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger $audit Audit logger.
	 */
	public function __construct( AuditLogger $audit ) {
		$this->audit = $audit;
	}

	/**
	 * Listen for the Compliance event.
	 */
	public static function hooks(): void {
		add_action(
			'wiw_removal_upheld',
			static function ( $profile_id, $item_id ): void {
				self::for_site()->hide_for_profile( (int) $profile_id, (int) $item_id );
			},
			10,
			2
		);
	}

	/**
	 * Register on the event for this instance (used by tests).
	 */
	public function register(): void {
		add_action(
			'wiw_removal_upheld',
			function ( $profile_id, $item_id ): void {
				$this->hide_for_profile( (int) $profile_id, (int) $item_id );
			},
			10,
			2
		);
	}

	/**
	 * Service for this site.
	 */
	public static function for_site(): self {
		return new self( AuditLogger::for_site() );
	}

	/**
	 * Set every live nomination of the profile to Not live.
	 *
	 * @param int $profile_id Profile that was removed.
	 * @param int $item_id    Removal item (for the audit row).
	 * @return int Nominations hidden.
	 */
	public function hide_for_profile( int $profile_id, int $item_id ): int {
		global $wpdb;
		if ( $profile_id <= 0 ) {
			return 0;
		}
		$table = AwardsTables::table( AwardsTables::NOMINATIONS );
		$ids   = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . $table . " WHERE profile_id = %d AND status = 'live'", $profile_id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		$count = 0;
		foreach ( $ids as $id ) {
			$id = (int) $id;
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'status'     => 'not_live',
					'updated_at' => gmdate( 'Y-m-d H:i:s' ),
				),
				array( 'id' => $id )
			);
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				AwardsTables::table( AwardsTables::CHANGES ),
				array(
					'nomination_id' => $id,
					'change_type'   => 'removal',
					'old_value'     => 'live',
					'new_value'     => 'not_live',
					'user_id'       => get_current_user_id(),
					'changed_at'    => gmdate( 'Y-m-d H:i:s' ),
				)
			);
			$this->audit->log(
				'nomination.removal_hidden',
				array(
					'type' => 'nomination',
					'id'   => $id,
				),
				array( 'status' => 'live' ),
				array(
					'status'  => 'not_live',
					'removal' => $item_id,
				)
			);
			do_action( 'wiw_nomination_changed', $id, 'status', 'not_live' );
			++$count;
		}//end foreach
		if ( $count > 0 ) {
			do_action( 'wiw_purge_urls', array( home_url( '/awards/nominees/' ), home_url( '/awards/winners/' ), home_url( '/awards/' ) ), false );
		}
		return $count;
	}
}
