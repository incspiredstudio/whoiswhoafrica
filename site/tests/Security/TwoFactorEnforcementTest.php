<?php
/**
 * Two-factor enforcement, recovery codes, lockout and password reset, per staff role (REQ-047). Uses the real Two Factor plugin
 * and the guard's sign-in protection. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Security;

use WhoIsWho\Core\Platform\TwoFactor\Enforcement;
use WhoIsWho\Guard\Login\Login;
use WhoIsWho\Tests\WpTestCase;

final class TwoFactorEnforcementTest extends WpTestCase {

	private const PASSWORD = 'Correct-Horse-9-Battery';
	private const ROLES    = array( 'wiw_reviewer', 'wiw_editor', 'administrator' );

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( 0 );
	}

	/**
	 * A staff user.
	 *
	 * @param string $role  Role.
	 * @param string $login Login name.
	 */
	private function staff( string $role, string $login = '' ): int {
		return self::factory()->user->create(
			array(
				'role'       => $role,
				'user_pass'  => self::PASSWORD,
				'user_login' => '' !== $login ? $login : 'tf' . wp_generate_password( 8, false ),
			)
		);
	}

	/**
	 * Give a user a working authenticator app, the way the plugin stores it.
	 *
	 * @param int $user_id User id.
	 */
	private function enrol( int $user_id ): void {
		update_user_meta( $user_id, '_two_factor_enabled_providers', array( 'Two_Factor_Totp', 'Two_Factor_Backup_Codes' ) );
		update_user_meta( $user_id, '_two_factor_totp_key', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567' );
	}

	public function test_REQ_047_staff_cannot_reach_admin_without_2fa(): void {
		$enforcement = Enforcement::for_site();
		foreach ( self::ROLES as $role ) {
			$id = $this->staff( $role );
			foreach ( array( 'index.php', 'edit.php', 'users.php', 'admin.php', 'options-general.php' ) as $page ) {
				$this->assertSame( Enforcement::ENROL, $enforcement->decide( $id, $page ), "$role on $page" );
			}
			$this->assertSame( Enforcement::ALLOW, $enforcement->decide( $id, 'profile.php' ), "$role can still reach the screen where they enrol" );
			$this->enrol( $id );
			$this->assertSame( Enforcement::ALLOW, $enforcement->decide( $id, 'index.php' ), "$role after enrolling" );
		}
	}

	public function test_REQ_047_a_second_factor_prompt_replaces_the_sign_in_for_an_enrolled_user(): void {
		$id = $this->staff( 'wiw_editor', 'tfprompt' );
		$this->enrol( $id );
		$user = get_user_by( 'id', $id );
		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertTrue( \Two_Factor_Core::is_user_using_two_factor( $user ), 'the plugin will ask this user for a second factor at sign-in' );
		$this->assertSame( 31, has_filter( 'authenticate', array( \Two_Factor_Core::class, 'filter_authenticate' ) ), 'the plugin withholds the sign-in cookie until the second factor is given' );
	}

	public function test_REQ_047_password_reset_does_not_bypass_2fa(): void {
		$id = $this->staff( 'wiw_reviewer', 'tfreset' );
		$this->enrol( $id );
		$user = get_user_by( 'id', $id );
		$this->assertInstanceOf( \WP_User::class, $user );

		reset_password( $user, 'Another-Strong-Pass-7-Word' );

		$this->assertTrue( \Two_Factor_Core::is_user_using_two_factor( $id ), 'a reset keeps the second factor' );
		$this->assertSame( array(), \WP_Session_Tokens::get_instance( $id )->get_all(), 'a reset signs nobody in' );
		$this->assertSame( 0, get_current_user_id() );
		// The next sign-in still goes through the plugin's second step, because the filter is still attached.
		$this->assertSame( 31, has_filter( 'authenticate', array( \Two_Factor_Core::class, 'filter_authenticate' ) ) );
		// And a user who has not enrolled is still held at the enrolment screen after a reset.
		$other = $this->staff( 'wiw_editor', 'tfreset2' );
		reset_password( get_user_by( 'id', $other ), 'Another-Strong-Pass-8-Word' );
		$this->assertSame( Enforcement::ENROL, Enforcement::for_site()->decide( $other, 'index.php' ) );
	}

	public function test_REQ_047_recovery_code_single_use(): void {
		$id = $this->staff( 'administrator', 'tfcodes' );
		$this->enrol( $id );
		$user     = get_user_by( 'id', $id );
		$provider = \Two_Factor_Backup_Codes::get_instance();
		$codes    = $provider->generate_codes( $user, array( 'number' => 3 ) );
		$this->assertCount( 3, $codes, 'recovery codes are issued at set-up' );
		$this->assertSame( 3, \Two_Factor_Backup_Codes::codes_remaining_for_user( $user ) );

		$this->assertTrue( $provider->validate_code( $user, $codes[0] ), 'a recovery code works once' );
		$this->assertFalse( $provider->validate_code( $user, $codes[0] ), 'and not a second time' );
		$this->assertSame( 2, \Two_Factor_Backup_Codes::codes_remaining_for_user( $user ) );
		$this->assertFalse( $provider->validate_code( $user, 'not-a-code' ) );
	}

	public function test_REQ_047_lockout_after_repeated_failures_holds_for_enrolled_staff(): void {
		foreach ( self::ROLES as $role ) {
			$login = 'tflock' . substr( md5( $role ), 0, 6 );
			$id    = $this->staff( $role, $login );
			$this->enrol( $id );
			for ( $i = 0; $i < Login::MAX_FAILURES; $i++ ) {
				$this->assertInstanceOf( \WP_Error::class, wp_authenticate( $login, 'wrong-password' ) );
			}
			$this->assertTrue( Login::is_locked( $login ), $role );
			$this->assertInstanceOf( \WP_Error::class, wp_authenticate( $login, self::PASSWORD ), "$role: the right password is refused while locked" );
			Login::unlock( $login );
		}
	}

	public function test_REQ_047_idle_timeout_is_sixty_minutes(): void {
		$this->assertSame( 3600, Login::IDLE_LIMIT );
		$this->assertFalse( Login::is_idle( 1000, 1000 + 3599 ) );
		$this->assertTrue( Login::is_idle( 1000, 1000 + 3601 ) );
	}
}
