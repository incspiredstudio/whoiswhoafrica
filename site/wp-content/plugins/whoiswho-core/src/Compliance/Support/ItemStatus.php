<?php
/**
 * Status of a support item (REQ-158).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

/**
 * Item statuses.
 */
final class ItemStatus {

	public const NEW          = 'new';
	public const ACKNOWLEDGED = 'acknowledged';
	public const IN_PROGRESS  = 'in_progress';
	public const RESOLVED     = 'resolved';
	public const DECLINED     = 'declined';

	/**
	 * Every status.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::NEW, self::ACKNOWLEDGED, self::IN_PROGRESS, self::RESOLVED, self::DECLINED );
	}

	/**
	 * Statuses that end the work: no breach can be counted against them.
	 *
	 * @return string[]
	 */
	public static function closed(): array {
		return array( self::RESOLVED, self::DECLINED );
	}

	/**
	 * Whether a status is a closing one.
	 *
	 * @param string $status Status.
	 */
	public static function is_closed( string $status ): bool {
		return in_array( $status, self::closed(), true );
	}
}
