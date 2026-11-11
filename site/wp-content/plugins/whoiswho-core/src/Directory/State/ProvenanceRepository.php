<?php
/**
 * Field provenance: for each profile field, where it came from (REQ-142, REQ-143). A row without a source URL, retrieval date,
 * confidence and method is refused, and so is a low-confidence row, because such a field cannot be published.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\State;

/**
 * Provenance table access.
 */
final class ProvenanceRepository {

	public const TABLE = 'wiw_field_provenance';

	public const CONFIDENCE = array( 'high', 'medium', 'low' );

	public const METHODS = array( 'api', 'import', 'manual', 'ai' );

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Check one provenance entry without writing it.
	 *
	 * @param array<string, mixed> $entry field, source_url, retrieved_at, confidence, method and optionally source_hash.
	 * @return string|null Refusal code (UNSOURCED_FIELD, LOW_CONFIDENCE) or null when acceptable.
	 */
	public static function refusal( array $entry ): ?string {
		foreach ( array( 'field', 'source_url', 'retrieved_at', 'confidence', 'method' ) as $key ) {
			if ( ! isset( $entry[ $key ] ) || '' === trim( (string) $entry[ $key ] ) ) {
				return 'UNSOURCED_FIELD';
			}
		}
		if ( ! in_array( $entry['confidence'], self::CONFIDENCE, true ) || ! in_array( $entry['method'], self::METHODS, true ) ) {
			return 'UNSOURCED_FIELD';
		}
		if ( false === strtotime( (string) $entry['retrieved_at'] ) ) {
			return 'UNSOURCED_FIELD';
		}
		return 'low' === $entry['confidence'] ? 'LOW_CONFIDENCE' : null;
	}

	/**
	 * Record one entry.
	 *
	 * @param int                  $profile_id Profile post id.
	 * @param array<string, mixed> $entry      See refusal().
	 * @return int Row id.
	 * @throws \InvalidArgumentException When the entry is refused.
	 * @throws \RuntimeException When the row cannot be written.
	 */
	public function record( int $profile_id, array $entry ): int {
		global $wpdb;
		$refusal = self::refusal( $entry );
		if ( null !== $refusal ) {
			throw new \InvalidArgumentException( esc_html( $refusal ) );
		}
		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'profile_id'   => $profile_id,
				'field'        => (string) $entry['field'],
				'source_url'   => (string) $entry['source_url'],
				'retrieved_at' => gmdate( 'Y-m-d H:i:s', (int) strtotime( (string) $entry['retrieved_at'] ) ),
				'confidence'   => (string) $entry['confidence'],
				'method'       => (string) $entry['method'],
				'source_hash'  => isset( $entry['source_hash'] ) && '' !== $entry['source_hash'] ? (string) $entry['source_hash'] : null,
			)
		);
		if ( false === $ok ) {
			throw new \RuntimeException( 'Could not write the provenance row.' );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Entries of one profile, optionally for one field.
	 *
	 * @param int         $profile_id Profile post id.
	 * @param string|null $field      Field name.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_profile( int $profile_id, ?string $field = null ): array {
		global $wpdb;
		$sql  = 'SELECT * FROM ' . self::table() . ' WHERE profile_id = %d';
		$args = array( $profile_id );
		if ( null !== $field ) {
			$sql   .= ' AND field = %s';
			$args[] = $field;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY id ASC', $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- fixed table name; values prepared.
		return is_array( $rows ) ? $rows : array();
	}
}
