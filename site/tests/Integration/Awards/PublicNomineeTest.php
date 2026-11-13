<?php
/**
 * Public nominee and winner read models and the Awards routes (REQ-033, REQ-198, REQ-064, REQ-226). Needs the WordPress test
 * suite and the real profile and awards tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\AwardsRoutes;
use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\PublicNominee;
use WhoIsWho\Core\Awards\PublicWinner;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\WpTestCase;

final class PublicNomineeTest extends WpTestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		foreach ( array( AwardsTables::NOMINATIONS, AwardsTables::CATEGORIES, AwardsTables::EDITIONS ) as $name ) {
			$wpdb->query( 'DELETE FROM ' . AwardsTables::table( $name ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		}
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function profile( string $name, bool $reviewed ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		if ( $reviewed ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		}
		return $id;
	}

	private function category( string $title = 'Digital Pioneer' ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::CATEGORIES ),
			array(
				'group_key'  => 'technology-innovation',
				'title'      => $title,
				'created_at' => '2026-11-12 10:00:00',
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function nomination( int $profile_id, int $category_id, string $status, string $stage = 'nominated' ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::NOMINATIONS ),
			array(
				'category_id'     => $category_id,
				'profile_id'      => $profile_id,
				'nominator_name'  => 'Nora Nominator',
				'nominator_email' => 'nora.nominator@example.test',
				'contact_email'   => 'press.contact@example.test',
				'evidence'        => 'Evidence text from the nominator.',
				'stage'           => $stage,
				'status'          => $status,
				'source'          => 'public_form',
				'created_at'      => '2026-11-12 10:00:00',
				'updated_at'      => '2026-11-12 10:00:00',
			)
		);
		return (int) $wpdb->insert_id;
	}

	public function test_REQ_198_not_live_nomination_is_never_public(): void {
		$cat = $this->category();
		$id  = $this->nomination( $this->profile( 'Hidden Org Wzq', true ), $cat, 'not_live' );
		$ref = PublicNominee::ref_for( $id );
		$this->assertNull( PublicNominee::find( $ref ) );
		$this->assertSame( array(), PublicNominee::live() );
		$this->assertSame( array(), PublicWinner::all() );
		$this->assertSame( 404, ( new AwardsRoutes() )->resolve( 'nominee', $ref )['status'] );

		$id_w = $this->nomination( $this->profile( 'Hidden Winner Wzq', true ), $cat, 'not_live', 'winner' );
		$this->assertSame( array(), PublicWinner::all(), 'Winner stage alone shows nothing while Not live' );
		$this->assertNull( PublicNominee::find( PublicNominee::ref_for( $id_w ) ) );
	}

	public function test_REQ_064_nominator_is_never_in_public_output(): void {
		$cat   = $this->category();
		$id    = $this->nomination( $this->profile( 'Visible Org Wzq', true ), $cat, 'live' );
		$found = PublicNominee::find( PublicNominee::ref_for( $id ) );
		$this->assertNotNull( $found );
		$dump = (string) wp_json_encode( get_object_vars( $found ) );
		foreach ( array( 'Nora', 'nominator', 'press.contact', 'Evidence text', 'example.test' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $dump );
		}
		$props = array_keys( get_object_vars( $found ) );
		foreach ( array( 'nominator', 'nominator_name', 'nominator_email', 'contact_email', 'evidence', 'id' ) as $forbidden ) {
			$this->assertNotContains( $forbidden, $props );
		}
		$this->assertStringNotContainsString( '/' . $id . '/', $found->url, 'the address carries a reference, not the nomination id' );
		$this->assertNull( PublicNominee::find( substr( $found->ref, 0, -1 ) . ( 'a' === substr( $found->ref, -1 ) ? 'b' : 'a' ) ), 'an altered reference does not resolve' );
	}

	public function test_REQ_226_pending_review_profile_never_on_a_nominee_page(): void {
		$cat = $this->category();
		$id  = $this->nomination( $this->profile( 'Pending Org Wzq', false ), $cat, 'live', 'winner' );
		$ref = PublicNominee::ref_for( $id );
		$this->assertNull( PublicNominee::find( $ref ) );
		$this->assertSame( array(), PublicNominee::live() );
		$this->assertSame( array(), PublicWinner::all() );
		$this->assertSame( 404, ( new AwardsRoutes() )->resolve( 'nominee', $ref )['status'] );
		$none = $this->nomination( 0, $cat, 'live' );
		$this->assertNull( PublicNominee::find( PublicNominee::ref_for( $none ) ), 'a nomination with no linked profile is not public' );
	}

	public function test_REQ_033_live_nominee_and_live_winner_appear_on_their_pages(): void {
		$cat    = $this->category();
		$nom    = $this->nomination( $this->profile( 'Nominee Org Wzq', true ), $cat, 'live' );
		$win    = $this->nomination( $this->profile( 'Winner Org Wzq', true ), $cat, 'live', 'winner' );
		$shadow = $this->nomination( $this->profile( 'Shadow Org Wzq', true ), $cat, 'not_live', 'winner' );
		$names  = array_map( static fn ( $n ) => $n->name, PublicNominee::live( $cat ) );
		$this->assertContains( 'Nominee Org Wzq', $names );
		$this->assertContains( 'Winner Org Wzq', $names );
		$this->assertNotContains( 'Shadow Org Wzq', $names );
		$winners = array_map( static fn ( $n ) => $n->name, PublicWinner::all() );
		$this->assertSame( array( 'Winner Org Wzq' ), $winners );
		$this->assertSame( array( 2026 ), array_keys( PublicWinner::by_year() ) );
		$found = PublicNominee::find( PublicNominee::ref_for( $nom ) );
		$this->assertSame( 'Digital Pioneer', $found->category );
		$this->assertSame( 'Technology and Innovation', $found->group );
		$this->assertFalse( $found->is_winner );
		$this->assertSame( 'Nominee Org Wzq', $found->profile->name, 'built from the reviewed profile read model' );
		$routes = new AwardsRoutes();
		$this->assertSame( 200, $routes->resolve( 'nominee', PublicNominee::ref_for( $win ) )['status'] );
		$this->assertSame( 200, $routes->resolve( 'nominees' )['status'] );
		$this->assertSame( 200, $routes->resolve( 'winners' )['status'] );
		$this->assertSame( 200, $routes->resolve( 'category', (string) $cat )['status'] );
		$this->assertSame( 404, $routes->resolve( 'category', '999999' )['status'] );
		$this->assertSame( 404, $routes->resolve( 'nominee', PublicNominee::ref_for( $shadow ) )['status'] );
	}

	public function test_REQ_198_staff_who_manage_awards_preview_a_not_live_nominee(): void {
		$cat = $this->category();
		$id  = $this->nomination( $this->profile( 'Preview Org Wzq', true ), $cat, 'not_live' );
		$ref = PublicNominee::ref_for( $id );
		$res = ( new AwardsRoutes() )->resolve( 'nominee', $ref );
		$this->assertSame( 'notfound', $res['action'] );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_editor' ) ) );
		$res = ( new AwardsRoutes() )->resolve( 'nominee', $ref );
		$this->assertSame( 'preview', $res['action'] );
		$this->assertTrue( PublicNominee::find( $ref, true )->preview );
		$this->assertNull( PublicNominee::find( $ref ), 'without the preview flag it is still hidden' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 'notfound', ( new AwardsRoutes() )->resolve( 'nominee', $ref )['action'] );
	}
}
