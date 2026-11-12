<?php
/**
 * The pipeline's claims, source passages and verification report for a profile (contract sections 6 and 7, REQ-142, REQ-166,
 * REQ-208). They let a reviewer see each claim beside its source. They are admin-only: this repository has no public read path,
 * reading needs the review capability, and no public read model, REST route or template touches these tables. Named wiw_claims
 * for import claims; profile claim requests use another table (Trust).
 *
 * A claim whose quote is not found exactly in its passage is refused, and a refused set writes nothing.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import;

/**
 * Evidence repository.
 */
final class EvidenceRepository {

	public const READ_CAPABILITY = 'wiw_review_records';

	public const CLAIMS = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		profile_id bigint(20) unsigned NOT NULL,
		claim_id varchar(64) NOT NULL,
		field varchar(64) NOT NULL,
		claim_text text NOT NULL,
		passage_id varchar(64) NOT NULL,
		quote text NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY profile_claim (profile_id, claim_id),
		KEY profile_field (profile_id, field)';

	public const PASSAGES = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		profile_id bigint(20) unsigned NOT NULL,
		passage_id varchar(64) NOT NULL,
		source_id varchar(100) NOT NULL,
		source_url varchar(2048) NOT NULL,
		retrieved_at datetime NOT NULL,
		source_hash char(64) NULL,
		passage_text mediumtext NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY profile_passage (profile_id, passage_id)';

	public const VERIFICATION = 'profile_id bigint(20) unsigned NOT NULL,
		run_id varchar(64) NOT NULL,
		verified_at datetime NOT NULL,
		checks text NOT NULL,
		claims_kept int(10) unsigned NOT NULL DEFAULT 0,
		claims_dropped int(10) unsigned NOT NULL DEFAULT 0,
		fields_dropped text NULL,
		PRIMARY KEY  (profile_id)';

	/**
	 * Table name with the site prefix.
	 *
	 * @param string $name claims, passages or verification.
	 */
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'wiw_' . $name;
	}

	/**
	 * Check the quotes of a record's claims without writing anything.
	 *
	 * @param array<int, array<string, mixed>> $claims   Claims.
	 * @param array<int, array<string, mixed>> $passages Passages.
	 * @return array<int, string> claim ids that fail: the quote is not in its passage, the passage is missing or an id repeats.
	 */
	public static function bad_claims( array $claims, array $passages ): array {
		$text = array();
		foreach ( $passages as $passage ) {
			if ( isset( $passage['passage_id'], $passage['text'] ) && is_string( $passage['passage_id'] ) && is_string( $passage['text'] ) ) {
				$text[ $passage['passage_id'] ] = $passage['text'];
			}
		}
		$bad  = array();
		$seen = array();
		foreach ( $claims as $claim ) {
			$id    = isset( $claim['claim_id'] ) && is_string( $claim['claim_id'] ) ? $claim['claim_id'] : '';
			$pid   = isset( $claim['passage_id'] ) && is_string( $claim['passage_id'] ) ? $claim['passage_id'] : '';
			$quote = isset( $claim['quote'] ) && is_string( $claim['quote'] ) ? $claim['quote'] : '';
			if ( '' === $id || isset( $seen[ $id ] ) || '' === $quote || ! isset( $text[ $pid ] ) || false === strpos( $text[ $pid ], $quote ) ) {
				$bad[] = $id;
			}
			$seen[ $id ] = true;
		}
		return $bad;
	}

	/**
	 * Replace the evidence of a profile with the record's claims, passages and verification report.
	 *
	 * @param int                  $profile_id Profile post id.
	 * @param array<string, mixed> $record     A validated record (decoded to arrays).
	 * @throws \InvalidArgumentException When a claim's quote is not exactly in its passage; nothing is written.
	 */
	public function store( int $profile_id, array $record ): void {
		global $wpdb;
		$claims   = is_array( $record['claims'] ?? null ) ? $record['claims'] : array();
		$passages = is_array( $record['passages'] ?? null ) ? $record['passages'] : array();
		if ( array() !== self::bad_claims( $claims, $passages ) ) {
			throw new \InvalidArgumentException( 'A claim quote must appear exactly in its passage.' );
		}
		$this->clear( $profile_id );
		foreach ( $passages as $passage ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::table( 'passages' ),
				array(
					'profile_id'   => $profile_id,
					'passage_id'   => (string) $passage['passage_id'],
					'source_id'    => (string) ( $passage['source_id'] ?? '' ),
					'source_url'   => (string) ( $passage['source_url'] ?? '' ),
					'retrieved_at' => gmdate( 'Y-m-d H:i:s', (int) strtotime( (string) ( $passage['retrieved_at'] ?? 'now' ) ) ),
					'source_hash'  => self::strip_hash_prefix( (string) ( $passage['source_hash'] ?? '' ) ),
					'passage_text' => (string) $passage['text'],
				)
			);
		}
		foreach ( $claims as $claim ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::table( 'claims' ),
				array(
					'profile_id' => $profile_id,
					'claim_id'   => (string) $claim['claim_id'],
					'field'      => (string) ( $claim['field'] ?? '' ),
					'claim_text' => (string) ( $claim['text'] ?? '' ),
					'passage_id' => (string) $claim['passage_id'],
					'quote'      => (string) $claim['quote'],
				)
			);
		}
		$report = $record['verification'] ?? null;
		if ( is_array( $report ) ) {
			$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::table( 'verification' ),
				array(
					'profile_id'     => $profile_id,
					'run_id'         => (string) ( $report['run_id'] ?? '' ),
					'verified_at'    => gmdate( 'Y-m-d H:i:s', (int) strtotime( (string) ( $report['verified_at'] ?? 'now' ) ) ),
					'checks'         => (string) wp_json_encode( $report['checks'] ?? array() ),
					'claims_kept'    => (int) ( $report['claims_kept'] ?? 0 ),
					'claims_dropped' => (int) ( $report['claims_dropped'] ?? 0 ),
					'fields_dropped' => (string) wp_json_encode( $report['fields_dropped'] ?? array() ),
				)
			);
		}
	}

	/**
	 * Remove a profile's evidence (an update replaces it; an erase request removes it).
	 *
	 * @param int $profile_id Profile post id.
	 */
	public function clear( int $profile_id ): void {
		global $wpdb;
		foreach ( array( 'claims', 'passages', 'verification' ) as $name ) {
			$wpdb->delete( self::table( $name ), array( 'profile_id' => $profile_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * A profile's evidence for the reviewer screen. Needs the review capability; anyone else gets nothing.
	 *
	 * @param int $profile_id Profile post id.
	 * @return array{claims: array<int, array<string, mixed>>, passages: array<int, array<string, mixed>>, verification: array<string, mixed>|null}
	 * @throws \DomainException When the current user may not read it.
	 */
	public function for_reviewer( int $profile_id ): array {
		global $wpdb;
		if ( ! current_user_can( self::READ_CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'Not allowed to read import evidence.' );
		}
		$read   = static fn ( string $name ): array => (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( $name ) . ' WHERE profile_id = %d ORDER BY ' . ( 'verification' === $name ? 'profile_id' : 'id' ) . ' ASC', $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table and column names.
		$report = $read( 'verification' );
		return array(
			'claims'       => $read( 'claims' ),
			'passages'     => $read( 'passages' ),
			'verification' => array() === $report ? null : $report[0],
		);
	}

	/**
	 * A provenance or source hash without the contract's "sha256:" prefix, for the char(64) columns.
	 *
	 * @param string $hash Hash as received.
	 */
	public static function strip_hash_prefix( string $hash ): ?string {
		$hash = (string) preg_replace( '/^sha256:/', '', $hash );
		return '' === $hash ? null : $hash;
	}
}
