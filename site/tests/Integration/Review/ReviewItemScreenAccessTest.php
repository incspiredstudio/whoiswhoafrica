<?php
/**
 * The Review item screen (REQ-084, REQ-148): the authorisation matrix line and the side-by-side markup. Needs the WordPress
 * test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Review;

use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Review\Admin\ReviewItemScreen;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class ReviewItemScreenAccessTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
	}

	public function test_REQ_084_review_item_screen_denies_without_capability(): void {
		$registry = new ScreenRegistry();
		$registry->register( ReviewItemScreen::SCREEN, 'Review item', ReviewItemScreen::CAPABILITY, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), ReviewItemScreen::CAPABILITY, ReviewItemScreen::CAPABILITY );
		AuthorisationMatrix::assert_statuses(
			$this,
			ReviewItemScreen::SCREEN,
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
