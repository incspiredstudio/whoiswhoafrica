<?php
/**
 * Sign-in protection (REQ-047, REQ-235). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Security;

use WhoIsWho\Guard\Login\Login;
use WhoIsWho\Tests\WpTestCase;

final class LoginProtectionTest extends WpTestCase {

	private const PASSWORD = 'Correct-Horse-9-Battery';

	private function staff_user( string $login ): int {
		return self::factory()->user->create(
			array(
				'user_login' => $login,
				'user_pass'  => self::PASSWORD,
				'role'       => 'administrator',
			)
		);
	}

	public function test_REQ_047_five_failed_logins_lock_the_account(): void {
		$this->staff_user( 'locktest' );
		for ( $i = 0; $i < Login::MAX_FAILURES; $i++ ) {
			$this->assertInstanceOf( \WP_Error::class, wp_authenticate( 'locktest', 'wrong-password' ) );
		}
		$this->assertTrue( Login::is_locked( 'locktest' ) );

		$result = wp_authenticate( 'locktest', self::PASSWORD );
		$this->assertInstanceOf( \WP_Error::class, $result, 'the right password is refused while locked' );

		Login::unlock( 'locktest' );
		$this->assertInstanceOf( \WP_User::class, wp_authenticate( 'locktest', self::PASSWORD ), 'unlocking restores sign-in' );
	}

	public function test_REQ_047_lockout_event_carries_the_account_id_only(): void {
		$id     = $this->staff_user( 'idtest' );
		$logged = array();
		add_action(
			'wiw_login_locked',
			static function ( $user_id ) use ( &$logged ): void {
				$logged[] = $user_id;
			}
		);
		for ( $i = 0; $i < Login::MAX_FAILURES; $i++ ) {
			wp_authenticate( 'idtest', 'nope' );
		}
		$this->assertSame( array( $id ), $logged );
	}

	public function test_REQ_047_weak_password_is_refused(): void {
		$this->assertNotEmpty( Login::password_problems( 'short1A!' ), 'too short' );
		$this->assertNotEmpty( Login::password_problems( 'alllowercaseletters' ), 'one character class' );
		$this->assertNotEmpty( Login::password_problems( 'Password12345!' ), 'common word' );
		$this->assertSame( array(), Login::password_problems( self::PASSWORD ) );

		$errors = new \WP_Error();
		$user   = (object) array( 'user_pass' => 'weak' );
		Login::check_profile_password( $errors, true, $user );
		$this->assertTrue( $errors->has_errors() );
	}

	public function test_REQ_047_login_errors_are_generic(): void {
		$this->staff_user( 'generic' );
		$unknown = wp_authenticate( 'nobody-here', 'whatever-123-ABC' );
		$wrong   = wp_authenticate( 'generic', 'wrong-password-1' );
		$this->assertInstanceOf( \WP_Error::class, $unknown );
		$this->assertInstanceOf( \WP_Error::class, $wrong );
		$this->assertSame( $unknown->get_error_codes(), $wrong->get_error_codes() );
		$this->assertSame( $unknown->get_error_message(), $wrong->get_error_message() );
	}

	public function test_REQ_047_idle_sessions_end_after_the_limit(): void {
		$now = 2000000;
		$this->assertFalse( Login::is_idle( $now - 3000, $now ) );
		$this->assertTrue( Login::is_idle( $now - 3601, $now ) );
		$this->assertFalse( Login::is_idle( 0, $now ), 'no activity recorded yet' );
		$this->assertSame( 3600, Login::IDLE_LIMIT );
	}

	public function test_REQ_047_a_new_sign_in_resets_a_stale_idle_clock(): void {
		$id = $this->staff_user( 'staleclock' );
		update_user_meta( $id, Login::META_ACTIVE, time() - 86400 );

		do_action( 'wp_login', 'staleclock', new \WP_User( $id ) );

		$this->assertFalse( Login::is_idle( (int) get_user_meta( $id, Login::META_ACTIVE, true ), time() ), 'the old session\'s timestamp must not end the new one' );
	}

	public function test_REQ_235_sessions_are_revoked_when_the_role_changes(): void {
		$id     = $this->staff_user( 'revoke' );
		$tokens = \WP_Session_Tokens::get_instance( $id );
		$tokens->create( time() + 3600 );
		$this->assertNotEmpty( $tokens->get_all() );

		( new \WP_User( $id ) )->set_role( 'wiw_editor' );
		$this->assertEmpty( \WP_Session_Tokens::get_instance( $id )->get_all(), 'role change signs the user out everywhere' );
	}
}
