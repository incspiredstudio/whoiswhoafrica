<?php
/**
 * Who may open the Import screen and see its switch (REQ-237, REQ-244). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Import;

use WhoIsWho\Core\Import\Admin\ImportScreen;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class ImportScreenAccessTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
	}

	public function test_REQ_237_import_screen_denies_without_capability(): void {
		$registry = new ScreenRegistry();
		$registry->register( ImportScreen::SCREEN, 'Import', ImportScreen::CAPABILITY, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), ImportScreen::CAPABILITY, 'wiw_review_records' );
		AuthorisationMatrix::assert_statuses(
			$this,
			ImportScreen::SCREEN,
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

	public function test_REQ_237_the_screen_refuses_a_user_without_the_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_editor' ) ) );
		$this->expectException( \DomainException::class );
		( new ImportScreen() )->html( new Request( array() ) );
	}

	public function test_REQ_237_the_switch_is_shown_to_administrators_only(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertStringContainsString( 'Import everything as not public', ( new ImportScreen() )->html( new Request( array() ) ) );

		$granted = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		get_userdata( $granted )->add_cap( ImportScreen::CAPABILITY );
		wp_set_current_user( $granted );
		$html = ( new ImportScreen() )->html( new Request( array() ) );
		$this->assertStringContainsString( 'Upload a bundle', $html );
		$this->assertStringNotContainsString( 'Import everything as not public', $html );
	}
}
