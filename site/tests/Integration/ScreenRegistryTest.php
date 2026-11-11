<?php
/**
 * Screens and actions are deny-by-default (REQ-240, REQ-235, REQ-244). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class ScreenRegistryTest extends WpTestCase {

	/**
	 * Users by label.
	 *
	 * @var array<string, int>
	 */
	private array $users = array();

	/**
	 * Registry under test.
	 *
	 * @var ScreenRegistry
	 */
	private ScreenRegistry $registry;

	protected function setUp(): void {
		parent::setUp();
		// Clean slate: other tests commit the transaction, so role grants can leak between runs.
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();

		$this->registry = new ScreenRegistry();
		$this->registry->register( 'wiw-probe-review', 'Probe review', 'wiw_review_records', '__return_null' );
		$this->registry->register( 'wiw-probe-imports', 'Probe imports', 'wiw_run_imports', '__return_null' );
		$this->registry->hook();
		$this->users = AuthorisationMatrix::users( self::factory(), 'wiw_run_imports', 'wiw_review_records' );
	}

	public function test_REQ_240_hidden_menu_screen_also_returns_403_by_direct_url(): void {
		AuthorisationMatrix::assert_statuses(
			$this,
			'wiw-probe-imports',
			$this->users,
			array(
				'reviewer'      => 403,
				'editor'        => 403,
				'administrator' => 200,
				'granted'       => 200,
				'revoked'       => 403,
			)
		);
		$this->assertNotContains( 'wiw-probe-imports', AuthorisationMatrix::menu( $this->users['reviewer'] ), 'hidden from the menu too' );
		$this->assertContains( 'wiw-probe-imports', AuthorisationMatrix::menu( $this->users['administrator'] ) );
	}

	public function test_REQ_235_screen_without_capability_cannot_be_registered(): void {
		$registry = new ScreenRegistry();
		$this->expectException( \InvalidArgumentException::class );
		$registry->register( 'wiw-no-cap', 'No capability', '', '__return_null' );
	}

	public function test_REQ_235_slug_must_be_valid_and_unique(): void {
		$registry = new ScreenRegistry();
		$registry->register( 'wiw-one', 'One', 'read', '__return_null' );
		try {
			$registry->register( 'wiw-one', 'Again', 'read', '__return_null' );
			$this->fail( 'a duplicate slug must be refused' );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertStringContainsString( 'already registered', $e->getMessage() );
		}
		$this->expectException( \InvalidArgumentException::class );
		$registry->register( 'Bad Slug!', 'Bad', 'read', '__return_null' );
	}

	public function test_REQ_244_granted_capability_adds_menu_and_revoked_restores_403(): void {
		$editor = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		$this->assertSame( 403, AuthorisationMatrix::status( $editor, 'wiw-probe-imports' ) );
		$this->assertNotContains( 'wiw-probe-imports', AuthorisationMatrix::menu( $editor ) );

		wp_set_current_user( $this->users['administrator'] ); // The grant comes from an Administrator: nobody can grant themselves.
		( new \WP_User( $editor ) )->add_cap( 'wiw_run_imports' );
		clean_user_cache( $editor );
		$this->assertSame( 200, AuthorisationMatrix::status( $editor, 'wiw-probe-imports' ), 'the screen opens at once' );
		$this->assertContains( 'wiw-probe-imports', AuthorisationMatrix::menu( $editor ), 'and appears in the menu' );

		wp_set_current_user( $this->users['administrator'] );
		( new \WP_User( $editor ) )->remove_cap( 'wiw_run_imports' ); // And takes it away.
		clean_user_cache( $editor );
		$this->assertSame( 403, AuthorisationMatrix::status( $editor, 'wiw-probe-imports' ) );
		$this->assertNotContains( 'wiw-probe-imports', AuthorisationMatrix::menu( $editor ) );
	}

	public function test_REQ_244_the_menu_for_each_kind_of_user_is_derived_from_capabilities(): void {
		$expected = array(
			'reviewer'      => array( 'wiw-probe-review' ),
			'editor'        => array( 'wiw-probe-review' ),
			'administrator' => array( 'wiw-probe-review', 'wiw-probe-imports' ),
			'granted'       => array( 'wiw-probe-review', 'wiw-probe-imports' ),
			'revoked'       => array(),
		);
		foreach ( $expected as $label => $slugs ) {
			$this->assertSame( $slugs, AuthorisationMatrix::menu( $this->users[ $label ] ), "menu for {$label}" );
		}
	}

	public function test_REQ_240_action_requires_nonce_and_capability(): void {
		wp_set_current_user( $this->users['editor'] );
		$good  = new Request( array( '_wpnonce' => ActionGuard::nonce_for( 'wiw_probe' ) ) );
		$stale = new Request( array( '_wpnonce' => 'not-a-nonce' ) );
		$none  = new Request( array() );

		$this->assertNull( ActionGuard::check( 'wiw_probe', 'wiw_review_claims', $good ), 'capability and nonce: allowed' );
		$this->assertSame( ActionGuard::REFUSED_NONCE, ActionGuard::check( 'wiw_probe', 'wiw_review_claims', $stale ) );
		$this->assertSame( ActionGuard::REFUSED_NONCE, ActionGuard::check( 'wiw_probe', 'wiw_review_claims', $none ) );
		$this->assertSame( ActionGuard::REFUSED_CAPABILITY, ActionGuard::check( 'wiw_probe', 'wiw_run_imports', $good ), 'no capability: refused even with a valid nonce' );

		// The nonce belongs to one action.
		$other = new Request( array( '_wpnonce' => ActionGuard::nonce_for( 'something_else' ) ) );
		$this->assertSame( ActionGuard::REFUSED_NONCE, ActionGuard::check( 'wiw_probe', 'wiw_review_claims', $other ) );
	}

	public function test_REQ_240_an_action_cannot_be_registered_without_a_capability(): void {
		$this->expectException( \InvalidArgumentException::class );
		ActionGuard::register( 'wiw_probe', '', '__return_null' );
	}

	public function test_REQ_240_the_request_object_reads_values_safely(): void {
		$request = new Request(
			array(
				'name' => '  <b>Ada</b> ',
				'id'   => '42abc',
				'bad'  => array( 'x' ),
			)
		);
		$this->assertSame( 'Ada', $request->string( 'name' ) );
		$this->assertSame( 42, $request->int( 'id' ) );
		$this->assertSame( '', $request->string( 'bad' ) );
		$this->assertSame( '', $request->string( 'missing' ) );
	}
}
