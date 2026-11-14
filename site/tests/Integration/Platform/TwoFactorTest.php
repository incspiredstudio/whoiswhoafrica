<?php
/**
 * Two-factor with the real Two Factor plugin loaded (REQ-047, REQ-235): optional, available, never blocking (D-070).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\TwoFactor\PluginStatus;
use WhoIsWho\Core\Platform\TwoFactor\TestMode;
use WhoIsWho\Core\Platform\TwoFactor\TwoFactor;
use WhoIsWho\Tests\WpTestCase;

/**
 * Two-factor: optional and available.
 */
final class TwoFactorTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( 0 );
		remove_role( 'wiw_custom_test_role' );
	}

	protected function tearDown(): void {
		remove_role( 'wiw_custom_test_role' );
		parent::tearDown();
	}

	/**
	 * Give a user a working authenticator app, the way the plugin stores it.
	 *
	 * @param int $user_id User id.
	 */
	private function enrol( int $user_id ): void {
		update_user_meta( $user_id, '_two_factor_enabled_providers', array( 'Two_Factor_Totp' ) );
		update_user_meta( $user_id, '_two_factor_totp_key', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567' );
	}

	public function test_REQ_047_the_two_factor_plugin_is_loaded_by_the_core_plugin(): void {
		$this->assertTrue( ( new PluginStatus() )->available() );
		$this->assertSame( '0.17.0', TWO_FACTOR_VERSION );
	}

	public function test_REQ_047_two_factor_is_optional_staff_without_it_are_not_redirected_or_blocked(): void {
		$this->assertFalse( class_exists( '\WhoIsWho\Core\Platform\TwoFactor\Enforcement' ), 'the enforcement class is gone' );
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$this->assertFalse( ( new PluginStatus() )->is_enrolled( $user_id ) );
		TwoFactor::register();
		foreach ( $GLOBALS['wp_filter']['admin_init']->callbacks ?? array() as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$owner = is_array( $callback['function'] ) ? $callback['function'][0] : '';
				$name  = is_object( $owner ) ? get_class( $owner ) : (string) $owner;
				$this->assertStringNotContainsString( 'Platform\TwoFactor', $name, 'no admin redirect for two-factor' );
			}
		}
		$GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/posts';
		$this->assertNull( apply_filters( 'rest_authentication_errors', null ), 'REST is not refused for want of two-factor' );
		unset( $GLOBALS['wp']->query_vars['rest_route'] );
	}

	public function test_REQ_047_two_factor_is_available_and_a_user_who_switches_it_on_is_asked_for_it(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->assertTrue( ( new PluginStatus() )->available() );
		$this->enrol( $user_id );
		$this->assertTrue( ( new PluginStatus() )->is_enrolled( $user_id ) );
		$this->assertTrue( \Two_Factor_Core::is_user_using_two_factor( $user_id ), 'the plugin asks for the second step at sign-in' );
	}

	public function test_REQ_047_recovery_codes_alone_do_not_count_as_a_second_factor(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		update_user_meta( $user_id, '_two_factor_enabled_providers', array( 'Two_Factor_Backup_Codes' ) );
		update_user_meta( $user_id, '_two_factor_backup_codes', array( wp_hash_password( '12345678' ) ) );
		$this->assertFalse( ( new PluginStatus() )->is_enrolled( $user_id ) );
	}

	public function test_REQ_047_recovery_codes_and_authenticator_are_offered_and_the_debug_method_is_not(): void {
		$providers = array_keys( \Two_Factor_Core::get_providers() );
		$this->assertContains( 'Two_Factor_Backup_Codes', $providers );
		$this->assertContains( 'Two_Factor_Totp', $providers );
		$this->assertNotContains( 'Two_Factor_Dummy', $providers );
	}

	public function test_REQ_047_settings_are_applied_by_code(): void {
		delete_option( TwoFactor::OPTION_APPLIED );
		delete_option( \Two_Factor_Core::ENABLED_PROVIDERS_OPTION_KEY );
		TwoFactor::apply_settings();
		$this->assertSame( TwoFactor::PROVIDERS, get_option( \Two_Factor_Core::ENABLED_PROVIDERS_OPTION_KEY ) );
		$this->assertSame( TwoFactor::SETTINGS_REV, get_option( TwoFactor::OPTION_APPLIED ) );
	}

	public function test_REQ_047_test_mode_hook_cannot_exist_in_production(): void {
		$this->assertFalse( TestMode::enabled(), 'the constant is not set in the test suite' );
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		update_user_meta( $user_id, TestMode::META, '1' );
		$this->assertFalse( TestMode::exempts( $user_id ), 'the marker alone exempts nobody' );
		$config = (string) file_get_contents( dirname( __DIR__, 3 ) . '/wp-config.php' );
		$this->assertStringContainsString( "! \$wiw_production && 'true' === wiw_secret( 'WIW_E2E_TEST_MODE'", $config );
	}
}
