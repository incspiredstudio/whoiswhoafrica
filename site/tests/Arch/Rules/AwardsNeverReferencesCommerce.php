<?php
/**
 * Nothing under Awards references Commerce: no use, new, static call or type, and no Commerce table in any SQL string,
 * join or call (REQ-199, D-045: awards are never for sale).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch\Rules;

use WhoIsWho\Tests\Arch\Support\Rule;
use WhoIsWho\Tests\Arch\Support\SourceSet;

/**
 * Awards must not depend on Commerce.
 */
final class AwardsNeverReferencesCommerce implements Rule {

	/**
	 * Tables that belong to Commerce.
	 */
	public const COMMERCE_TABLES = '/wiw_(arrangements|arrangement_links|customers|leads)\b/i';

	/**
	 * {@inheritDoc}
	 *
	 * @param SourceSet $sources Sources.
	 */
	public function violations( SourceSet $sources ): array {
		$bad = array();
		foreach ( $sources->paths() as $path ) {
			if ( 0 !== strpos( $path, 'Awards/' ) ) {
				continue;
			}
			foreach ( $sources->names( $path ) as $name ) {
				if ( 1 === preg_match( '/(^|\\\\)Commerce(\\\\|$)/', $name ) ) {
					$bad[] = "$path references the Commerce class $name";
				}
			}
			foreach ( $sources->strings( $path ) as $text ) {
				if ( 1 === preg_match( self::COMMERCE_TABLES, $text ) ) {
					$bad[] = "$path names a Commerce table";
				}
			}
		}
		return array_values( array_unique( $bad ) );
	}
}
