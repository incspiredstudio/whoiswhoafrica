<?php
/**
 * Two-factor enforcement with the real Two Factor plugin loaded (REQ-047, REQ-235).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\TwoFactor\Enforcement;
use WhoIsWho\Core\Platform\TwoFactor\PluginStatus;
use WhoIsWho\Core\Platform\TwoFactor\SecondFactorStatus;
use WhoIsWho\Core\Platform\TwoFactor\TestMode;
use WhoIsWho\Core\Platform\TwoFactor\TwoFactor;
use WhoIsWho\Tests\WpTestCase;

/**
 * Two-factor enforcement.
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
	 * A user with a given second-factor state.
	 *
	 * @param bool $enrolled Whether the user has set up an authenticator app.
	 */
	private function enforcement_with( bool $enrolled ): Enforcement {
		$status = new class( $enrolled ) implements SecondFactorStatus {
			/**
			 * Enrolled flag.
			 *
			 * @var bool
			 */
			private bool $enrolled;

			public function __construct( bool $enrolled ) {
				$this->enrolled = $enrolled;
			}

			public function available(): bool {
				return true;
			}

			public function is_enrolled( int $user_id ): bool {
				return $this->enrolled;
			}
		};
		return new Enforcement( $status );
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

	public function test_REQ_047_staff_without_second_factor_cannot_reach_admin(): void {
		$enforcement = $this->enforcement_with( false );
		foreach ( array( 'index.php', 'edit.php', 'users.php', 'options-general.php', 'admin.php', 'plugins.php' ) as $page ) {
			$this->assertSame( Enforcement::ENROL, $enforcement->decide( 5, $page ), $page );
		}
		$this->assertSame( Enforcement::ENROL, $enforcement->decide( 5, 'admin-ajax.php' ) );
		$this->assertSame( Enforcement::ENROL, $enforcement->decide( 5, '', 0, '/wp/v2/posts' ) );
	}

	public function test_REQ_047_enrolment_screens_stay_open_so_the_user_can_set_up(): void {
		$enforcement = $this->enforcement_with( false );
		$this->assertSame( Enforcement::ALLOW, $enforcement->decide( 5, 'profile.php' ) );
		$this->assertSame( Enforcement::ALLOW, $enforcement->decide( 5, 'user-edit.php', 5 ) );
		$this->assertSame( Enforcement::ENROL, $enforcement->decide( 5, 'user-edit.php', 9 ), 'another user\'s profile is not open' );
		$this->assertSame( Enforcement::ALLOW, $enforcement->decide( 5, '', 0, '/two-factor/1.0/generate-backup-codes' ) );
	}

	public function test_REQ_047_an_enrolled_user_reaches_admin(): void {
		$this->assertSame( Enforcement::ALLOW, $this->enforcement_with( true )->decide( 5, 'index.php' ) );
	}

	public function test_REQ_047_enforcement_applies_to_new_custom_role(): void {
		add_role( 'wiw_custom_test_role', 'Custom', array( 'read' => true ) );
		$user_id = self::factory()->user->create( array( 'role' => 'wiw_custom_test_role' ) );
		$real    = Enforcement::for_site();
		$this->assertSame( Enforcement::ENROL, $real->decide( $user_id, 'index.php' ) );
		$this->enrol( $user_id );
		$this->assertSame( Enforcement::ALLOW, $real->decide( $user_id, 'index.php' ) );
	}

	public function test_REQ_047_recovery_codes_alone_do_not_count_as_a_second_factor(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		update_user_meta( $user_id, '_two_factor_enabled_providers', array( 'Two_Factor_Backup_Codes' ) );
		update_user_meta( $user_id, '_two_factor_backup_codes', array( wp_hash_password( '12345678' ) ) );
		$this->assertFalse( ( new PluginStatus() )->is_enrolled( $user_id ) );
	}

	public function test_REQ_047_a_missing_plugin_means_no_entry_not_open_doors(): void {
		$status = new class() implements SecondFactorStatus {
			public function available(): bool {
				return false;
			}

			public function is_enrolled( int $user_id ): bool {
				return false;
			}
		};
		$this->assertSame( Enforcement::ENROL, ( new Enforcement( $status ) )->decide( 5, 'index.php' ) );
		$this->assertSame( Enforcement::ENROL, ( new Enforcement( $status ) )->decide( 5, 'profile.php' ) );
	}

	public function test_REQ_047_signed_out_visitors_are_not_touched(): void {
		$this->assertSame( Enforcement::ALLOW, $this->enforcement_with( false )->decide( 0, 'index.php' ) );
	}

	public function test_REQ_047_rest_is_refused_until_enrolled(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/posts';
		$result                                  = Enforcement::for_site()->guard_rest( null );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
		$this->enrol( $user_id );
		$this->assertNull( Enforcement::for_site()->guard_rest( null ) );
		unset( $GLOBALS['wp']->query_vars['rest_route'] );
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
		$this->assertSame( Enforcement::ENROL, Enforcement::for_site()->decide( $user_id, 'index.php' ) );
		$config = (string) file_get_contents( dirname( __DIR__, 3 ) . '/wp-config.php' );
		$this->assertStringContainsString( "! \$wiw_production && 'true' === wiw_secret( 'WIW_E2E_TEST_MODE'", $config );
	}
}
