<?php
/**
 * The fact gate for the Review item screen (REQ-142, REQ-143): a fact with low confidence, or an imported fact with no source,
 * cannot be published from review. It only reads: it lists the problems in words and never changes a profile.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review;

use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\StateRepository;

/**
 * Fact gate.
 */
final class FactGate {

	/**
	 * Problems that stop a profile being approved.
	 *
	 * @param int $profile_id Profile post id.
	 * @return string[] Messages naming the field, never its value. Empty when nothing blocks.
	 */
	public function problems( int $profile_id ): array {
		$out        = array();
		$provenance = new ProvenanceRepository();
		$by_field   = array();
		foreach ( $provenance->for_profile( $profile_id ) as $entry ) {
			$by_field[ (string) $entry['field'] ][] = $entry;
			if ( 'low' === strtolower( (string) ( $entry['confidence'] ?? '' ) ) ) {
				$out[] = sprintf( 'The field "%s" has a low-confidence source.', (string) $entry['field'] );
			}
		}
		$row = ( new StateRepository() )->get( $profile_id );
		if ( null !== $row && 'import' === (string) ( $row['origin'] ?? '' ) ) {
			foreach ( array_keys( ( new FieldAllowlist() )->for_type( (string) $row['entity_type'] ) ) as $key ) {
				$value = 'name' === $key ? get_the_title( $profile_id ) : get_post_meta( $profile_id, (string) FieldAllowlist::meta_key( (string) $key ), true );
				if ( '' !== $value && false !== $value && ! isset( $by_field[ (string) $key ] ) ) {
					$out[] = sprintf( 'The field "%s" has no source.', (string) $key );
				}
			}
		}
		return array_values( array_unique( $out ) );
	}
}
