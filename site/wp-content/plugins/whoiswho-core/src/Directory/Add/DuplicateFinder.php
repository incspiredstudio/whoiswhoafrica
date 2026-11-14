<?php
/**
 * The duplicate check of the Add profile form (REQ-145, REQ-146): before a profile is created by hand, existing profiles of the
 * same entity type whose normalised name is the same or very close, and profiles of any type that share a Wikidata id or a
 * registration number, are listed so the staff member can open them or create anyway. It only reads.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Add;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Review\Duplicates\Matcher;

/**
 * Duplicate finder.
 */
final class DuplicateFinder {

	/**
	 * Candidates compared by name at most.
	 */
	private const CANDIDATES = 500;

	/**
	 * Most matches listed.
	 */
	public const LIMIT = 20;

	/**
	 * Possible duplicates.
	 *
	 * @param string                $name         Name as typed.
	 * @param string                $type         Entity type.
	 * @param array<string, string> $external_ids Identifier key => value (wikidata_qid, rc_number).
	 * @return array<int, array{id: int, name: string, type: string, state: string, basis: string}>
	 */
	public function find( string $name, string $type, array $external_ids = array() ): array {
		$found = array();
		foreach ( $external_ids as $key => $value ) {
			if ( '' === trim( $value ) ) {
				continue;
			}
			foreach ( $this->by_identifier( (string) $key, $value ) as $id ) {
				$found[ $id ] = $key;
			}
		}
		$mine = Matcher::normalise( $name );
		if ( '' !== $mine ) {
			foreach ( $this->by_name( $mine, $type ) as $id => $title ) {
				if ( ! isset( $found[ $id ] ) && Matcher::similarity( $mine, Matcher::normalise( $title ) ) >= Matcher::MIN_NAME_SCORE ) {
					$found[ $id ] = 'name';
				}
			}
		}
		$out   = array();
		$state = new StateRepository();
		foreach ( array_slice( $found, 0, self::LIMIT, true ) as $id => $basis ) {
			$row   = $state->get( (int) $id );
			$out[] = array(
				'id'    => (int) $id,
				'name'  => get_the_title( (int) $id ),
				'type'  => (string) ( $row['entity_type'] ?? '' ),
				'state' => (string) ( $row[ StateRepository::STATE_COLUMN ] ?? '' ),
				'basis' => (string) $basis,
			);
		}
		return $out;
	}

	/**
	 * Profiles whose stored identifiers hold this value.
	 *
	 * @param string $key   Identifier key.
	 * @param string $value Identifier.
	 * @return int[]
	 */
	private function by_identifier( string $key, string $value ): array {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( '"' . $key . '":"' . trim( $value ) . '"' ) . '%';
		$ids  = (array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value LIKE %s AND p.post_type = %s AND p.post_status NOT IN ('trash','auto-draft') LIMIT %d",
				Matcher::EXTERNAL_IDS_META,
				$like,
				ProfileType::POST_TYPE,
				self::LIMIT
			)
		);
		return array_map( 'intval', $ids );
	}

	/**
	 * Profiles of the same type whose title shares the longest word of the normalised name: id => title.
	 *
	 * @param string $normalised Normalised name.
	 * @param string $type       Entity type.
	 * @return array<int, string>
	 */
	private function by_name( string $normalised, string $type ): array {
		global $wpdb;
		$words = explode( ' ', $normalised );
		usort( $words, static fn ( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT p.ID, p.post_title FROM ' . $wpdb->posts . ' p INNER JOIN ' . StateRepository::table() . " s ON s.profile_id = p.ID WHERE p.post_type = %s AND p.post_status NOT IN ('trash','auto-draft') AND s.entity_type = %s AND p.post_title LIKE %s LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table names.
				ProfileType::POST_TYPE,
				$type,
				'%' . $wpdb->esc_like( $words[0] ) . '%',
				self::CANDIDATES
			),
			ARRAY_A
		);
		$out  = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row['ID'] ] = (string) $row['post_title'];
		}
		return $out;
	}
}
