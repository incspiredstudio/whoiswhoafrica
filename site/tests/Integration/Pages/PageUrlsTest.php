<?php
/**
 * Page URLs stay stable (REQ-260): editing never changes a URL, a changed slug redirects the old one, revisions keep author and
 * time, and fixed routes cannot be moved or deleted by accident. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Pages;

use WhoIsWho\Core\Content\Pages\PageRoutes;
use WhoIsWho\Tests\WpTestCase;

final class PageUrlsTest extends WpTestCase {

	public function set_up(): void {
		parent::set_up();
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		wp_set_current_user( 0 );
		( new PageRoutes() )->register();
	}

	/**
	 * Create a published page.
	 *
	 * @param string $slug Slug.
	 * @param int    $parent_id Parent id.
	 */
	private function page( string $slug, int $parent_id = 0 ): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => ucfirst( $slug ),
				'post_name'    => $slug,
				'post_parent'  => $parent_id,
				'post_content' => 'First draft of ' . $slug,
			)
		);
	}

	public function test_REQ_260_editing_a_page_keeps_its_url(): void {
		$id  = $this->page( 'stable-page' );
		$url = get_permalink( $id );
		wp_update_post(
			array(
				'ID'           => $id,
				'post_title'   => 'A new title',
				'post_content' => 'New words',
			)
		);
		$this->assertSame( $url, get_permalink( $id ) );
		$this->assertSame( 'stable-page', get_post_field( 'post_name', $id ) );
	}

	public function test_REQ_260_changed_slug_redirects_old_url(): void {
		$id = $this->page( 'old-name' );
		wp_update_post(
			array(
				'ID'        => $id,
				'post_name' => 'new-name',
			)
		);
		$this->assertSame( get_permalink( $id ), ( new PageRoutes() )->redirect_target( 'old-name' ) );
		$this->assertNull( ( new PageRoutes() )->redirect_target( 'new-name' ), 'the live address is not redirected' );
		$this->assertNull( ( new PageRoutes() )->redirect_target( 'never-existed' ) );

		// Changing it back drops the old address from the list: no loop.
		wp_update_post(
			array(
				'ID'        => $id,
				'post_name' => 'old-name',
			)
		);
		$this->assertNull( ( new PageRoutes() )->redirect_target( 'old-name' ) );
		$this->assertSame( get_permalink( $id ), ( new PageRoutes() )->redirect_target( 'new-name' ) );
	}

	public function test_REQ_260_child_page_old_path_redirects_and_unpublished_pages_do_not(): void {
		$parent_id = $this->page( 'awards-x' );
		$child     = $this->page( 'rules-x', $parent_id );
		wp_update_post(
			array(
				'ID'        => $child,
				'post_name' => 'terms-x',
			)
		);
		$this->assertSame( get_permalink( $child ), ( new PageRoutes() )->redirect_target( 'awards-x/rules-x' ) );
		wp_update_post(
			array(
				'ID'          => $child,
				'post_status' => 'draft',
			)
		);
		$this->assertNull( ( new PageRoutes() )->redirect_target( 'awards-x/rules-x' ), 'an unpublished page is not revealed by its old address' );
	}

	public function test_REQ_260_page_revisions_keep_author_and_time(): void {
		$author = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $author );
		$id = $this->page( 'with-history' );
		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => 'Second version',
			)
		);
		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => 'Third version',
			)
		);
		$revisions = wp_get_post_revisions( $id );
		$this->assertNotEmpty( $revisions );
		foreach ( $revisions as $revision ) {
			$this->assertSame( $author, (int) $revision->post_author );
			$this->assertNotSame( '', $revision->post_modified_gmt );
		}
	}

	public function test_REQ_260_fixed_routes_keep_their_slug_unless_an_administrator_changes_it(): void {
		$id = $this->page( 'about' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		wp_update_post(
			array(
				'ID'        => $id,
				'post_name' => 'about-renamed',
			)
		);
		$this->assertSame( 'about', get_post_field( 'post_name', $id ), 'an editor cannot move a fixed route' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		wp_update_post(
			array(
				'ID'        => $id,
				'post_name' => 'about-us',
			)
		);
		$this->assertSame( 'about-us', get_post_field( 'post_name', $id ) );
		$this->assertSame( get_permalink( $id ), ( new PageRoutes() )->redirect_target( 'about' ), 'and the old address redirects' );
	}

	public function test_REQ_260_fixed_routes_cannot_be_trashed_or_deleted_by_an_editor(): void {
		$id = $this->page( 'faq' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertFalse( wp_trash_post( $id ) );
		$this->assertFalse( wp_delete_post( $id, true ) );
		$this->assertSame( 'publish', get_post_status( $id ) );

		$other = $this->page( 'ordinary-page' );
		$this->assertNotFalse( wp_trash_post( $other ) );
	}
}
