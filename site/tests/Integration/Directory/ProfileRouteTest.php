<?php
/**
 * Profile slugs and the /profile/{slug} route (REQ-003, REQ-006). Needs the WordPress test suite. Plain TestCase because it
 * needs the real state table; its posts are removed in tearDown.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Url\ProfileRoute;
use WhoIsWho\Core\Directory\Url\ProfileSlugs;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ProfileRouteTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		parent::tearDown();
	}

	private function profile( string $name, string $state = PubState::PUBLISHED_REVIEWED ): int {
		$id            = wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = (int) $id;
		( new StateMachine() )->enter( (int) $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		if ( PubState::PUBLISHED_REVIEWED === $state ) {
			( new StateMachine() )->transition( (int) $id, PubState::PUBLISHED_REVIEWED );
		}
		return (int) $id;
	}

	public function test_REQ_003_profile_slug_generated_with_collision_suffix(): void {
		$a = $this->profile( "Chinwe \u{d6}kafor Zxy" );
		$b = $this->profile( "Chinwe \u{d6}kafor Zxy" );
		$this->assertSame( 'chinwe-okafor-zxy', get_post_field( 'post_name', $a ), 'Diacritics are transliterated.' );
		$this->assertSame( 'chinwe-okafor-zxy-2', get_post_field( 'post_name', $b ), 'A collision gets a suffix.' );

		wp_update_post(
			array(
				'ID'         => $a,
				'post_title' => 'A Completely Different Name',
			)
		);
		$this->assertSame( 'chinwe-okafor-zxy', get_post_field( 'post_name', $a ), 'The slug is stable when the name changes.' );
		$this->assertSame( 'https://example.org/profile/chinwe-okafor-zxy/', str_replace( home_url(), 'https://example.org', ProfileSlugs::url_for_slug( 'chinwe-okafor-zxy' ) ) );
	}

	public function test_REQ_003_route_is_registered_as_a_rewrite_rule(): void {
		global $wp_rewrite;
		$before = get_option( 'permalink_structure' );
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		( new ProfileRoute() )->add_rules();
		flush_rewrite_rules( false );
		$rules = get_option( 'rewrite_rules' );
		$wp_rewrite->set_permalink_structure( (string) $before );
		flush_rewrite_rules( false );
		$this->assertIsArray( $rules );
		$this->assertSame( 'index.php?wiw_profile_slug=$matches[1]', $rules['^profile/([^/]+)/?$'] ?? null );
		$this->assertContains( ProfileRoute::QUERY_VAR, ( new ProfileRoute() )->query_vars( array() ) );
	}

	public function test_REQ_006_old_slug_redirects_to_new_slug(): void {
		$id    = $this->profile( 'Old Slug Holdings Qwv' );
		$route = new ProfileRoute();
		$this->assertSame( 'serve', $route->resolve( 'old-slug-holdings-qwv' )['action'] );

		$new = ( new ProfileSlugs() )->change( $id, 'new-slug-holdings-qwv' );
		$this->assertSame( 'new-slug-holdings-qwv', $new );
		$result = $route->resolve( 'old-slug-holdings-qwv' );
		$this->assertSame( 'redirect', $result['action'] );
		$this->assertSame( 301, $result['status'] );
		$this->assertSame( ProfileSlugs::url_for_slug( 'new-slug-holdings-qwv' ), $result['location'] );
		$this->assertSame( 'serve', $route->resolve( 'new-slug-holdings-qwv' )['action'] );

		$other = $this->profile( 'Other Qwv' );
		$this->assertNotSame( 'old-slug-holdings-qwv', ( new ProfileSlugs() )->unique( 'Old Slug Holdings Qwv', $other ), 'An old slug is not handed to another profile.' );
	}

	public function test_REQ_006_unknown_slug_returns_404(): void {
		$result = ( new ProfileRoute() )->resolve( 'no-such-profile-qwv' );
		$this->assertSame( 'notfound', $result['action'] );
		$this->assertSame( 404, $result['status'] );
	}

	public function test_REQ_209_route_answers_from_the_state(): void {
		$id    = $this->profile( 'State Route Qwv' );
		$route = new ProfileRoute();
		$this->assertSame( 200, $route->resolve( 'state-route-qwv' )['status'] );
		( new StateMachine() )->transition( $id, PubState::UNPUBLISHED, 'Takedown' );
		$this->assertSame( 410, $route->resolve( 'state-route-qwv' )['status'] );
		( new StateMachine() )->transition( $id, PubState::PUBLISHED_PENDING );
		$pending = $route->resolve( 'state-route-qwv' );
		$this->assertSame( 200, $pending['status'] );
		$this->assertTrue( $pending['policy']->noindex );
	}

	public function test_REQ_208_not_public_states_are_404(): void {
		$id            = wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Hidden Route Qwv',
			)
		);
		$this->posts[] = (int) $id;
		( new StateMachine() )->enter( (int) $id, PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => 'individual' ) );
		$this->assertSame( 404, ( new ProfileRoute() )->resolve( 'hidden-route-qwv' )['status'] );
	}

	public function test_REQ_217_pending_returns_200_with_noindex_header(): void {
		$this->profile( 'Pending Hdr Qwv', PubState::PUBLISHED_PENDING );
		$result = ( new ProfileRoute() )->resolve( 'pending-hdr-qwv' );
		$this->assertSame( 200, $result['status'] );
		$this->assertSame( 'noindex', ProfileRoute::headers_for( $result )['X-Robots-Tag'] );
	}

	public function test_REQ_209_removed_returns_410_with_noindex_header(): void {
		$id = $this->profile( 'Removed Hdr Qwv' );
		( new StateMachine() )->transition( $id, PubState::REMOVED_SUPPRESSED, 'Removal request' );
		$result = ( new ProfileRoute() )->resolve( 'removed-hdr-qwv' );
		$this->assertSame( 410, $result['status'] );
		$this->assertSame( 'noindex', ProfileRoute::headers_for( $result )['X-Robots-Tag'] );
	}

	public function test_REQ_209_restore_returns_200_again(): void {
		$id = $this->profile( 'Restore Hdr Qwv' );
		( new StateMachine() )->transition( $id, PubState::UNPUBLISHED, 'Takedown' );
		$this->assertSame( 410, ( new ProfileRoute() )->resolve( 'restore-hdr-qwv' )['status'] );
		( new StateMachine() )->transition( $id, PubState::PUBLISHED_PENDING );
		$this->assertSame( 200, ( new ProfileRoute() )->resolve( 'restore-hdr-qwv' )['status'] );
	}

	public function test_REQ_208_status_read_from_database_not_cache(): void {
		$id    = $this->profile( 'Fresh Hdr Qwv' );
		$route = new ProfileRoute();
		$this->assertSame( 200, $route->resolve( 'fresh-hdr-qwv' )['status'] );
		global $wpdb;
		$wpdb->update( StateRepository::table(), array( 'pub_state' => PubState::UNPUBLISHED ), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $route->resolve( 'fresh-hdr-qwv' );
		$this->assertSame( 410, $result['status'], 'A direct database change shows at once.' );
		$this->assertStringContainsString( 'no-cache', ProfileRoute::headers_for( $result )['Cache-Control'] );
		$this->assertStringContainsString( 's-maxage=0', ProfileRoute::headers_for( $result )['Cache-Control'] );
	}
}
