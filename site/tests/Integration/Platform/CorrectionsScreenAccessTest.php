<?php
/**
 * Authorisation matrix for the Corrections screen (REQ-244, REQ-271). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Compliance\Admin\Corrections\CorrectionsScreen;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class CorrectionsScreenAccessTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
	}

	public function test_REQ_244_corrections_screen_returns_403_without_capability(): void {
		$registry = new ScreenRegistry();
		$registry->register( CorrectionsScreen::SCREEN, 'Corrections', CorrectionsScreen::CAP, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), CorrectionsScreen::CAP, CorrectionsScreen::CAP );
		AuthorisationMatrix::assert_statuses(
			$this,
			CorrectionsScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 403,
				'editor'        => 200,
				'administrator' => 200,
				'granted'       => 200,
				'revoked'       => 403,
			)
		);
		$this->assertNotContains( CorrectionsScreen::SCREEN, AuthorisationMatrix::menu( $users['reviewer'] ) );
		$this->assertContains( CorrectionsScreen::SCREEN, AuthorisationMatrix::menu( $users['editor'] ) );
	}
}
