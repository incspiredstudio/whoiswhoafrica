<?php
/**
 * The state matrix on real profiles (M1-SEO-11; REQ-208, REQ-209, REQ-217, REQ-218): one profile in each of the six publication
 * states, asked on the page, the robots header, the sitemap, the directory list, search and structured data. The expectations
 * are written out here from the state table in docs/theme/seo-and-sharing.md, not read from StatePolicy, so a change to the
 * policy that breaks the documented table fails this test. Needs the WordPress test suite and the real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Visibility;

use WhoIsWho\Core\Directory\ProfileList;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\Search\SearchIndexer;
use WhoIsWho\Core\Directory\Seo\StructuredData;
use WhoIsWho\Core\Directory\Seo\Tags;
use WhoIsWho\Core\Directory\Sitemap\Sitemap;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Url\ProfileRoute;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ProfileStateMatrixTest extends TestCase {

	/**
	 * The documented table: state => HTTP status, noindex, in lists, in search, in sitemap, structured data.
	 * Source: docs/theme/seo-and-sharing.md, "Visibility by state".
	 */
	private const TABLE = array(
		PubState::PUBLISHED_REVIEWED  => array( 200, false, true, true, true, true ),
		PubState::PUBLISHED_PENDING   => array( 200, true, false, true, false, false ),
		PubState::UNPUBLISHED         => array( 410, true, false, false, false, false ),
		PubState::REMOVED_SUPPRESSED  => array( 410, true, false, false, false, false ),
		PubState::REJECTED            => array( 404, true, false, false, false, false ),
		PubState::IMPORTED_NOT_PUBLIC => array( 404, true, false, false, false, false ),
	);

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete(
				SearchIndexer::table(),
				array(
					'item_type' => SearchIndexer::ITEM_TYPE,
					'item_id'   => $id,
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		( new Sitemap() )->purge();
		parent::tearDown();
	}

	/**
	 * Create an organisation profile and walk it to the state, through the state machine only.
	 *
	 * @param string $state A PubState value.
	 * @return array{id: int, slug: string, name: string}
	 */
	private function profile_in( string $state ): array {
		$name          = 'Matrix ' . ucwords( str_replace( '_', ' ', $state ) ) . ' Qwv';
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$values        = array( 'entity_type' => 'organisation' );
		switch ( $state ) {
			case PubState::IMPORTED_NOT_PUBLIC:
				$machine->enter( $id, PubState::IMPORTED_NOT_PUBLIC, $values );
				break;
			case PubState::REJECTED:
				$machine->enter( $id, PubState::IMPORTED_NOT_PUBLIC, $values );
				$machine->transition( $id, PubState::REJECTED, 'Matrix fixture' );
				break;
			case PubState::PUBLISHED_PENDING:
				$machine->enter( $id, PubState::PUBLISHED_PENDING, $values );
				break;
			case PubState::PUBLISHED_REVIEWED:
				$machine->enter( $id, PubState::PUBLISHED_PENDING, $values );
				$machine->transition( $id, PubState::PUBLISHED_REVIEWED );
				break;
			case PubState::UNPUBLISHED:
				$machine->enter( $id, PubState::PUBLISHED_PENDING, $values );
				$machine->transition( $id, PubState::PUBLISHED_REVIEWED );
				$machine->transition( $id, PubState::UNPUBLISHED, 'Matrix fixture' );
				break;
			case PubState::REMOVED_SUPPRESSED:
				$machine->enter( $id, PubState::PUBLISHED_PENDING, $values );
				$machine->transition( $id, PubState::REMOVED_SUPPRESSED, 'Matrix fixture' );
				break;
		}
		return array(
			'id'   => $id,
			'slug' => (string) get_post_field( 'post_name', $id ),
			'name' => $name,
		);
	}

	/**
	 * Ask every surface about one profile.
	 *
	 * @param array{id: int, slug: string, name: string} $p Profile.
	 * @return array{status: int, noindex: bool, list: bool, search: bool, sitemap: bool, schema: bool}
	 */
	private function surfaces( array $p ): array {
		global $wpdb;
		$decision = ( new ProfileRoute() )->resolve( $p['slug'] );
		$headers  = ProfileRoute::headers_for( $decision );

		$listed = false;
		foreach ( ( new ProfileList() )->query( 'all', array( 'q' => $p['name'] ) )['profiles'] as $found ) {
			$listed = $listed || $found->id === $p['id'];
		}

		$searched = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . SearchIndexer::table() . ' WHERE item_type = %s AND item_id = %d', SearchIndexer::ITEM_TYPE, $p['id'] ) ) > 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.

		$sitemap = ( new Sitemap() )->respond( 'sitemap-profiles-1.xml' );
		$in_map  = null !== $sitemap && false !== strpos( $sitemap['body'], '/profile/' . $p['slug'] . '/' );

		$public = PublicProfile::find_by_id( $p['id'] );
		$schema = null !== $public && null !== ( new StructuredData() )->for_profile( $public );

		return array(
			'status'  => (int) $decision['status'],
			'noindex' => isset( $headers['X-Robots-Tag'] ) && false !== strpos( $headers['X-Robots-Tag'], 'noindex' ),
			'list'    => $listed,
			'search'  => $searched,
			'sitemap' => $in_map,
			'schema'  => $schema,
		);
	}

	public function test_REQ_208_state_matrix_on_every_surface(): void {
		$failures = array();
		foreach ( self::TABLE as $state => $want ) {
			$got = $this->surfaces( $this->profile_in( $state ) );
			$exp = array(
				'status'  => $want[0],
				'noindex' => $want[1],
				'list'    => $want[2],
				'search'  => $want[3],
				'sitemap' => $want[4],
				'schema'  => $want[5],
			);
			foreach ( $exp as $surface => $value ) {
				if ( $value !== $got[ $surface ] ) {
					$failures[] = sprintf( '%s on %s: expected %s, got %s', $state, $surface, var_export( $value, true ), var_export( $got[ $surface ], true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- building a failure message.
				}
			}
		}
		$this->assertSame( array(), $failures, "State matrix failures:\n" . implode( "\n", $failures ) );
	}

	public function test_REQ_218_pending_profile_in_search_but_not_in_lists(): void {
		$p   = $this->profile_in( PubState::PUBLISHED_PENDING );
		$got = $this->surfaces( $p );
		$this->assertTrue( $got['search'], 'A pending profile is found by search.' );
		$this->assertFalse( $got['list'], 'A pending profile is not on lists, the home page or industry pages.' );
		$public = PublicProfile::find_by_id( $p['id'] );
		$this->assertNotNull( $public );
		$tags = ( new Tags() )->for_profile( $public );
		$this->assertSame( 'noindex', $tags['robots'], 'The robots meta tag says noindex.' );
		$this->assertStringContainsString( 'Human review pending', (string) wp_json_encode( $tags ), 'The pending label reaches the share text.' );
	}

	public function test_REQ_209_removed_profile_410_across_surfaces(): void {
		foreach ( array( PubState::UNPUBLISHED, PubState::REMOVED_SUPPRESSED ) as $state ) {
			$p   = $this->profile_in( $state );
			$got = $this->surfaces( $p );
			$this->assertSame( 410, $got['status'], $state );
			$this->assertTrue( $got['noindex'], $state . ' carries noindex.' );
			$this->assertFalse( $got['list'] || $got['search'] || $got['sitemap'] || $got['schema'], $state . ' appears nowhere.' );
			$this->assertNull( PublicProfile::find_by_id( $p['id'] ), $state . ' has no public read model.' );
		}
	}

	public function test_REQ_217_pending_profile_has_noindex_and_no_sitemap_or_schema_entry(): void {
		$got = $this->surfaces( $this->profile_in( PubState::PUBLISHED_PENDING ) );
		$this->assertSame( 200, $got['status'] );
		$this->assertTrue( $got['noindex'] );
		$this->assertFalse( $got['sitemap'] );
		$this->assertFalse( $got['schema'] );
	}
}
