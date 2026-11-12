<?php
/**
 * The sitemap (REQ-121, REQ-208, REQ-209, REQ-217). Needs the WordPress test suite. Plain TestCase because it needs the real
 * state table; its posts are removed in tearDown.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Sitemap\Sitemap;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class SitemapTest extends TestCase {

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
		( new Sitemap() )->purge();
		parent::tearDown();
	}

	/**
	 * Make a profile in a state; its slug comes from the title.
	 *
	 * @param string $name  Title.
	 * @param string $state imported, pending, reviewed or unpublished.
	 */
	private function profile( string $name, string $state ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, 'imported' === $state ? PubState::IMPORTED_NOT_PUBLIC : PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		if ( 'reviewed' === $state ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		} elseif ( 'unpublished' === $state ) {
			$machine->transition( $id, PubState::UNPUBLISHED, 'test' );
		}
		return $id;
	}

	private function profiles_xml(): string {
		$out   = '';
		$parts = $this->parts();
		for ( $n = 1; $n <= $parts; $n++ ) {
			$out .= ( new Sitemap() )->respond( 'sitemap-profiles-' . $n . '.xml' )['body'];
		}
		return $out;
	}

	private function parts(): int {
		return substr_count( ( new Sitemap() )->respond( 'sitemap.xml' )['body'], 'sitemap-profiles-' );
	}

	public function test_REQ_208_sitemap_lists_reviewed_profiles_only(): void {
		$reviewed = $this->profile( 'Sitemap Reviewed Qvx', 'reviewed' );
		$this->profile( 'Sitemap Imported Qvx', 'imported' );
		$this->profile( 'Sitemap Gone Qvx', 'unpublished' );
		$xml = $this->profiles_xml();
		$this->assertStringContainsString( '/profile/' . get_post_field( 'post_name', $reviewed ) . '/', $xml );
		$this->assertStringNotContainsString( 'sitemap-imported-qvx', $xml );
		$this->assertStringNotContainsString( 'sitemap-gone-qvx', $xml );
		$index = ( new Sitemap() )->respond( 'sitemap.xml' );
		$this->assertSame( 200, $index['status'] );
		$this->assertStringContainsString( '<sitemapindex', $index['body'] );
		$this->assertStringContainsString( 'sitemap-pages.xml', $index['body'] );
	}

	public function test_REQ_217_pending_profile_not_in_sitemap(): void {
		$this->profile( 'Sitemap Pending Qvx', 'pending' );
		$this->assertStringNotContainsString( 'sitemap-pending-qvx', $this->profiles_xml() );
	}

	public function test_REQ_217_approval_adds_the_entry_in_the_same_step(): void {
		$id = $this->profile( 'Sitemap Approve Qvx', 'pending' );
		$this->assertStringNotContainsString( 'sitemap-approve-qvx', $this->profiles_xml(), 'Built and cached without the profile.' );
		( new Sitemap() )->register();
		( new StateMachine() )->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		$this->assertStringContainsString( 'sitemap-approve-qvx', $this->profiles_xml(), 'Approval purged the cache.' );
		remove_all_actions( 'wiw_profile_state_changed' );
	}

	public function test_REQ_209_removed_profile_leaves_sitemap_in_same_step(): void {
		( new Sitemap() )->register();
		$id = $this->profile( 'Sitemap Remove Qvx', 'reviewed' );
		$this->assertStringContainsString( 'sitemap-remove-qvx', $this->profiles_xml() );
		( new StateMachine() )->transition( $id, PubState::UNPUBLISHED, 'Owner asked', 1 );
		$this->assertStringNotContainsString( 'sitemap-remove-qvx', $this->profiles_xml(), 'The state change purged the cache.' );
		remove_all_actions( 'wiw_profile_state_changed' );
	}

	public function test_REQ_121_core_sitemaps_and_user_sitemaps_disabled(): void {
		$sitemap = new Sitemap();
		$this->assertNull( $sitemap->respond( 'wp-sitemap.xml' ) );
		$this->assertNull( $sitemap->respond( 'wp-sitemap-users-1.xml' ) );
		$this->assertNull( $sitemap->respond( 'sitemap-users-1.xml' ) );
		$this->assertNull( $sitemap->respond( 'about' ) );
		$this->assertFalse( apply_filters( 'wp_sitemaps_enabled', true ) );
		$this->assertSame( 404, $sitemap->respond( 'sitemap-profiles-999.xml' )['status'] );
	}

	public function test_REQ_121_sitemap_is_cached_for_five_minutes(): void {
		$sitemap = new Sitemap();
		$sitemap->respond( 'sitemap-pages.xml' );
		$key = 'wiw_sm_' . (int) get_option( Sitemap::VERSION, 0 ) . '_pages';
		$this->assertIsString( get_transient( $key ) );
		$this->assertSame( 300, Sitemap::TTL );
		$sitemap->purge();
		$this->assertNotSame( $key, 'wiw_sm_' . (int) get_option( Sitemap::VERSION, 0 ) . '_pages' );
	}
}
