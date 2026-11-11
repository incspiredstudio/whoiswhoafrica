<?php
/**
 * The permission to change the post status of a guarded record (REQ-210). While a callback runs inside run(), the status guard
 * lets status changes through. Only the Review module (the StateMachine) may call run(): an architecture test enforces it, so
 * quick edit, bulk edit, REST, XML-RPC, imports and plugins all stay refused.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Guard\Status;

/**
 * State change token.
 */
final class StateChangeToken {

	/**
	 * Nesting depth of active grants.
	 *
	 * @var int
	 */
	private static int $depth = 0;

	/**
	 * Run a callback with permission to change guarded post statuses.
	 *
	 * @param callable $change The change; its result is returned.
	 * @return mixed
	 * @throws \Throwable Whatever the callback throws, after the permission has been withdrawn.
	 */
	public static function run( callable $change ) {
		++self::$depth;
		try {
			return $change();
		} finally {
			--self::$depth;
		}
	}

	/**
	 * Whether a grant is active right now.
	 */
	public static function is_active(): bool {
		return self::$depth > 0;
	}
}
