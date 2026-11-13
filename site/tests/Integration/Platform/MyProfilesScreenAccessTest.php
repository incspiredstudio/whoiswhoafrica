<?php
/**
 * Who may open My profiles, and what the limited role can reach (REQ-261, REQ-244): the authorisation matrix, one line for the
 * screen, plus a check that a limited profile manager gets 403 on the other staff screens and a menu with only My profiles. The
 * role exists only here; the plugin ships none. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Compliance\Admin\EnquiriesScreen;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Review\Admin\ReviewQueueScreen;
use WhoIsWho\Core\Trust\Admin\ClaimsScreen;
use WhoIsWho\Core\Trust\Admin\MyProfilesScreen;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class MyProfilesScreenAccessTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		add_role(
			'wiw_test_profile_manager',
			'Profile manager (test)',
			array(
				'read'                => true,
				'upload_files'        => true,
				MyProfilesScreen::CAP => true,
			)
		);
	}

	public function test_REQ_261_my_profiles_requires_the_assigned_capability(): void {
		$registry = new ScreenRegistry();
		$registry->register( MyProfilesScreen::SCREEN, 'My profiles', MyProfilesScreen::CAP, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), MyProfilesScreen::CAP, MyProfilesScreen::CAP );
		AuthorisationMatrix::assert_statuses(
			$this,
			MyProfilesScreen::SCREEN,
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

	public function test_REQ_261_limited_role_cannot_open_other_admin_screens(): void {
		$registry = new ScreenRegistry();
		$registry->register( MyProfilesScreen::SCREEN, 'My profiles', MyProfilesScreen::CAP, '__return_null' );
		$registry->register( EnquiriesScreen::SCREEN, 'Enquiries', EnquiriesScreen::CAP, '__return_null' );
		$registry->register( ReviewQueueScreen::SCREEN, 'Review queue', ReviewQueueScreen::CAPABILITY, '__return_null' );
		$registry->register( ClaimsScreen::SCREEN, 'Claims', ClaimsScreen::CAP, '__return_null' );
		$registry->hook();
		$manager = self::factory()->user->create( array( 'role' => 'wiw_test_profile_manager' ) );

		$this->assertSame( 200, AuthorisationMatrix::status( $manager, MyProfilesScreen::SCREEN ) );
		foreach ( array( EnquiriesScreen::SCREEN, ReviewQueueScreen::SCREEN, ClaimsScreen::SCREEN ) as $slug ) {
			$this->assertSame( 403, AuthorisationMatrix::status( $manager, $slug ), $slug . ' answers 403 to the limited role.' );
		}
		$this->assertSame( array( MyProfilesScreen::SCREEN ), AuthorisationMatrix::menu( $manager ), 'The menu holds only My profiles.' );
	}
}
