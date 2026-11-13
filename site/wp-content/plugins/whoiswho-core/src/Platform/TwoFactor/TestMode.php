<?php
/**
 * The test-environment-only sign-in hook for Playwright (M0-QAX-01). It exists only when WIW_E2E_TEST_MODE is true, which the
 * config template sets only outside production and only when the secrets file asks for it. In production the constant is false
 * and the environment type is production, so this class can never exempt anyone. Users are marked by the wiw_e2e_staff meta,
 * which only the CI install step sets.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\TwoFactor;

/**
 * Test mode.
 */
final class TestMode {

	public const META = 'wiw_e2e_staff';

	/**
	 * Whether test mode is on. Never in production.
	 */
	public static function enabled(): bool {
		return defined( 'WIW_E2E_TEST_MODE' ) && true === WIW_E2E_TEST_MODE && 'production' !== wp_get_environment_type();
	}

	/**
	 * Whether this user is exempt from two-factor in test mode.
	 *
	 * @param int $user_id User id.
	 */
	public static function exempts( int $user_id ): bool {
		return self::enabled() && '1' === (string) get_user_meta( $user_id, self::META, true );
	}

	/**
	 * Attach the login hook: the plugin's prompt is skipped for marked test users in test mode.
	 */
	public static function register(): void {
		if ( ! self::enabled() ) {
			return;
		}
		add_filter(
			'two_factor_is_required_for_user',
			static function ( $required, $user ) {
				return $user instanceof \WP_User && self::exempts( (int) $user->ID ) ? false : $required;
			},
			10,
			2
		);
	}
}
