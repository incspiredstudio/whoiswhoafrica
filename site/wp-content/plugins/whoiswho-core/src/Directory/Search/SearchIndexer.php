<?php
/**
 * Keeps wiw_profile_search in step with publication state (REQ-254, REQ-208, REQ-079, REQ-216). It listens to the state
 * machine's in-transaction event, so a row is added or removed in the same step as the state change and a failure rolls both
 * back. It only ever holds the two Published states, reads every row through PublicProfile and never writes a state.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Search;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Log\Logger;

/**
 * Search indexer.
 */
final class SearchIndexer {

	public const TABLE     = 'wiw_profile_search';
	public const ITEM_TYPE = 'profile';

	public const TABLE_DEFINITION = 'item_type varchar(20) NOT NULL,
		item_id bigint(20) unsigned NOT NULL,
		entity_type varchar(20) NOT NULL DEFAULT \'\',
		name varchar(255) NOT NULL DEFAULT \'\',
		organisation varchar(255) NOT NULL DEFAULT \'\',
		title varchar(255) NOT NULL DEFAULT \'\',
		industry varchar(255) NOT NULL DEFAULT \'\',
		bio text NOT NULL,
		url varchar(2083) NOT NULL DEFAULT \'\',
		pending tinyint(1) unsigned NOT NULL DEFAULT 0,
		name_sort varchar(255) NOT NULL DEFAULT \'\',
		sponsored tinyint(1) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (item_type,item_id),
		KEY name_sort (name_sort(100)),
		KEY pending (pending),
		FULLTEXT KEY search_text (name,organisation,title,industry,bio)';

	/**
	 * Logger.
	 *
	 * @var Logger|null
	 */
	private ?Logger $log;

	/**
	 * Constructor.
	 *
	 * @param Logger|null $log Logger; the site logger when null.
	 */
	public function __construct( ?Logger $log = null ) {
		$this->log = $log;
	}

	/**
	 * Table name with the site prefix.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Listen to the state machine.
	 */
	public function register(): void {
		add_action( 'wiw_profile_state_in_transaction', array( $this, 'on_state_change' ), 10, 3 );
		add_action( 'save_post_' . ProfileType::POST_TYPE, array( $this, 'on_save' ), 20, 1 );
	}

	/**
	 * Refresh the row after an edit, so changed text is searchable at once. A profile that is not public gets no row.
	 *
	 * @param int $profile_id Profile.
	 */
	public function on_save( int $profile_id ): void {
		if ( null !== ( new StateRepository() )->get( $profile_id ) ) {
			$this->upsert( $profile_id );
		}
	}

	/**
	 * Add or remove the row for a state change.
	 *
	 * @param int         $profile_id Profile.
	 * @param string|null $from       Previous state.
	 * @param string      $to         New state.
	 * @throws \RuntimeException When the row cannot be written, so the state change rolls back.
	 */
	public function on_state_change( int $profile_id, ?string $from, string $to ): void {
		unset( $from );
		if ( PubState::is_public( $to ) ) {
			$this->upsert( $profile_id );
		} else {
			$this->remove( $profile_id );
		}
	}

	/**
	 * Add or refresh a profile's row; the row is removed instead unless the read model says the profile is public.
	 *
	 * @param int $profile_id Profile.
	 * @return bool True when a row is present afterwards.
	 * @throws \RuntimeException When the write fails.
	 */
	public function upsert( int $profile_id ): bool {
		global $wpdb;
		$profile = PublicProfile::find_by_id( $profile_id );
		if ( null === $profile || ! PubState::is_public( $profile->state ) ) {
			$this->remove( $profile_id );
			return false;
		}
		$facts = $profile->facts;
		$org   = $this->text( $facts['current_organisation']['value'] ?? ( null !== $profile->organisation ? $profile->organisation['name'] : '' ) );
		$title = $this->text( $facts['current_title']['value'] ?? ( $facts['org_type']['value'] ?? '' ) );
		$bio   = $this->text( $facts['bio']['value'] ?? ( $facts['summary']['value'] ?? '' ) );
		$sort  = strtolower( remove_accents( $profile->name ) );
		$ok    = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'REPLACE INTO ' . self::table() . ' (item_type, item_id, entity_type, name, organisation, title, industry, bio, url, pending, name_sort) VALUES (%s, %d, %s, %s, %s, %s, %s, %s, %s, %d, %s)', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
				self::ITEM_TYPE,
				$profile->id,
				$profile->entity_type,
				$profile->name,
				$org,
				$title,
				$this->text( $facts['industry']['value'] ?? '' ),
				$bio,
				$profile->url,
				PubState::PUBLISHED_PENDING === $profile->state ? 1 : 0,
				$sort
			)
		);
		if ( false === $ok ) {
			throw new \RuntimeException( 'The search row could not be written.' );
		}
		return true;
	}

	/**
	 * Remove a profile's row. A removal that actually dropped a row is written to the purge log (identifier only).
	 *
	 * @param int $profile_id Profile.
	 * @return bool True when a row was removed.
	 * @throws \RuntimeException When the delete fails.
	 */
	public function remove( int $profile_id ): bool {
		global $wpdb;
		$rows = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'item_type' => self::ITEM_TYPE,
				'item_id'   => $profile_id,
			),
			array( '%s', '%d' )
		);
		if ( false === $rows ) {
			throw new \RuntimeException( 'The search row could not be removed.' );
		}
		if ( $rows > 0 ) {
			( $this->log ?? Logger::for_site() )->log(
				'info',
				'purge_queued',
				array(
					'event' => 'search_removed',
					'id'    => $profile_id,
				)
			);
		}
		return $rows > 0;
	}

	/**
	 * Rebuild the index: every public profile is upserted through the read model and every other row is dropped.
	 *
	 * @return int Rows present afterwards.
	 */
	public function rebuild(): int {
		global $wpdb;
		$public = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT profile_id FROM ' . StateRepository::table() . ' WHERE pub_state IN (%s, %s) ORDER BY profile_id', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
				PubState::PUBLISHED_REVIEWED,
				PubState::PUBLISHED_PENDING
			)
		);
		$keep   = array();
		foreach ( (array) $public as $id ) {
			if ( $this->upsert( (int) $id ) ) {
				$keep[ (int) $id ] = true;
			}
		}
		$present = $wpdb->get_col( 'SELECT item_id FROM ' . self::table() . ' WHERE item_type = \'' . self::ITEM_TYPE . '\'' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name and constant.
		foreach ( (array) $present as $id ) {
			if ( ! isset( $keep[ (int) $id ] ) ) {
				$this->remove( (int) $id );
			}
		}
		return count( $keep );
	}

	/**
	 * A fact value as plain text.
	 *
	 * @param mixed $value Value (string or list of strings).
	 */
	private function text( $value ): string {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_filter( $value, 'is_string' ) );
		}
		return is_string( $value ) ? wp_strip_all_tags( $value ) : '';
	}
}
