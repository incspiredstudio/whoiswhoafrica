<?php
/**
 * Commerce code and the Sponsored label code never write the Claimed mark, the Featured flag or the Verified mark
 * (REQ-184, REQ-199). They may only write their own sponsored_* columns.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch\Rules;

use WhoIsWho\Tests\Arch\Support\Rule;
use WhoIsWho\Tests\Arch\Support\SourceSet;

/**
 * Commerce does not buy trust marks.
 */
final class CommerceNeverWritesClaimedOrFeatured implements Rule {

	/**
	 * Keys of the marks commercial code must never write.
	 */
	public const FORBIDDEN_KEYS = array( 'claimed', 'claimed_at', 'featured', 'is_featured', '_wiw_featured', 'verified', 'trust' );

	/**
	 * Functions that set the Featured flag of a story.
	 */
	public const FORBIDDEN_CALLS = array( 'stick_post', 'unstick_post' );

	/**
	 * {@inheritDoc}
	 *
	 * @param SourceSet $sources Sources.
	 */
	public function violations( SourceSet $sources ): array {
		$bad = array();
		foreach ( $sources->paths() as $path ) {
			if ( ! $this->is_commercial( $path ) ) {
				continue;
			}
			foreach ( $sources->array_keys( $path ) as $key ) {
				if ( in_array( $key, self::FORBIDDEN_KEYS, true ) ) {
					$bad[] = "$path writes the '$key' key";
				}
			}
			foreach ( $sources->strings( $path ) as $text ) {
				if ( in_array( $text, self::FORBIDDEN_KEYS, true ) || 1 === preg_match( '/\b(UPDATE|INSERT|REPLACE)\b.*\b(claimed|claimed_at|featured|verified|trust)\b/is', $text ) ) {
					$bad[] = "$path names the '$text' mark";
				}
			}
			foreach ( $sources->calls( $path ) as $call ) {
				if ( in_array( $call, self::FORBIDDEN_CALLS, true ) ) {
					$bad[] = "$path calls $call";
				}
			}
		}
		return array_values( array_unique( $bad ) );
	}

	/**
	 * Commerce, or any file that carries the Sponsored label.
	 *
	 * @param string $path Relative path.
	 */
	private function is_commercial( string $path ): bool {
		return 0 === strpos( $path, 'Commerce/' ) || 1 === preg_match( '/sponsor/i', $path );
	}
}
