<?php
/**
 * Only the Review module may use the guard's StateChangeToken, the permission to change a guarded record's post status
 * (REQ-210). Any other file in the plugin that names it could bypass the state machine.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch\Rules;

use WhoIsWho\Tests\Arch\Support\Rule;
use WhoIsWho\Tests\Arch\Support\SourceSet;

/**
 * One holder of the token.
 */
final class OnlyReviewUsesStateChangeToken implements Rule {

	/**
	 * {@inheritDoc}
	 *
	 * @param SourceSet $sources Sources.
	 */
	public function violations( SourceSet $sources ): array {
		$bad = array();
		foreach ( $sources->paths() as $path ) {
			if ( 0 === strpos( $path, 'Review/' ) ) {
				continue;
			}
			foreach ( $sources->names( $path ) as $name ) {
				if ( 1 === preg_match( '/(^|\\\\)StateChangeToken$/', $name ) ) {
					$bad[] = "$path uses the StateChangeToken";
				}
			}
		}
		return array_values( array_unique( $bad ) );
	}
}
