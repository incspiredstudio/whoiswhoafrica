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
use WhoIsWho\Core\Platform\TwoFactor\Enforcement;
use WhoIsWho\Core\Platform\TwoFactor\SecondFactorStatus;
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
		foreach ( array( SetupScreen::ACTION_FIX, SetupScreen::ACTION_START, SetupScreen::ACTION_STEP, SetupScreen::ACTION_REMOVE ) as $action ) {
			wp_set_current_user( $editor );
			$nonce = ActionGuard::nonce_for( $action );
			$this->assertSame( ActionGuard::REFUSED_CAPABILITY, ActionGuard::check( $action, SetupScreen::CAP, new Request( array( '_wpnonce' => $nonce ) ) ), "{$action} for an editor" );
			wp_set_current_user( $admin );
			$this->assertSame( ActionGuard::REFUSED_NONCE, ActionGuard::check( $action, SetupScreen::CAP, new Request( array() ) ), "{$action} without a nonce" );
			$this->assertNull( ActionGuard::check( $action, SetupScreen::CAP, new Request( array( '_wpnonce' => ActionGuard::nonce_for( $action ) ) ) ), "{$action} for an administrator" );
		}
	}

	public function test_REQ_056_the_checklist_has_the_seven_checks_in_order(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$items = Checklist::items();
		$this->assertSame( array( 'database', 'permalinks', 'theme', 'guard', 'two_factor', 'email', 'demo' ), array_column( $items, 'id' ) );
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
		foreach ( array( 'Database ready', 'Pretty web addresses', 'Site theme active', 'Guard plugin', 'Two-factor sign-in', 'Email settings', 'Demo content', 'Import demo content', 'Remove demo content' ) as $text ) {
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
		$this->assertMatchesRegularExpression( '/\d of 7 setup steps are ready/', $html );
		$this->assertStringContainsString( 'page=wiw-setup', $html );
		$this->assertStringContainsString( 'Hide this reminder', $html );
	}
	/**
	 * A two-factor status that says every user is enrolled, or none.
	 *
	 * @param bool $enrolled Answer.
	 */
	private function enforcement( bool $enrolled ): Enforcement {
		return new Enforcement(
			new class( $enrolled ) implements SecondFactorStatus {
				public function __construct( private bool $enrolled ) {}
				public function available(): bool {
					return true;
				}
				public function is_enrolled( int $user_id ): bool {
					return $this->enrolled && $user_id > 0;
				}
			}
		);
	}

	public function test_REQ_056_the_administrator_is_sent_to_setup_once_after_activation_but_only_when_they_can_open_it(): void {
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$editor = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		delete_option( FirstRun::REDIRECT );
		$this->assertNull( SetupScreen::redirect_target( $admin, 'index.php', false, false, $this->enforcement( true ) ), 'nothing was activated, so no redirect' );
		update_option( FirstRun::REDIRECT, '1', false );
		$this->assertStringContainsString( 'page=wiw-setup', (string) SetupScreen::redirect_target( $admin, 'index.php', false, false, $this->enforcement( true ) ) );
		$this->assertStringContainsString( 'page=wiw-setup', (string) SetupScreen::redirect_target( $admin, 'plugins.php', false, false, $this->enforcement( true ) ) );
		$this->assertNull( SetupScreen::redirect_target( $admin, 'index.php', false, false, $this->enforcement( false ) ), 'a user still being sent to enrol waits' );
		$this->assertNull( SetupScreen::redirect_target( $admin, 'admin-post.php', false, false, $this->enforcement( true ) ), 'never a form post' );
		$this->assertNull( SetupScreen::redirect_target( $admin, 'index.php', false, true, $this->enforcement( true ) ), 'never an AJAX call' );
		$this->assertNull( SetupScreen::redirect_target( $admin, 'plugins.php', true, false, $this->enforcement( true ) ), 'not when several plugins were activated at once' );
		$this->assertNull( SetupScreen::redirect_target( $editor, 'index.php', false, false, $this->enforcement( true ) ), 'not a user who may not open the screen' );
		delete_option( FirstRun::REDIRECT );
	}
}
