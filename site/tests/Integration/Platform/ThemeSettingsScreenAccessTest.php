<?php
/**
 * Who may open the Theme settings screen (REQ-272, REQ-188): the authorisation matrix, one line for this screen. Needs the
 * WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class ThemeSettingsScreenAccessTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
	}

	public function test_REQ_272_theme_settings_is_for_the_administrator_only(): void {
		$registry = new ScreenRegistry();
		$registry->register( WIW_THEME_SETTINGS_SCREEN, 'Theme settings', WIW_THEME_SETTINGS_CAP, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), WIW_THEME_SETTINGS_CAP, 'wiw_review_records' );
		AuthorisationMatrix::assert_statuses(
			$this,
			WIW_THEME_SETTINGS_SCREEN,
			$users,
			array(
				'reviewer'      => 403,
				'editor'        => 403,
				'administrator' => 200,
			)
		);
		$this->assertNotContains( WIW_THEME_SETTINGS_SCREEN, AuthorisationMatrix::menu( $users['reviewer'] ) );
		$this->assertContains( WIW_THEME_SETTINGS_SCREEN, AuthorisationMatrix::menu( $users['administrator'] ) );
	}
}
