<?php
/**
 * WordPress's own sample content ("Hello world!" with its comment, and "Sample Page") is moved to the trash on first run, but only
 * when it is untouched: the default id, the default slug and never edited (REQ-056, #810). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Setup\DefaultContent;
use WhoIsWho\Tests\WpTestCase;

final class DefaultContentTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		delete_option( DefaultContent::OPTION );
		delete_option( \WhoIsWho\Core\Platform\Migrations\Runner::OPTION_LOCK ); // A lock another test left would stop the first run.
		foreach ( array( 1, 2 ) as $id ) {
			if ( null !== get_post( $id ) ) {
				wp_delete_post( $id, true );
			}
		}
	}

	/**
	 * Make one of WordPress's default posts.
	 *
	 * @param int    $id     Wanted id.
	 * @param string $type   post or page.
	 * @param string $slug   Slug.
	 * @param bool   $edited Whether it was edited after it was made.
	 */
	private function default_post( int $id, string $type, string $slug, bool $edited = false ): int {
		global $wpdb;
		$made = wp_insert_post(
			array(
				'import_id'     => $id,
				'post_type'     => $type,
				'post_name'     => $slug,
				'post_title'    => 'post' === $type ? 'Hello world!' : 'Sample Page',
				'post_status'   => 'publish',
				'post_date'     => '2026-11-01 10:00:00',
				'post_date_gmt' => '2026-11-01 10:00:00',
			)
		);
		$this->assertSame( $id, $made );
		$modified = $edited ? '2026-11-02 09:00:00' : '2026-11-01 10:00:00';
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => $modified,
				'post_modified_gmt' => $modified,
			),
			array( 'ID' => $id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_post_cache( $id );
		return $id;
	}

	public function test_REQ_056_untouched_hello_world_and_sample_page_go_to_the_trash_once(): void {
		$post    = $this->default_post( 1, 'post', 'hello-world' );
		$page    = $this->default_post( 2, 'page', 'sample-page' );
		$comment = self::factory()->comment->create( array( 'comment_post_ID' => $post ) );

		$this->assertSame( array( 1, 2 ), DefaultContent::retire() );
		$this->assertSame( 'trash', get_post_status( $post ) );
		$this->assertSame( 'trash', get_post_status( $page ) );
		$this->assertSame( 'post-trashed', get_comment( $comment )->comment_approved, 'the default comment goes with its post' );

		wp_untrash_post( $post );
		$this->assertSame( array(), DefaultContent::retire(), 'runs once: a post put back by hand stays' );
	}

	public function test_REQ_056_a_fresh_first_run_retires_the_defaults(): void {
		$post = $this->default_post( 1, 'post', 'hello-world' );
		$dir  = sys_get_temp_dir() . '/wiw-defaults-' . uniqid();
		mkdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$result = \WhoIsWho\Core\Platform\Setup\FirstRun::activate( $dir, true, true, array() );
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->assertSame( '', $result['error'] );
		$this->assertSame( 'trash', get_post_status( $post ) );
	}

	public function test_REQ_056_edited_or_renamed_defaults_are_kept(): void {
		$post = $this->default_post( 1, 'post', 'hello-world', true );
		$page = $this->default_post( 2, 'page', 'about-us' );
		$this->assertSame( array(), DefaultContent::retire() );
		$this->assertSame( 'publish', get_post_status( $post ), 'an edited post is the owner\'s content' );
		$this->assertSame( 'publish', get_post_status( $page ), 'a renamed page is the owner\'s content' );
	}
}
