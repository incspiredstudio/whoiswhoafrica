<?php
/**
 * The Add profile screen authorisation line (REQ-244, REQ-240). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Admin\AddProfileScreen;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class AddProfileScreenAccessTest extends WpTestCase {

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

	public function test_REQ_244_add_profile_screen_denies_without_capability(): void {
		$this->assertSame( 'wiw_add_profile', AddProfileScreen::CAPABILITY );
		$registry = new ScreenRegistry();
		$registry->register( AddProfileScreen::SCREEN, 'Add profile', AddProfileScreen::CAPABILITY, '__return_null', false, 'directory' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), AddProfileScreen::CAPABILITY, AddProfileScreen::CAPABILITY );
		AuthorisationMatrix::assert_statuses(
			$this,
			AddProfileScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 403,
				'editor'        => 200,
				'administrator' => 200,
				'granted'       => 200,
				'revoked'       => 403,
			)
		);
	}

	public function test_REQ_244_add_profile_action_needs_capability_and_nonce(): void {
		$reviewer = self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) );
		$editor   = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );

		wp_set_current_user( $reviewer );
		$request = new Request( array( '_wpnonce' => ActionGuard::nonce_for( AddProfileScreen::ACTION ) ) );
		$this->assertSame( ActionGuard::REFUSED_CAPABILITY, ActionGuard::check( AddProfileScreen::ACTION, AddProfileScreen::CAPABILITY, $request ) );

		wp_set_current_user( $editor );
		$this->assertSame( ActionGuard::REFUSED_NONCE, ActionGuard::check( AddProfileScreen::ACTION, AddProfileScreen::CAPABILITY, new Request( array( '_wpnonce' => 'bad' ) ) ) );
		$request = new Request( array( '_wpnonce' => ActionGuard::nonce_for( AddProfileScreen::ACTION ) ) );
		$this->assertNull( ActionGuard::check( AddProfileScreen::ACTION, AddProfileScreen::CAPABILITY, $request ) );
	}

	public function test_REQ_244_screen_markup_refuses_a_user_without_the_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) ) );
		$this->expectException( \DomainException::class );
		( new AddProfileScreen() )->html( new Request( array() ) );
	}
}
