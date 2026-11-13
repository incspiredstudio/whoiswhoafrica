<?php
/**
 * Who may open the Customers screen (REQ-177, REQ-205): the authorisation matrix, one line for this screen. Needs the WordPress
 * test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Commerce\Admin\CustomersScreen;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class CustomersScreenAccessTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
	}

	public function test_REQ_177_customers_screen_requires_the_capability_and_answers_403_otherwise(): void {
		$registry = new ScreenRegistry();
		$registry->register( CustomersScreen::SCREEN, 'Customers', CustomersScreen::CAP, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), CustomersScreen::CAP, CustomersScreen::CAP );
		AuthorisationMatrix::assert_statuses(
			$this,
			CustomersScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 403,
				'editor'        => 200,
				'administrator' => 200,
				'granted'       => 200,
				'revoked'       => 403,
			)
		);
		$this->assertNotContains( CustomersScreen::SCREEN, AuthorisationMatrix::menu( $users['reviewer'] ) );
		$this->assertContains( CustomersScreen::SCREEN, AuthorisationMatrix::menu( $users['editor'] ) );
	}
}
