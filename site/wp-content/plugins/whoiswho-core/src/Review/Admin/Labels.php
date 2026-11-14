<?php
/**
 * Plain words for the codes the review screens show (REQ-084, REQ-244): entity types, publication states, profile fields and
 * reject reasons. A code the lists do not know is shown humanised ("some_key" becomes "Some key"), never raw.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Admin;

use WhoIsWho\Core\Directory\Admin\AddProfileScreen;
use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\State\PubState;

/**
 * Review labels.
 */
final class Labels {

	/**
	 * An entity type's name.
	 *
	 * @param string $type Entity type code.
	 */
	public static function entity_type( string $type ): string {
		$label = AddProfileScreen::type_label( $type );
		return $label === $type ? self::humanise( $type ) : $label;
	}

	/**
	 * A publication state's name (the wording of the Dashboard tiles).
	 *
	 * @param string $state State code.
	 */
	public static function state( string $state ): string {
		$labels = array(
			PubState::IMPORTED_NOT_PUBLIC => __( 'Imported, not public', 'whoiswho' ),
			PubState::PUBLISHED_PENDING   => __( 'Published, waiting for review', 'whoiswho' ),
			PubState::PUBLISHED_REVIEWED  => __( 'Published and reviewed', 'whoiswho' ),
			PubState::UNPUBLISHED         => __( 'Unpublished', 'whoiswho' ),
			PubState::REJECTED            => __( 'Rejected', 'whoiswho' ),
			PubState::REMOVED_SUPPRESSED  => __( 'Removed and suppressed', 'whoiswho' ),
		);
		return $labels[ $state ] ?? self::humanise( $state );
	}

	/**
	 * A profile field's label: the field list's own label when it has one, else the Add profile screen's words, else the key
	 * humanised.
	 *
	 * @param string $key Field key.
	 */
	public static function field( string $key ): string {
		if ( method_exists( FieldAllowlist::class, 'label' ) ) {
			$label = call_user_func( array( new FieldAllowlist(), 'label' ), $key );
			if ( is_string( $label ) && '' !== $label && $label !== $key ) {
				return $label;
			}
		}
		return AddProfileScreen::field_label( $key );
	}

	/**
	 * A reject reason's label.
	 *
	 * @param string $code Reason code (DecisionLog::REASON_CODES).
	 */
	public static function reason( string $code ): string {
		$labels = array(
			'duplicate'    => __( 'Duplicate of another profile', 'whoiswho' ),
			'inaccurate'   => __( 'Inaccurate', 'whoiswho' ),
			'unverifiable' => __( 'Cannot be verified from the sources', 'whoiswho' ),
			'out_of_scope' => __( 'Out of scope (does not meet the inclusion rules)', 'whoiswho' ),
			'policy'       => __( 'Against the editorial policy', 'whoiswho' ),
			'other'        => __( 'Other', 'whoiswho' ),
		);
		return $labels[ $code ] ?? self::humanise( $code );
	}

	/**
	 * A code in words: underscores become spaces, first letter capital.
	 *
	 * @param string $code Code.
	 */
	public static function humanise( string $code ): string {
		return ucfirst( str_replace( array( '_', '-' ), ' ', $code ) );
	}
}
