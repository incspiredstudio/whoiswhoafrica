<?php
/**
 * Who may open the Enquiries screen (REQ-089, REQ-244): the authorisation matrix, one line for this screen. Needs the WordPress
 * test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Compliance\Admin\EnquiriesScreen;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class EnquiriesScreenAccessTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
	}

	public function test_REQ_089_enquiries_screen_requires_wiw_support_inbox(): void {
		$registry = new ScreenRegistry();
		$registry->register( EnquiriesScreen::SCREEN, 'Enquiries', EnquiriesScreen::CAP, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), EnquiriesScreen::CAP, EnquiriesScreen::CAP );
		AuthorisationMatrix::assert_statuses(
			$this,
			EnquiriesScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 403,
				'editor'        => 200,
				'administrator' => 200,
				'granted'       => 200,
				'revoked'       => 403,
			)
		);
		$this->assertNotContains( EnquiriesScreen::SCREEN, AuthorisationMatrix::menu( $users['reviewer'] ) );
		$this->assertContains( EnquiriesScreen::SCREEN, AuthorisationMatrix::menu( $users['editor'] ) );
	}
}
