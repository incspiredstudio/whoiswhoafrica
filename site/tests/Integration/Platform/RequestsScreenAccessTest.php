<?php
/**
 * Authorisation matrix for the Requests and reports screen (REQ-270).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Compliance\Admin\Requests\RequestsScreen;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class RequestsScreenAccessTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
	}

	public function test_REQ_270_requests_screen_returns_403_without_capability(): void {
		$registry = new ScreenRegistry();
		$registry->register( RequestsScreen::SCREEN, 'Requests and reports', RequestsScreen::CAP, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), RequestsScreen::CAP, RequestsScreen::CAP );
		AuthorisationMatrix::assert_statuses(
			$this,
			RequestsScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 403,
				'editor'        => 200,
				'administrator' => 200,
				'granted'       => 200,
				'revoked'       => 403,
			)
		);
		$this->assertNotContains( RequestsScreen::SCREEN, AuthorisationMatrix::menu( $users['reviewer'] ) );
		$this->assertContains( RequestsScreen::SCREEN, AuthorisationMatrix::menu( $users['editor'] ) );
	}
}
