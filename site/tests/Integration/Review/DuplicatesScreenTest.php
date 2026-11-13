<?php
/**
 * The Duplicates screen (REQ-244, REQ-146): the authorisation matrix line and the side-by-side markup. Needs the WordPress
 * test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Review;

use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Review\Admin\DuplicatesScreen;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class DuplicatesScreenTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
	}

	public function test_REQ_244_duplicates_screen_denies_without_capability(): void {
		$registry = new ScreenRegistry();
		$registry->register( DuplicatesScreen::SCREEN, 'Duplicates', DuplicatesScreen::CAPABILITY, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), DuplicatesScreen::CAPABILITY, DuplicatesScreen::CAPABILITY );
		AuthorisationMatrix::assert_statuses(
			$this,
			DuplicatesScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 200,
				'editor'        => 200,
				'administrator' => 200,
				'granted'       => 200,
				'revoked'       => 403,
			)
		);
	}

	public function test_REQ_244_the_screen_refuses_a_user_without_the_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->expectException( \DomainException::class );
		( new DuplicatesScreen() )->html();
	}

	public function test_REQ_146_the_screen_shows_an_empty_state_and_no_automatic_merge(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) ) );
		$html = ( new DuplicatesScreen() )->html();
		$this->assertStringContainsString( 'Nothing is merged unless you do it here', $html );
	}
}
