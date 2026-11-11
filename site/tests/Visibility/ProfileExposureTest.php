<?php
/**
 * A profile cannot leak through WordPress surfaces (REQ-208, REQ-209): feeds, REST, oEmbed, shortlinks, sitemaps, author pages
 * and previews. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Visibility;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Guard\Exposure\Exposure;
use WhoIsWho\Tests\WpTestCase;

final class ProfileExposureTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
	}

	/**
	 * A profile post that WordPress itself would call published (a pending-review profile has the publish status).
	 */
	private function published_profile(): int {
		global $wpdb;
		$id = self::factory()->post->create(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Leak Probe Qwv',
			)
		);
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the test sets the status the way a state change would.
		clean_post_cache( $id );
		return $id;
	}

	public function test_REQ_208_profile_absent_from_feeds_rest_oembed_shortlink(): void {
		$id   = $this->published_profile();
		$type = get_post_type_object( ProfileType::POST_TYPE );
		$this->assertFalse( $type->show_in_rest );
		$this->assertArrayNotHasKey( '/wp/v2/' . ProfileType::POST_TYPE, rest_get_server()->get_routes() );

		// Even if another plugin tries to open the type, the guard closes it again.
		$args = Exposure::lock_guarded_type(
			array(
				'show_in_rest'        => true,
				'has_archive'         => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
			),
			ProfileType::POST_TYPE
		);
		$this->assertFalse( $args['show_in_rest'] );
		$this->assertFalse( $args['has_archive'] );
		$this->assertFalse( $args['publicly_queryable'] );
		$this->assertTrue( $args['exclude_from_search'] );

		$this->assertSame( '', wp_get_shortlink( $id, 'post' ), 'No shortlink.' );
		$this->assertSame( 0, Exposure::no_oembed_for_guarded( $id ), 'No oEmbed answer.' );
		$this->assertArrayNotHasKey( ProfileType::POST_TYPE, Exposure::drop_guarded_from_sitemaps( array( ProfileType::POST_TYPE => 1 ) ) );

		// A front-end query (feed, template, third-party plugin) never receives the profile.
		$query = new \WP_Query(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( array(), $query->posts );
		$feed = new \WP_Query(
			array(
				'post_type'   => 'any',
				'post_status' => 'publish',
			)
		);
		$this->assertNotContains( $id, wp_list_pluck( $feed->posts, 'ID' ) );
	}

	public function test_REQ_208_author_pages_and_user_enumeration_closed(): void {
		$user = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		$this->go_to( home_url( '/?author=' . $user ) );
		do_action( 'template_redirect' );
		$this->assertTrue( is_404() );
		$routes = apply_filters( 'rest_endpoints', array( '/wp/v2/users' => array() ) );
		$this->assertArrayNotHasKey( '/wp/v2/users', $routes );
		$result = apply_filters( 'rest_authentication_errors', null );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_REQ_208_preview_requires_staff_capability(): void {
		$id       = $this->published_profile();
		$reviewer = self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) );
		$editor   = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );

		wp_set_current_user( 0 );
		$this->assertFalse( current_user_can( 'edit_post', $id ), 'A visitor cannot preview.' );
		$this->go_to( home_url( '/?p=' . $id . '&preview=true' ) );
		$this->assertNotContains( $id, wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );

		wp_set_current_user( $reviewer );
		$this->assertFalse( current_user_can( 'edit_post', $id ), 'A Reviewer reads profiles but holds no edit capability.' );
		wp_set_current_user( $editor );
		$this->assertTrue( current_user_can( 'edit_post', $id ), 'An Editor can edit, and so preview.' );
	}
}
