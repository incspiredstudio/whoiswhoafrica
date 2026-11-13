<?php
/**
 * The public read model of the winners (REQ-033, REQ-198): Live nominations at stage Winner whose profile is reviewed and public,
 * newest edition first, and nothing else. It is a view over PublicNominee, so it has the same absences: no nominator, no
 * evidence, no Contact email. Filters by year, group, category, type and country arrive with the winners query (M4-AWD-14).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

/**
 * Public winners.
 */
final class PublicWinner {

	/**
	 * Live winners, newest edition first.
	 *
	 * @param int $limit Maximum.
	 * @return PublicNominee[]
	 */
	public static function all( int $limit = 500 ): array {
		return PublicNominee::winners( $limit );
	}

	/**
	 * Winners grouped by year, newest first.
	 *
	 * @param int $limit Maximum.
	 * @return array<int, PublicNominee[]> Year => winners.
	 */
	public static function by_year( int $limit = 500 ): array {
		$out = array();
		foreach ( self::all( $limit ) as $winner ) {
			$out[ $winner->year ][] = $winner;
		}
		return $out;
	}
}
