<?php
/**
 * The duplicates matcher (REQ-123, REQ-145, REQ-146): finds probable duplicates of a profile by registration number, Wikidata id,
 * and a normalised name plus organisation plus location (diacritics folded, legal suffixes dropped). It only writes pairs to
 * wiw_duplicates for a human to resolve; it never merges, changes or hides a profile, and individuals and entrepreneurs are
 * never merged automatically. Runs when a record is imported and on demand.
 *
 * Identifiers are read from the post meta JSON object `_wiw_external_ids` (keys rc_number, wikidata_qid, ...), written by the
 * importer from the record's external_ids.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Duplicates;

use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\StateRepository;

/**
 * Duplicates matcher.
 */
final class Matcher {

	public const EXTERNAL_IDS_META = '_wiw_external_ids';

	/**
	 * Minimum name similarity (percent) for a name, organisation and location match.
	 */
	public const MIN_NAME_SCORE = 88;

	/**
	 * Candidates compared by name at most, per profile.
	 */
	private const CANDIDATE_LIMIT = 500;

	/**
	 * Repository.
	 *
	 * @var DuplicateRepository
	 */
	private DuplicateRepository $pairs;

	/**
	 * Constructor.
	 *
	 * @param DuplicateRepository|null $pairs Repository.
	 */
	public function __construct( ?DuplicateRepository $pairs = null ) {
		$this->pairs = $pairs ?? new DuplicateRepository();
	}

	/**
	 * Fold a name for comparison: diacritics removed, lower case, punctuation and legal-form words dropped, spaces collapsed.
	 *
	 * @param string $name Name.
	 */
	public static function normalise( string $name ): string {
		$text = strtolower( remove_accents( $name ) );
		$text = (string) preg_replace( '/[^a-z0-9 ]+/', ' ', $text );
		$text = (string) preg_replace( '/\b(ltd|limited|plc|inc|llc|nig|nigeria|co|company|corp|corporation|the)\b/', ' ', $text );
		return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * Similarity of two normalised names, 0 to 100.
	 *
	 * @param string $one Normalised name.
	 * @param string $two Normalised name.
	 */
	public static function similarity( string $one, string $two ): int {
		if ( '' === $one || '' === $two ) {
			return 0;
		}
		if ( $one === $two ) {
			return 100;
		}
		// Words in another order are the same name ("Okafor Chinwe" and "Chinwe Okafor").
		$a = explode( ' ', $one );
		$b = explode( ' ', $two );
		sort( $a );
		sort( $b );
		if ( $a === $b ) {
			return 98;
		}
		similar_text( $one, $two, $percent );
		return (int) round( $percent );
	}

	/**
	 * Compare two profile descriptions. Pure: no database access.
	 *
	 * @param array<string, mixed> $one   Description from describe().
	 * @param array<string, mixed> $other Description from describe().
	 * @return array{score: int, basis: string}|null
	 */
	public static function compare( array $one, array $other ): ?array {
		foreach ( array( 'rc_number', 'wikidata_qid' ) as $key ) {
			$x = self::identifier( $one['external_ids'][ $key ] ?? '' );
			$y = self::identifier( $other['external_ids'][ $key ] ?? '' );
			if ( '' !== $x && $x === $y ) {
				return array(
					'score' => 100,
					'basis' => $key,
				);
			}
		}
		if ( self::group( (string) $one['entity_type'] ) !== self::group( (string) $other['entity_type'] ) ) {
			return null;
		}
		$score = self::similarity( self::normalise( (string) $one['name'] ), self::normalise( (string) $other['name'] ) );
		if ( $score < self::MIN_NAME_SCORE ) {
			return null;
		}
		$org_one = self::normalise( (string) $one['organisation'] );
		$org_two = self::normalise( (string) $other['organisation'] );
		$loc_one = self::normalise( (string) $one['location'] );
		$loc_two = self::normalise( (string) $other['location'] );
		// The organisation and the location must agree where both are known, and at least one must be known on both sides.
		if ( ( '' !== $org_one && '' !== $org_two && $org_one !== $org_two ) || ( '' !== $loc_one && '' !== $loc_two && $loc_one !== $loc_two ) ) {
			return null;
		}
		if ( ( '' === $org_one || '' === $org_two ) && ( '' === $loc_one || '' === $loc_two ) ) {
			return null;
		}
		return array(
			'score' => $score,
			'basis' => 'name_org_location',
		);
	}

	/**
	 * Find probable duplicates of one profile and flag them for review.
	 *
	 * @param int $profile_id Profile post id.
	 * @return int Number of new pairs flagged.
	 */
	public function scan( int $profile_id ): int {
		$me = $this->describe( $profile_id );
		if ( null === $me ) {
			return 0;
		}
		$flagged = 0;
		foreach ( $this->candidates( $me ) as $candidate_id ) {
			$other = $this->describe( $candidate_id );
			if ( null === $other ) {
				continue;
			}
			$match = self::compare( $me, $other );
			if ( null !== $match && $this->pairs->flag( $profile_id, $candidate_id, $match['score'], $match['basis'] ) ) {
				++$flagged;
			}
		}
		return $flagged;
	}

	/**
	 * What the matcher knows about a profile.
	 *
	 * @param int $profile_id Profile post id.
	 * @return array{id: int, entity_type: string, name: string, organisation: string, location: string, external_ids: array<string, string>}|null
	 */
	public function describe( int $profile_id ): ?array {
		$post = get_post( $profile_id );
		$row  = ( new StateRepository() )->get( $profile_id );
		if ( ! $post instanceof \WP_Post || ProfileType::POST_TYPE !== $post->post_type || null === $row ) {
			return null;
		}
		$ids = json_decode( (string) get_post_meta( $profile_id, self::EXTERNAL_IDS_META, true ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- decoding our own stored JSON.
		$ext = array();
		foreach ( is_array( $ids ) ? $ids : array() as $key => $value ) {
			if ( is_string( $key ) && is_string( $value ) ) {
				$ext[ $key ] = $value;
			}
		}
		$meta = static fn ( string $key ): string => (string) get_post_meta( $profile_id, FieldAllowlist::META_PREFIX . $key, true );
		return array(
			'id'           => $profile_id,
			'entity_type'  => (string) $row['entity_type'],
			'name'         => $post->post_title,
			'organisation' => $meta( 'current_organisation' ),
			'location'     => $meta( 'hq_city' ),
			'external_ids' => $ext,
		);
	}

	/**
	 * Other profiles worth comparing: those sharing an identifier, and those whose title shares the first word.
	 *
	 * @param array<string, mixed> $me Description.
	 * @return int[]
	 */
	private function candidates( array $me ): array {
		global $wpdb;
		$ids = array();
		foreach ( array( 'rc_number', 'wikidata_qid' ) as $key ) {
			$value = self::identifier( $me['external_ids'][ $key ] ?? '' );
			if ( '' === $value ) {
				continue;
			}
			$like = '%' . $wpdb->esc_like( '"' . $key . '":"' ) . '%';
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s AND post_id <> %d LIMIT %d", self::EXTERNAL_IDS_META, $like, $me['id'], self::CANDIDATE_LIMIT ) ) as $id ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$ids[ (int) $id ] = true;
			}
		}
		$words = explode( ' ', self::normalise( (string) $me['name'] ) );
		foreach ( $words as $word ) {
			if ( strlen( $word ) < 3 ) {
				continue;
			}
			$like = '%' . $wpdb->esc_like( $word ) . '%';
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s AND ID <> %d AND post_status NOT IN ('trash','auto-draft') LIMIT %d", ProfileType::POST_TYPE, $like, $me['id'], self::CANDIDATE_LIMIT ) ) as $id ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$ids[ (int) $id ] = true;
			}
			break;
		}
		return array_keys( $ids );
	}

	/**
	 * An identifier folded for comparison: letters and digits only, lower case.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function identifier( $value ): string {
		return is_string( $value ) ? strtolower( (string) preg_replace( '/[^A-Za-z0-9]+/', '', $value ) ) : '';
	}

	/**
	 * Entity types that can be the same record: people with people, organisations with organisations.
	 *
	 * @param string $type Entity type.
	 */
	private static function group( string $type ): string {
		return in_array( $type, array( 'individual', 'entrepreneur' ), true ) ? 'person' : 'organisation';
	}
}
