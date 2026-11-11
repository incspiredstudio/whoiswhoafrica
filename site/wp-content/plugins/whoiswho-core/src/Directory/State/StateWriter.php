<?php
/**
 * The state writer: the only code that inserts a profile's state row or changes its pub_state (REQ-210, REQ-123).
 *
 * ONLY Review\StateMachine may use this class. Nothing else may reference it, and an architecture test fails the build if
 * another file does. Everything else reads through StateRepository and changes the other columns through its update().
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\State;

/**
 * State writer.
 */
final class StateWriter extends StateRepository {

	/**
	 * Create the row for a new profile.
	 *
	 * @param int                  $profile_id Profile post id.
	 * @param string               $pub_state  Initial state.
	 * @param array<string, mixed> $values     Other columns: entity_type (required), tier, origin, source, import_batch, content_hash, importer_id.
	 * @throws \InvalidArgumentException On an unknown state, a missing entity type or a duplicate row.
	 */
	public function insert( int $profile_id, string $pub_state, array $values ): void {
		global $wpdb;
		if ( ! PubState::is_valid( $pub_state ) ) {
			throw new \InvalidArgumentException( 'Unknown publication state.' );
		}
		if ( empty( $values['entity_type'] ) ) {
			throw new \InvalidArgumentException( 'An entity type is required.' );
		}
		$this->validate( $values );
		if ( null !== $this->get( $profile_id ) ) {
			throw new \InvalidArgumentException( 'A state row already exists for this profile.' );
		}
		$row = array_intersect_key( $values, array_flip( self::WRITABLE ) );
		$row = array_merge(
			$row,
			array(
				'profile_id' => $profile_id,
				'pub_state'  => $pub_state,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		$ok  = $wpdb->insert( self::table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( false === $ok ) {
			throw new \InvalidArgumentException( 'A state row already exists for this profile.' );
		}
	}

	/**
	 * Set the publication state of an existing row. Review\StateMachine calls this inside its transaction.
	 *
	 * @param int    $profile_id Profile post id.
	 * @param string $pub_state  New state.
	 * @throws \InvalidArgumentException On an unknown state or a missing row.
	 */
	public function set_pub_state( int $profile_id, string $pub_state ): void {
		global $wpdb;
		if ( ! PubState::is_valid( $pub_state ) ) {
			throw new \InvalidArgumentException( 'Unknown publication state.' );
		}
		$rows = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'pub_state'  => $pub_state,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'profile_id' => $profile_id )
		);
		if ( false === $rows || null === $this->get( $profile_id ) ) {
			throw new \InvalidArgumentException( 'No state row for this profile.' );
		}
	}
}
