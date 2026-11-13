<?php
/**
 * Hidden content and users do not leak through WordPress surfaces (REQ-229, REQ-208, REQ-046, REQ-217).
 * Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Visibility;

use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\WpTestCase;

final class ExposureTest extends WpTestCase {

	/**
	 * Build the real tables once (the story search index writes to one on every save).
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		( new Runner( dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
	}

	public function test_REQ_229_rest_requires_staff_login(): void {
		wp_set_current_user( 0 );
		$result = apply_filters( 'rest_authentication_errors', null );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 401, $result->get_error_data()['status'] );

		$staff = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $staff );
		// Staff must have a second factor before REST answers (REQ-047); give this one an authenticator app.
		update_user_meta( $staff, '_two_factor_enabled_providers', array( 'Two_Factor_Totp' ) );
		update_user_meta( $staff, '_two_factor_totp_key', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567' );
		$this->assertNull( apply_filters( 'rest_authentication_errors', null ), 'a signed-in user with a second factor passes' );
	}

	public function test_REQ_229_user_enumeration_is_blocked(): void {
		wp_set_current_user( 0 );
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$routes = apply_filters(
			'rest_endpoints',
			array(
				'/wp/v2/users'    => array(),
				'/wp/v2/users/me' => array(),
				'/wp/v2/posts'    => array(),
			)
		);
		$this->assertArrayNotHasKey( '/wp/v2/users', $routes );
		$this->assertArrayNotHasKey( '/wp/v2/users/me', $routes );

		$this->go_to( '/?author=' . $user );
		do_action( 'template_redirect' );
		$this->assertTrue( is_404(), '?author=ID shows nothing' );

		$this->assertStringNotContainsString( 'password', strtolower( apply_filters( 'login_errors', 'The password you entered for the username admin is incorrect.' ) ) );
		$this->assertStringNotContainsString( 'admin', apply_filters( 'login_errors', 'Unknown username admin' ) );
	}

	public function test_REQ_208_hidden_content_absent_from_feeds_oembed_and_core_sitemaps(): void {
		wp_set_current_user( 0 );
		$id = self::factory()->post->create( array( 'post_status' => 'wiw_hidden' ) );

		$this->assertFalse( apply_filters( 'wp_sitemaps_enabled', true ), 'core sitemaps are off' );
		$this->assertNotFalse( has_action( 'do_feed_rss2', array( \WhoIsWho\Guard\Exposure\Exposure::class, 'refuse_feed' ) ), 'feeds are refused' );

		$this->go_to( '/?p=' . $id . '&embed=true' );
		do_action( 'template_redirect' );
		$this->assertTrue( is_404(), 'no embed for hidden content' );
	}

	public function test_REQ_208_shortlinks_and_previews_match_real_url_answer(): void {
		wp_set_current_user( 0 );
		$id = self::factory()->post->create( array( 'post_status' => 'wiw_hidden' ) );

		$this->go_to( get_permalink( $id ) );
		$real = is_404();
		$this->go_to( '/?p=' . $id );
		$this->assertSame( $real, is_404(), 'the shortlink answers like the real URL' );
		$this->assertTrue( is_404() );

		$this->go_to( '/?p=' . $id . '&preview=true' );
		$this->assertTrue( is_404(), 'an anonymous preview answers 404' );

		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'wiw_hidden',
			)
		);
		$this->go_to( '/?page_id=' . $page );
		$this->assertTrue( is_404(), '?page_id= answers 404 for hidden content' );
	}

	public function test_REQ_208_attachment_pages_are_not_public(): void {
		wp_set_current_user( 0 );
		$parent = self::factory()->post->create();
		$att    = self::factory()->attachment->create_object(
			array(
				'file'           => 'x.jpg',
				'post_parent'    => $parent,
				'post_mime_type' => 'image/jpeg',
			)
		);
		$this->go_to( '/?attachment_id=' . $att );
		do_action( 'template_redirect' );
		$this->assertTrue( is_404() );
	}

	public function test_REQ_208_core_search_never_returns_a_guarded_type(): void {
		wp_set_current_user( 0 );
		\WhoIsWho\Guard\Guard::guard_post_type( 'wiw_probe' );
		register_post_type( 'wiw_probe', array( 'public' => true ) );
		self::factory()->post->create(
			array(
				'post_type'    => 'wiw_probe',
				'post_title'   => 'needle probe',
				'post_content' => 'needle',
			)
		);
		self::factory()->post->create(
			array(
				'post_title'   => 'needle story',
				'post_content' => 'needle',
			)
		);
		$this->go_to( '/?s=needle' );
		$types = wp_list_pluck( $GLOBALS['wp_query']->posts, 'post_type' );
		$this->assertContains( 'post', $types );
		$this->assertNotContains( 'wiw_probe', $types );
	}
}
