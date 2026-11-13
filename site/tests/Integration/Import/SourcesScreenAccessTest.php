<?php
/**
 * The Sources screen authorisation line (REQ-243). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Import;

use WhoIsWho\Core\Import\Sources\SourcesScreen;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class SourcesScreenAccessTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
	}

	public function test_REQ_243_sources_screen_denies_without_capability(): void {
		$registry = new ScreenRegistry();
		$registry->register( SourcesScreen::SCREEN, 'Sources', SourcesScreen::CAPABILITY, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), SourcesScreen::CAPABILITY, SourcesScreen::CAPABILITY );
		AuthorisationMatrix::assert_statuses(
			$this,
			SourcesScreen::SCREEN,
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
