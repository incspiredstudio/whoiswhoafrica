<?php
/**
 * The six publication states of a profile (REQ-208). Values are stored in wiw_profile_state.pub_state. This class only names
 * the states and answers questions about them; changing a state is Review\StateMachine's job alone.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\State;

/**
 * Publication states.
 */
final class PubState {

	public const IMPORTED_NOT_PUBLIC = 'imported_not_public';
	public const PUBLISHED_PENDING   = 'published_pending';
	public const PUBLISHED_REVIEWED  = 'published_reviewed';
	public const UNPUBLISHED         = 'unpublished';
	public const REJECTED            = 'rejected';
	public const REMOVED_SUPPRESSED  = 'removed_suppressed';

	/**
	 * Every state.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::IMPORTED_NOT_PUBLIC,
			self::PUBLISHED_PENDING,
			self::PUBLISHED_REVIEWED,
			self::UNPUBLISHED,
			self::REJECTED,
			self::REMOVED_SUPPRESSED,
		);
	}

	/**
	 * Whether a state is one of the two public ones.
	 *
	 * @param string $state State.
	 */
	public static function is_public( string $state ): bool {
		return self::PUBLISHED_PENDING === $state || self::PUBLISHED_REVIEWED === $state;
	}

	/**
	 * Whether a value is a known state.
	 *
	 * @param string $state Value.
	 */
	public static function is_valid( string $state ): bool {
		return in_array( $state, self::all(), true );
	}
}
