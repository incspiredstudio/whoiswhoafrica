<?php
/**
 * Who may open the Purge log screen (REQ-209, REQ-240): one authorisation matrix line. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Cache\Admin\PurgeLogScreen;
use WhoIsWho\Core\Platform\Cache\MemoryPurgeLog;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class PurgeLogScreenAccessTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
	}

	public function test_REQ_209_purge_log_requires_capability(): void {
		$registry = new ScreenRegistry();
		$registry->register( PurgeLogScreen::SCREEN, 'Purge log', PurgeLogScreen::CAP, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), PurgeLogScreen::CAP, 'wiw_review_records' );
		AuthorisationMatrix::assert_statuses(
			$this,
			PurgeLogScreen::SCREEN,
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

	public function test_REQ_209_purge_log_screen_is_read_only_and_refuses_without_capability(): void {
		$log = new MemoryPurgeLog();
		$log->add( 'profile', 5, 3, 'skipped' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_editor' ) ) );
		try {
			( new PurgeLogScreen( $log ) )->html( new Request( array() ) );
			$this->fail( 'expected a refusal' );
		} catch ( \DomainException $e ) {
			$this->assertStringContainsString( 'purge log', $e->getMessage() );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$html = ( new PurgeLogScreen( $log ) )->html( new Request( array() ) );
		$this->assertStringContainsString( 'profile #5', $html );
		$this->assertStringNotContainsString( 'method="post"', $html );
	}
}
