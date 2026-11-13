<?php
/**
 * Who may edit and publish pages (REQ-260): Editor and Administrator, never a Reviewer. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\TestCase;

final class PageCapabilitiesTest extends TestCase {

	private const PAGE_CAPS = array( 'edit_pages', 'edit_others_pages', 'publish_pages', 'edit_published_pages' );

	/**
	 * Users and posts to remove.
	 *
	 * @var int[]
	 */
	private array $users = array();

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		delete_option( RoleSync::OPTION_SYNCED );
		delete_option( RoleSync::OPTION_VERSION );
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
	}

	protected function tearDown(): void {
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
		$this->posts = array();
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * A user with a role.
	 *
	 * @param string $role Role.
	 */
	private function user( string $role ): int {
		$id            = (int) wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->users[] = $id;
		return $id;
	}

	/**
	 * A published page by an administrator.
	 */
	private function page(): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_title'  => 'About',
				'post_status' => 'publish',
				'post_author' => $this->user( 'administrator' ),
			)
		);
		$this->posts[] = $id;
		return $id;
	}

	public function test_REQ_260_editor_can_edit_and_publish_pages(): void {
		$page   = $this->page();
		$editor = $this->user( 'wiw_editor' );
		foreach ( self::PAGE_CAPS as $cap ) {
			$this->assertTrue( user_can( $editor, $cap ), $cap );
		}
		$this->assertTrue( user_can( $editor, 'edit_post', $page ), 'An editor can edit About, FAQ, rules and legal pages.' );
		$this->assertTrue( user_can( $this->user( 'administrator' ), 'publish_pages' ) );
	}

	public function test_REQ_260_reviewer_cannot_edit_pages(): void {
		$page     = $this->page();
		$reviewer = $this->user( 'wiw_reviewer' );
		foreach ( self::PAGE_CAPS as $cap ) {
			$this->assertFalse( user_can( $reviewer, $cap ), $cap );
		}
		$this->assertFalse( user_can( $reviewer, 'edit_post', $page ) );
	}

	public function test_REQ_260_page_capabilities_are_in_the_catalogue_for_editor_and_administrator_only(): void {
		$catalogue = new CapabilityCatalogue();
		foreach ( self::PAGE_CAPS as $cap ) {
			$this->assertContains( $cap, $catalogue->for_bundle( 'editor' ), $cap );
			$this->assertContains( $cap, $catalogue->for_bundle( 'administrator' ), $cap );
			$this->assertNotContains( $cap, $catalogue->for_bundle( 'reviewer' ), $cap );
		}
	}
}
