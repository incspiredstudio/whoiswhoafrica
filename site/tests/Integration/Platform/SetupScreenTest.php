<?php
/**
 * The Setup screen (REQ-056, REQ-240, REQ-244): administrators only, every action behind a capability and a nonce, a live checklist
 * that never shows a secret, and the one-click fixes. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Setup\Checklist;
use WhoIsWho\Core\Platform\Setup\FirstRun;
use WhoIsWho\Core\Platform\Setup\SetupScreen;
use WhoIsWho\Core\Platform\TwoFactor\SecondFactorStatus;
use WhoIsWho\Core\Platform\TwoFactor\StaffStatus;
use WhoIsWho\Core\Platform\Mail\Transport;
use WhoIsWho\Core\Compliance\Forms\TurnstileSettings;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class SetupScreenTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_REQ_244_setup_screen_is_for_administrators_only(): void {
		$registry = new ScreenRegistry();
		$registry->register( SetupScreen::SCREEN, 'Setup', SetupScreen::CAP, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), SetupScreen::CAP, 'wiw_review_records' );
		AuthorisationMatrix::assert_statuses(
			$this,
			SetupScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 403,
				'editor'        => 403,
				'administrator' => 200,
				'granted'       => 403, // A fixed capability cannot be granted to a non-Administrator.
				'revoked'       => 403,
			)
		);
	}

	public function test_REQ_240_the_catalogue_gives_setup_to_the_administrator_alone(): void {
		$catalogue = new CapabilityCatalogue();
		$this->assertContains( SetupScreen::CAP, $catalogue->for_bundle( 'administrator' ) );
		$this->assertNotContains( SetupScreen::CAP, $catalogue->for_bundle( 'editor' ) );
		$this->assertNotContains( SetupScreen::CAP, $catalogue->for_bundle( 'reviewer' ) );
		$this->assertContains( SetupScreen::CAP, $catalogue->fixed() );
	}

	public function test_REQ_240_every_setup_action_needs_the_capability_and_a_nonce(): void {
		$editor = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		foreach ( array( SetupScreen::ACTION_FIX, SetupScreen::ACTION_START, SetupScreen::ACTION_STEP, SetupScreen::ACTION_REMOVE, SetupScreen::ACTION_TURNSTILE ) as $action ) {
			wp_set_current_user( $editor );
			$nonce = ActionGuard::nonce_for( $action );
			$this->assertSame( ActionGuard::REFUSED_CAPABILITY, ActionGuard::check( $action, SetupScreen::CAP, new Request( array( '_wpnonce' => $nonce ) ) ), "{$action} for an editor" );
			wp_set_current_user( $admin );
			$this->assertSame( ActionGuard::REFUSED_NONCE, ActionGuard::check( $action, SetupScreen::CAP, new Request( array() ) ), "{$action} without a nonce" );
			$this->assertNull( ActionGuard::check( $action, SetupScreen::CAP, new Request( array( '_wpnonce' => ActionGuard::nonce_for( $action ) ) ) ), "{$action} for an administrator" );
		}
	}

	public function test_REQ_056_the_checklist_has_the_ten_checks_in_order(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$items = Checklist::items();
		$this->assertSame( array( 'database', 'permalinks', 'theme', 'guard', 'two_factor', 'email', 'turnstile', 'plugin_fluent_smtp', 'plugin_wpdiscuz', 'demo' ), array_column( $items, 'id' ) );
		foreach ( $items as $item ) {
			$this->assertContains( $item['status'], array( Checklist::OK, Checklist::WARN, Checklist::ERROR ) );
			$this->assertNotSame( '', $item['detail'] );
			if ( Checklist::OK !== $item['status'] ) {
				$this->assertNotSame( '', $item['fix'] . $item['action_label'], "{$item['id']} says what to do" );
			}
		}
	}

	public function test_REQ_056_plain_permalinks_show_a_fix_button_and_pretty_ones_are_ready(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		global $wp_rewrite;
		$before = get_option( 'permalink_structure' );
		try {
			$wp_rewrite->set_permalink_structure( '' );
			$item = Checklist::items()[1];
			$this->assertSame( Checklist::ERROR, $item['status'] );
			$this->assertSame( 'permalinks', $item['action'] );
			$this->assertSame( 'Fix permalinks', $item['action_label'] );
			$wp_rewrite->set_permalink_structure( '/%postname%/' );
			$this->assertSame( Checklist::OK, Checklist::items()[1]['status'] );
		} finally {
			$wp_rewrite->set_permalink_structure( (string) $before );
		}
	}

	public function test_REQ_056_the_screen_lists_every_step_and_the_demo_buttons_and_hides_the_secrets(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$html = SetupScreen::html( new Request( array() ) );
		foreach ( array( 'Database ready', 'Pretty web addresses', 'Site theme active', 'Guard plugin', 'Two-factor sign-in', 'Email', 'Spam protection', 'Save Turnstile keys', 'Demo content', 'Import demo content', 'Remove demo content' ) as $text ) {
			$this->assertStringContainsString( $text, $html );
		}
		$this->assertStringContainsString( 'role="progressbar"', $html );
		$this->assertStringContainsString( 'aria-live="polite"', $html );
		$this->assertStringContainsString( 'invented content', $html );
		foreach ( array( 'WIW_SMTP_PASSWORD', 'WIW_SMTP_USER', 'WIW_SMTP_HOST' ) as $constant ) {
			if ( defined( $constant ) && '' !== (string) constant( $constant ) ) {
				$this->assertStringNotContainsString( (string) constant( $constant ), $html );
			}
		}
	}

	public function test_REQ_240_the_screen_refuses_a_user_without_the_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_editor' ) ) );
		$this->expectException( \DomainException::class );
		SetupScreen::html( new Request( array() ) );
	}

	public function test_REQ_056_the_dashboard_widget_shows_progress_and_a_way_to_hide_it(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$html = SetupScreen::widget_html();
		$this->assertMatchesRegularExpression( '/\d of 10 setup steps are ready/', $html );
		$this->assertStringContainsString( 'page=wiw-setup', $html );
		$this->assertStringContainsString( 'Hide this reminder', $html );
	}

	public function test_REQ_056_the_administrator_is_sent_to_setup_once_after_activation_but_only_when_they_can_open_it(): void {
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$editor = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		delete_option( FirstRun::REDIRECT );
		$this->assertNull( SetupScreen::redirect_target( $admin, 'index.php', false, false ), 'nothing was activated, so no redirect' );
		update_option( FirstRun::REDIRECT, '1', false );
		$this->assertStringContainsString( 'page=wiw-setup', (string) SetupScreen::redirect_target( $admin, 'index.php', false, false ) );
		$this->assertStringContainsString( 'page=wiw-setup', (string) SetupScreen::redirect_target( $admin, 'plugins.php', false, false ) );
		$this->assertNull( SetupScreen::redirect_target( $admin, 'admin-post.php', false, false ), 'never a form post' );
		$this->assertNull( SetupScreen::redirect_target( $admin, 'index.php', false, true ), 'never an AJAX call' );
		$this->assertNull( SetupScreen::redirect_target( $admin, 'plugins.php', true, false ), 'not when several plugins were activated at once' );
		$this->assertNull( SetupScreen::redirect_target( $editor, 'index.php', false, false ), 'not a user who may not open the screen' );
		delete_option( FirstRun::REDIRECT );
	}

	public function test_REQ_047_an_administrator_without_two_factor_is_still_sent_to_setup(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		update_option( FirstRun::REDIRECT, '1', false );
		$this->assertStringContainsString( 'page=wiw-setup', (string) SetupScreen::redirect_target( $admin, 'index.php', false, false ), 'two-factor is optional, so nothing holds them back' );
		delete_option( FirstRun::REDIRECT );
	}

	/**
	 * A two-factor status with a fixed set of enrolled users.
	 *
	 * @param int[] $enrolled Enrolled user ids.
	 */
	private function staff_status( array $enrolled ): StaffStatus {
		return new StaffStatus(
			new class( $enrolled ) implements SecondFactorStatus {
				/**
				 * Constructor.
				 *
				 * @param int[] $enrolled Ids.
				 */
				public function __construct( private array $enrolled ) {}
				public function available(): bool {
					return true;
				}
				public function is_enrolled( int $user_id ): bool {
					return in_array( $user_id, $this->enrolled, true );
				}
			}
		);
	}

	public function test_REQ_047_the_checklist_lists_which_staff_have_two_factor_on_as_advice_only(): void {
		foreach ( get_users( array( 'role__in' => StaffStatus::staff_roles() ) ) as $old ) {
			wp_delete_user( $old->ID );
		}
		$on  = self::factory()->user->create(
			array(
				'role'         => 'wiw_editor',
				'display_name' => 'Ada Example',
			)
		);
		$off = self::factory()->user->create(
			array(
				'role'         => 'administrator',
				'display_name' => 'Bo Example',
			)
		);
		self::factory()->user->create(
			array(
				'role'         => 'subscriber',
				'display_name' => 'Not Staff',
			)
		);

		$item = Checklist::two_factor( $this->staff_status( array( $on ) ) );
		$this->assertSame( Checklist::WARN, $item['status'], 'advice, never an error' );
		$this->assertStringContainsString( 'On for: Ada Example.', $item['detail'] );
		$this->assertStringContainsString( 'Not yet on for: Bo Example.', $item['detail'] );
		$this->assertStringNotContainsString( 'Not Staff', $item['detail'] );
		$this->assertStringContainsString( 'optional', $item['fix'] );
		$this->assertSame( '', $item['action'], 'no button forces anything' );

		$all = Checklist::two_factor( $this->staff_status( array( $on, $off ) ) );
		$this->assertSame( Checklist::OK, $all['status'] );
	}

	public function test_REQ_075_the_email_check_is_green_for_fluent_smtp_or_the_constants_and_links_to_fluent_smtp(): void {
		$before = get_option( 'active_plugins' );
		$bare   = new Transport(
			array(
				'host'       => '',
				'port'       => 587,
				'user'       => '',
				'password'   => '',
				'from'       => '',
				'hourly_cap' => 100,
			)
		);
		$set    = new Transport(
			array(
				'host'       => 'mail.example.org',
				'port'       => 587,
				'user'       => 'u',
				'password'   => 'p',
				'from'       => '',
				'hourly_cap' => 100,
			)
		);
		try {
			update_option( 'active_plugins', array() );
			delete_option( Transport::FLUENT_SMTP_OPTION );
			$none = Checklist::email( $bare );
			$this->assertSame( Checklist::WARN, $none['status'] );
			$this->assertStringContainsString( 'FluentSMTP', $none['fix'] );
			$this->assertStringContainsString( 'page=wiw-required-plugins', $none['link'] );

			$this->assertSame( Checklist::OK, Checklist::email( $set )['status'], 'the host set the constants' );

			update_option( 'active_plugins', array( Transport::FLUENT_SMTP ) );
			$half = Checklist::email( $bare );
			$this->assertSame( Checklist::WARN, $half['status'], 'installed but not connected' );
			$this->assertStringContainsString( 'page=fluent-mail', $half['link'] );

			update_option( Transport::FLUENT_SMTP_OPTION, array( 'connections' => array( 'x' => array() ) ) );
			$ready = Checklist::email( $bare );
			$this->assertSame( Checklist::OK, $ready['status'] );
			$this->assertStringContainsString( 'page=fluent-mail', $ready['link'] );
			foreach ( array( $none, $half, $ready ) as $item ) {
				$this->assertStringNotContainsString( 'wp-config', $item['detail'] . $item['fix'] );
				$this->assertStringNotContainsString( 'WIW_', $item['detail'] . $item['fix'] );
			}
		} finally {
			update_option( 'active_plugins', $before );
			delete_option( Transport::FLUENT_SMTP_OPTION );
		}
	}

	public function test_REQ_081_turnstile_keys_are_saved_by_the_administrator_and_the_secret_is_never_shown(): void {
		delete_option( TurnstileSettings::OPTION_SITE_KEY );
		delete_option( TurnstileSettings::OPTION_SECRET );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertFalse( TurnstileSettings::active(), 'off until both keys are saved' );

		$error = SetupScreen::save_turnstile( new Request( array( 'turnstile_site_key' => '0x4AAAAAAA-site_Key' ) ) );
		$this->assertStringContainsString( 'secret key', $error, 'a site key alone is refused' );
		$this->assertFalse( TurnstileSettings::active() );

		$this->assertNotSame(
			'',
			SetupScreen::save_turnstile(
				new Request(
					array(
						'turnstile_site_key' => 'bad key<script>',
						'turnstile_secret'   => 'x',
					)
				)
			)
		);

		$this->assertSame(
			'',
			SetupScreen::save_turnstile(
				new Request(
					array(
						'turnstile_site_key' => '0x4AAAAAAA-site_Key',
						'turnstile_secret'   => '0x4AAAAAAA-Very-Secret_Value',
					)
				)
			)
		);
		$this->assertTrue( TurnstileSettings::active() );
		$this->assertSame( '0x4AAAAAAA-Very-Secret_Value', TurnstileSettings::secret() );
		global $wpdb;
		$autoload = (string) $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", TurnstileSettings::OPTION_SECRET ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertContains( $autoload, array( 'no', 'off', 'auto-off' ), 'the secret is not autoloaded' );

		$html = SetupScreen::html( new Request( array() ) );
		$this->assertStringNotContainsString( 'Very-Secret', $html, 'the secret is never echoed back' );
		$this->assertStringContainsString( 'Saved. It is not shown again.', $html );
		$this->assertStringContainsString( '0x4AAAAAAA-site_Key', $html, 'the public site key is shown' );

		$this->assertSame( '', SetupScreen::save_turnstile( new Request( array( 'turnstile_site_key' => '0x4AAAAAAA-other' ) ) ), 'an empty secret keeps the saved one' );
		$this->assertSame( '0x4AAAAAAA-Very-Secret_Value', TurnstileSettings::secret() );

		$this->assertSame( '', SetupScreen::save_turnstile( new Request( array( 'turnstile_remove' => '1' ) ) ) );
		$this->assertFalse( TurnstileSettings::active() );
		$this->assertFalse( get_option( TurnstileSettings::OPTION_SECRET ) );
	}

	public function test_REQ_081_the_forms_use_turnstile_only_when_both_keys_are_saved(): void {
		delete_option( TurnstileSettings::OPTION_SITE_KEY );
		delete_option( TurnstileSettings::OPTION_SECRET );
		$token = \WhoIsWho\Core\Platform\Forms\FormToken::for_site();
		$this->assertInstanceOf( \WhoIsWho\Core\Platform\Forms\NullChallenge::class, TurnstileSettings::challenge( $token ) );
		update_option( TurnstileSettings::OPTION_SECRET, 'only-secret', false );
		$this->assertInstanceOf( \WhoIsWho\Core\Platform\Forms\NullChallenge::class, TurnstileSettings::challenge( $token ), 'a secret alone would refuse every form' );
		update_option( TurnstileSettings::OPTION_SITE_KEY, 'site', false );
		$this->assertInstanceOf( \WhoIsWho\Core\Compliance\Forms\TurnstileChallenge::class, TurnstileSettings::challenge( $token ) );
		delete_option( TurnstileSettings::OPTION_SITE_KEY );
		delete_option( TurnstileSettings::OPTION_SECRET );
	}

	public function test_REQ_056_setup_messages_have_no_developer_jargon(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$text = '';
		foreach ( Checklist::items() as $item ) {
			$text .= ' ' . $item['detail'] . ' ' . $item['fix'] . ' ' . $item['action_label'];
		}
		foreach ( \WhoIsWho\Core\Platform\Setup\SystemCheck::problems( '8.1.0', '6.0', array(), true ) as $problem ) {
			$text .= ' ' . $problem['message'] . ' ' . $problem['fix'];
		}
		foreach ( array( 'wp-config', 'WIW_', 'constant', 'secrets file', 'deploy script', 'command line', 'wp wiw', 'WP_ENVIRONMENT_TYPE', 'runbook', 'developer' ) as $jargon ) {
			$this->assertStringNotContainsStringIgnoringCase( $jargon, $text, $jargon );
		}
	}
}
