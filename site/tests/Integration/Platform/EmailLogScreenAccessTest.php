<?php
/**
 * Who may open the Email log screen (REQ-075, REQ-240): the authorisation matrix, one line for this screen. Needs the
 * WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Mail\Admin\EmailLogScreen;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class EmailLogScreenAccessTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
	}

	public function test_REQ_075_email_log_requires_capability(): void {
		$registry = new ScreenRegistry();
		$registry->register( EmailLogScreen::SCREEN, 'Email log', EmailLogScreen::CAP, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), EmailLogScreen::CAP, 'wiw_review_records' );
		AuthorisationMatrix::assert_statuses(
			$this,
			EmailLogScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 403,
				'editor'        => 403,
				'administrator' => 200,
				'granted'       => 200,
				'revoked'       => 403,
			)
		);
		$this->assertNotContains( EmailLogScreen::SCREEN, AuthorisationMatrix::menu( $users['reviewer'] ) );
		$this->assertContains( EmailLogScreen::SCREEN, AuthorisationMatrix::menu( $users['administrator'] ) );
	}
}
