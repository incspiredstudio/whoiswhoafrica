<?php
/**
 * The Profile edit screen authorisation line (REQ-244). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Admin\ProfileEditScreen;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class ProfileEditScreenAccessTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
	}

	public function test_REQ_244_profile_edit_screen_denies_without_capability(): void {
		$registry = new ScreenRegistry();
		$registry->register( ProfileEditScreen::SCREEN, 'Edit profile', ProfileEditScreen::CAPABILITY, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), ProfileEditScreen::CAPABILITY, ProfileEditScreen::CAPABILITY );
		AuthorisationMatrix::assert_statuses(
			$this,
			ProfileEditScreen::SCREEN,
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
}
