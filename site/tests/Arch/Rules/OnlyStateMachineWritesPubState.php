<?php
/**
 * Only Review\StateMachine (through Directory\State\StateWriter) writes a profile's pub_state or sets a profile's post
 * status to publish (REQ-210). Anything else that writes the key, in an array handed to insert or update, in SQL, or as
 * meta, is a violation.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch\Rules;

use WhoIsWho\Tests\Arch\Support\Rule;
use WhoIsWho\Tests\Arch\Support\SourceSet;

/**
 * One writer for publication state.
 */
final class OnlyStateMachineWritesPubState implements Rule {

	/**
	 * Files allowed to write the state.
	 */
	public const ALLOWED = array( 'Review/StateMachine.php', 'Directory/State/StateWriter.php' );

	/**
	 * Functions whose arguments may write a meta key.
	 */
	public const META_WRITERS = array( 'update_post_meta', 'add_post_meta', 'update_metadata', 'add_metadata' );

	/**
	 * Functions that write a post.
	 */
	public const POST_WRITERS = array( 'wp_update_post', 'wp_insert_post', 'wp_publish_post' );

	/**
	 * {@inheritDoc}
	 *
	 * @param SourceSet $sources Sources.
	 */
	public function violations( SourceSet $sources ): array {
		$bad = array();
		foreach ( $sources->paths() as $path ) {
			if ( in_array( $path, self::ALLOWED, true ) ) {
				continue;
			}
			if ( in_array( 'pub_state', $sources->array_keys( $path ), true ) ) {
				$bad[] = "$path writes the pub_state key";
			}
			$strings = $sources->strings( $path );
			foreach ( $strings as $text ) {
				if ( 1 === preg_match( '/\b(UPDATE|INSERT|REPLACE)\b.*\bpub_state\b/is', $text ) ) {
					$bad[] = "$path writes pub_state in SQL";
				}
			}
			$calls = $sources->calls( $path );
			if ( in_array( 'pub_state', $strings, true ) && array() !== array_intersect( $calls, self::META_WRITERS ) ) {
				$bad[] = "$path writes pub_state as meta";
			}
			if ( in_array( 'publish', $sources->array_values_for( $path, 'post_status' ), true ) && $this->touches_profiles( $strings, $sources->names( $path ) ) ) {
				$bad[] = "$path sets a profile post status to publish";
			}
			if ( array() !== array_intersect( $calls, array( 'wp_publish_post' ) ) && $this->touches_profiles( $strings, $sources->names( $path ) ) ) {
				$bad[] = "$path publishes a profile with wp_publish_post";
			}
		}
		return array_values( array_unique( $bad ) );
	}

	/**
	 * Whether a file deals with the profile post type.
	 *
	 * @param string[] $strings String literals.
	 * @param string[] $names   Class names.
	 */
	private function touches_profiles( array $strings, array $names ): bool {
		if ( in_array( 'wiw_profile', $strings, true ) ) {
			return true;
		}
		foreach ( $names as $name ) {
			if ( 1 === preg_match( '/\\\\ProfileType$/', $name ) ) {
				return true;
			}
		}
		return false;
	}
}
