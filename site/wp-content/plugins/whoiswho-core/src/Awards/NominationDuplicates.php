<?php
/**
 * The duplicate check for a public nomination (REQ-196, REQ-198): the same nominee, by normalised name and organisation, already
 * nominated in the same category, or a name on the suppression list. The visitor only ever sees one neutral warning and may
 * continue; the answer here says which kind it was for staff and tests, and is never shown to the visitor, so the form cannot be
 * used to find out who was nominated or who asked to be removed.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Nomination duplicates.
 */
final class NominationDuplicates {

	public const NOMINATED  = 'nominated';
	public const SUPPRESSED = 'suppressed';

	/**
	 * Suppression list.
	 *
	 * @var Suppression
	 */
	private Suppression $suppression;

	/**
	 * Constructor.
	 *
	 * @param Suppression|null $suppression Suppression list; this site's by default.
	 */
	public function __construct( ?Suppression $suppression = null ) {
		$this->suppression = $suppression ?? new Suppression( AuditLogger::for_site() );
	}

	/**
	 * Whether a nominee is already nominated in the category or suppressed.
	 *
	 * @param string $name        Nominee name as typed.
	 * @param string $org         Organisation as typed, or empty.
	 * @param int    $category_id Category.
	 * @param int    $exclude_id  A nomination to leave out (the one being looked at); 0 for none.
	 * @return string|null NOMINATED, SUPPRESSED, or null when there is no duplicate.
	 */
	public function check( string $name, string $org, int $category_id, int $exclude_id = 0 ): ?string {
		if ( '' === Suppression::normalise_name( $name ) ) {
			return null;
		}
		if ( array() !== $this->same_nominee( $name, $org, $category_id, $exclude_id ) ) {
			return self::NOMINATED;
		}
		foreach ( StateRepository::ENTITY_TYPES as $type ) {
			if ( null !== $this->suppression->match( $name, $type ) ) {
				return self::SUPPRESSED;
			}
		}
		return null;
	}

	/**
	 * The other nominations in the category for the same nominee (normalised name, and organisation when both are given). For
	 * staff only: the ids, never the nominators.
	 *
	 * @param string $name        Nominee name as typed.
	 * @param string $org         Organisation as typed, or empty.
	 * @param int    $category_id Category.
	 * @param int    $exclude_id  A nomination to leave out; 0 for none.
	 * @return int[]
	 */
	public function same_nominee( string $name, string $org, int $category_id, int $exclude_id = 0 ): array {
		$key = Suppression::normalise_name( $name );
		if ( '' === $key ) {
			return array();
		}
		$org_key = Suppression::normalise_name( $org );
		$ids     = array();
		foreach ( $this->candidates( $category_id, $exclude_id ) as $row ) {
			$other = '' !== (string) $row['nominee_name'] ? (string) $row['nominee_name'] : ( (int) $row['profile_id'] > 0 ? get_the_title( (int) $row['profile_id'] ) : '' );
			if ( Suppression::normalise_name( $other ) !== $key ) {
				continue;
			}
			$other_org = Suppression::normalise_name( (string) $row['nominee_org'] );
			if ( '' === $org_key || '' === $other_org || $org_key === $other_org ) {
				$ids[] = (int) $row['id'];
			}
		}
		return $ids;
	}

	/**
	 * Nominations in a category, any stage or status (a Not live one still counts).
	 *
	 * @param int $category_id Category.
	 * @param int $exclude_id  A nomination to leave out; 0 for none.
	 * @return array<int, array<string, mixed>>
	 */
	private function candidates( int $category_id, int $exclude_id ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, nominee_name, nominee_org, profile_id FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ) . ' WHERE category_id = %d AND id <> %d LIMIT 5000', $category_id, $exclude_id ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}
}
