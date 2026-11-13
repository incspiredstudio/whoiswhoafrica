<?php
/**
 * A staff member can open a profile that is not public; a visitor cannot, and the public answer does not change
 * (REQ-208, REQ-209, REQ-217). Needs the WordPress test suite. Plain TestCase because it needs the real state table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.GlobalVariablesOverride -- the test gives each request a fresh main query, as WordPress does.

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Url\ProfileRoute;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class StaffPreviewRouteTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Users to remove.
	 *
	 * @var int[]
	 */
	private array $users = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		wp_set_current_user( 0 );
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
		$this->posts = array();
		$this->users = array();
		parent::tearDown();
	}

	private function user( string $role ): int {
		$id            = (int) wp_insert_user(
			array(
				'user_login' => $role . '-' . uniqid(),
				'user_pass'  => 'Preview-Test-Passphrase-1',
				'user_email' => $role . uniqid() . '@example.org',
				'role'       => $role,
			)
		);
		$this->users[] = $id;
		return $id;
	}

	private function profile( string $name, string $state ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		if ( PubState::PUBLISHED_PENDING !== $state ) {
			( new StateMachine() )->transition( $id, $state, 'Test move' );
		}
		return $id;
	}

	public function test_REQ_208_staff_can_preview_a_profile_that_is_not_public(): void {
		$unpublished = $this->profile( 'Preview Unpub Qzx', PubState::UNPUBLISHED );
		$slug        = get_post_field( 'post_name', $unpublished );
		$route       = new ProfileRoute();

		$this->assertSame( 410, $route->resolve( $slug )['status'], 'the public answer is 410' );
		$this->assertNull( $route->staff_preview_id( $slug ), 'a visitor gets no preview' );

		wp_set_current_user( $this->user( 'wiw_reviewer' ) );
		$this->assertSame( $unpublished, $route->staff_preview_id( $slug ), 'a Reviewer can preview it' );
		$this->assertSame( 410, $route->resolve( $slug )['status'], 'and the public answer is unchanged for everyone' );
	}

	public function test_REQ_208_staff_can_preview_a_rejected_profile_that_is_a_404_to_visitors(): void {
		$rejected = $this->profile( 'Preview Rejected Qzx', PubState::REJECTED );
		$slug     = get_post_field( 'post_name', $rejected );
		$route    = new ProfileRoute();

		$this->assertSame( 404, $route->resolve( $slug )['status'] );
		$this->assertNull( $route->staff_preview_id( $slug ) );

		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$this->assertSame( $rejected, $route->staff_preview_id( $slug ) );
	}

	public function test_REQ_208_an_unknown_address_is_a_404_for_staff_too(): void {
		wp_set_current_user( $this->user( 'administrator' ) );
		$this->assertNull( ( new ProfileRoute() )->staff_preview_id( 'no-such-profile-qzx' ) );
	}

	public function test_REQ_208_the_handler_serves_staff_and_answers_visitors_as_before(): void {
		global $wp_query;
		$id   = $this->profile( 'Preview Handler Qzx', PubState::UNPUBLISHED );
		$slug = get_post_field( 'post_name', $id );

		// A visitor: 404, nothing handed to the theme.
		$wp_query = new \WP_Query();
		$wp_query->set( ProfileRoute::QUERY_VAR, $slug );
		( new ProfileRoute() )->handle();
		$this->assertTrue( $wp_query->is_404(), 'a visitor still gets the 404 page' );
		$this->assertEmpty( $wp_query->get( ProfileRoute::ID_VAR ), 'and the theme is given no profile' );

		// Staff: the profile id is handed over and it is not a 404.
		wp_set_current_user( $this->user( 'wiw_reviewer' ) );
		$wp_query = new \WP_Query();
		$wp_query->set( ProfileRoute::QUERY_VAR, $slug );
		( new ProfileRoute() )->handle();
		$this->assertFalse( $wp_query->is_404() );
		$this->assertSame( $id, (int) $wp_query->get( ProfileRoute::ID_VAR ) );
	}
}
