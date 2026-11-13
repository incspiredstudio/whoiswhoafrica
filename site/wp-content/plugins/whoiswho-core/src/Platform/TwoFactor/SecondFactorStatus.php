<?php
/**
 * What the enforcement needs to know about a user's second factor. The real answer comes from the Two Factor plugin; tests use a fake.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\TwoFactor;

/**
 * Second factor status.
 */
interface SecondFactorStatus {

	/**
	 * Whether the second-factor software is present at all.
	 */
	public function available(): bool;

	/**
	 * Whether the user has set up a real second factor (an authenticator app or email code). Recovery codes alone do not count.
	 *
	 * @param int $user_id User id.
	 */
	public function is_enrolled( int $user_id ): bool;
}
