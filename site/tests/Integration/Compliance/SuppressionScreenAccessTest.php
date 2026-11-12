<?php
/**
 * The Suppression list screen authorisation line (REQ-207, REQ-244). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Admin\Suppression\SuppressionScreen;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class SuppressionScreenAccessTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
	}

	public function test_REQ_207_suppression_screen_denies_without_capability(): void {
		$registry = new ScreenRegistry();
		$registry->register( SuppressionScreen::SCREEN, 'Suppression list', SuppressionScreen::CAPABILITY, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), SuppressionScreen::CAPABILITY, SuppressionScreen::CAPABILITY );
		AuthorisationMatrix::assert_statuses(
			$this,
			SuppressionScreen::SCREEN,
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
}
