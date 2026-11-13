<?php
/**
 * Who may open the Audit log screen (REQ-051, REQ-240): one authorisation matrix line. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\Admin\AuditScreen;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class AuditScreenAccessTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
	}

	public function test_REQ_051_audit_log_screen_gives_a_reviewer_403(): void {
		$registry = new ScreenRegistry();
		$registry->register( AuditScreen::SCREEN, 'Audit log', AuditScreen::CAP, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), AuditScreen::CAP, 'wiw_review_records' );
		AuthorisationMatrix::assert_statuses(
			$this,
			AuditScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 403,
				'editor'        => 403,
				'administrator' => 200,
				'granted'       => 200,
				'revoked'       => 403,
			)
		);
	}
}
