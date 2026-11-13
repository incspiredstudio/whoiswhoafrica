<?php
/**
 * The profile state matrix (REQ-208, REQ-209, REQ-217, REQ-218): one profile in each of the six publication states, asked on the
 * page route, the robots rules, the sitemap, the lists and an industry list, the structured data, the share-card tags, the home
 * pickers and the search index. The expected answers come from the state table in docs/theme/seo-and-sharing.md, written out
 * here rather than read back from StatePolicy, so a wrong policy fails. Needs the WordPress test suite and the real tables.
 *
 * Not in this file: feeds, REST, oEmbed, shortlinks, core sitemaps and previews, which ProfileExposureTest and the bare-install
 * matrix cover; the browser smoke (M1-SEO-12, issue 332) and the search results page (M2) are separate issues.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Visibility;

use WhoIsWho\Core\Directory\Fields\ProfileFields;
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
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Directory\Url\ProfileRoute;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ProfileStateMatrixTest extends TestCase {

	/**
	 * What each state must do on each surface (docs/theme/seo-and-sharing.md, "Visibility decides everything").
	 *
	 * http: the page status; noindex: the robots rule; sitemap, lists, schema, search: presence; card: share-card tags.
	 */
	private const TABLE = array(
		PubState::PUBLISHED_REVIEWED  => array(
			'http'    => 200,
			'noindex' => false,
			'sitemap' => true,
			'lists'   => true,
			'schema'  => true,
			'search'  => true,
			'card'    => true,
		),
		PubState::PUBLISHED_PENDING   => array(
			'http'    => 200,
			'noindex' => true,
			'sitemap' => false,
			'lists'   => false,
			'schema'  => false,
			'search'  => true,
			'card'    => true,
		),
		PubState::UNPUBLISHED         => array(
			'http'    => 410,
			'noindex' => true,
			'sitemap' => false,
			'lists'   => false,
			'schema'  => false,
			'search'  => false,
			'card'    => false,
		),
		PubState::REMOVED_SUPPRESSED  => array(
			'http'    => 410,
			'noindex' => true,
			'sitemap' => false,
			'lists'   => false,
			'schema'  => false,
			'search'  => false,
			'card'    => false,
		),
		PubState::REJECTED            => array(
			'http'    => 404,
			'noindex' => true,
			'sitemap' => false,
			'lists'   => false,
			'schema'  => false,
			'search'  => false,
			'card'    => false,
		),
		PubState::IMPORTED_NOT_PUBLIC => array(
			'http'    => 404,
			'noindex' => true,
			'sitemap' => false,
			'lists'   => false,
			'schema'  => false,
			'search'  => false,
			'card'    => false,
		),
	);

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Profile id by state.
	 *
	 * @var array<string, int>
	 */
	private array $by_state = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		if ( ! taxonomy_exists( Taxonomies::INDUSTRY ) ) {
			( new Taxonomies() )->register();
		}
		$this->by_state = array();
		foreach ( array_keys( self::TABLE ) as $state ) {
			$this->by_state[ $state ] = $this->profile( $state );
		}
		( new Sitemap() )->purge();
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( SearchIndexer::table(), array( 'item_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		( new Sitemap() )->purge();
		parent::tearDown();
	}

	/**
	 * A fictional organisation in a state, with an industry and a sourced fact.
	 *
	 * @param string $state Target state.
	 */
	private function profile( string $state ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Matrix ' . str_replace( '_', ' ', $state ) . ' Qzx',
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$start         = in_array( $state, array( PubState::IMPORTED_NOT_PUBLIC, PubState::REJECTED ), true ) ? PubState::IMPORTED_NOT_PUBLIC : PubState::PUBLISHED_PENDING;
		$machine->enter( $id, $start, array( 'entity_type' => 'organisation' ) );
		( new ProfileFields() )->set( $id, 'summary', 'A fictional organisation used to test visibility.' );
		( new ProvenanceRepository() )->record(
			$id,
			array(
				'field'        => 'summary',
				'source_url'   => 'https://example.org/src',
				'retrieved_at' => '2026-11-10T09:00:00Z',
				'confidence'   => 'high',
				'method'       => 'import',
			)
		);
		$term = term_exists( 'matrix-industry-qzx', Taxonomies::INDUSTRY );
		if ( ! $term ) {
			$term = wp_insert_term( 'Matrix Industry Qzx', Taxonomies::INDUSTRY, array( 'slug' => 'matrix-industry-qzx' ) );
		}
		wp_set_object_terms( $id, array( (int) ( is_array( $term ) ? $term['term_id'] : $term ) ), Taxonomies::INDUSTRY );
		if ( PubState::PUBLISHED_REVIEWED === $state ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		} elseif ( PubState::REJECTED === $state ) {
			$machine->transition( $id, PubState::REJECTED, 'test' );
		} elseif ( PubState::UNPUBLISHED === $state ) {
			$machine->transition( $id, PubState::UNPUBLISHED, 'test' );
		} elseif ( PubState::REMOVED_SUPPRESSED === $state ) {
			$machine->transition( $id, PubState::REMOVED_SUPPRESSED, 'test' );
		}
		( new SearchIndexer() )->on_state_change( $id, null, (string) ( new StateRepository() )->pub_state( $id ) );
		return $id;
	}

	private function slug( string $state ): string {
		return (string) get_post_field( 'post_name', $this->by_state[ $state ] );
	}

	/**
	 * Ids of our profiles in a list of profiles.
	 *
	 * @param PublicProfile[] $profiles Profiles.
	 * @return int[]
	 */
	private function ours( array $profiles ): array {
		return array_values( array_intersect( array_map( static fn ( PublicProfile $p ): int => $p->id, $profiles ), $this->by_state ) );
	}

	/**
	 * The profile sitemaps as text.
	 */
	private function sitemap_xml(): string {
		$sitemap = new Sitemap();
		$index   = $sitemap->respond( 'sitemap.xml' )['body'];
		$xml     = '';
		$parts   = substr_count( $index, 'sitemap-profiles-' );
		for ( $n = 1; $n <= $parts; $n++ ) {
			$xml .= $sitemap->respond( 'sitemap-profiles-' . $n . '.xml' )['body'];
		}
		return $xml;
	}

	public function test_REQ_208_state_matrix_on_every_surface(): void {
		$failures = array();
		$xml      = $this->sitemap_xml();
		$route    = new ProfileRoute();
		$all      = ( new ProfileList() )->query( 'all' );
		$industry = ( new ProfileList() )->query( 'all', array( 'industry' => 'matrix-industry-qzx' ) );
		$choices  = array_keys( PublicProfile::public_choices() );
		$indexed  = $this->indexed();
		foreach ( self::TABLE as $state => $expect ) {
			$id     = $this->by_state[ $state ];
			$slug   = $this->slug( $state );
			$result = $route->resolve( $slug );
			$found  = PublicProfile::find( $slug );
			$seen   = array(
				'http'    => $result['status'],
				'noindex' => array_key_exists( 'X-Robots-Tag', ProfileRoute::headers_for( $result ) ),
				'sitemap' => false !== strpos( $xml, '/profile/' . $slug . '/' ),
				'lists'   => in_array( $id, $this->ours( $all['profiles'] ), true ) || in_array( $id, $this->ours( $industry['profiles'] ), true ) || ( PubState::PUBLISHED_REVIEWED !== $state && in_array( $id, $choices, true ) ),
				'schema'  => null !== $found && null !== ( new StructuredData() )->for_profile( $found ),
				'search'  => in_array( $id, $indexed, true ),
				'card'    => null !== $found && array() !== ( new Tags() )->for_profile( $found )['og'],
			);
			foreach ( $expect as $surface => $want ) {
				if ( $seen[ $surface ] !== $want ) {
					$failures[] = sprintf( '%s on %s: expected %s, got %s', $state, $surface, wp_json_encode( $want ), wp_json_encode( $seen[ $surface ] ) );
				}
			}
		}
		$this->assertSame( array(), $failures, "State matrix failures:\n" . implode( "\n", $failures ) );
		$this->assertContains( $this->by_state[ PubState::PUBLISHED_REVIEWED ], array_keys( PublicProfile::public_choices() ), 'the reviewed profile is offered by the home and story pickers' );
	}

	public function test_REQ_218_pending_profile_in_search_but_not_in_lists(): void {
		$pending = $this->by_state[ PubState::PUBLISHED_PENDING ];
		$this->assertContains( $pending, $this->indexed(), 'the pending profile is searchable' );
		global $wpdb;
		$flag = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT pending FROM ' . SearchIndexer::table() . ' WHERE item_type = %s AND item_id = %d', SearchIndexer::ITEM_TYPE, $pending ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		$this->assertSame( 1, $flag, 'and carries the pending flag the result card turns into the label' );
		$this->assertNotContains( $pending, $this->ours( ( new ProfileList() )->query( 'all' )['profiles'] ) );
		$this->assertNotContains( $pending, $this->ours( ( new ProfileList() )->query( 'all', array( 'industry' => 'matrix-industry-qzx' ) )['profiles'] ) );
		$this->assertNotContains( $pending, array_keys( PublicProfile::public_choices() ) );
		$this->assertStringNotContainsString( '/profile/' . $this->slug( PubState::PUBLISHED_PENDING ) . '/', $this->sitemap_xml() );
		$found = PublicProfile::find( $this->slug( PubState::PUBLISHED_PENDING ) );
		$this->assertNotNull( $found );
		$this->assertSame( 'noindex', ( new Tags() )->for_profile( $found )['robots'], 'the robots meta tag' );
		$this->assertNotNull( $found->label, 'the pending label' );
	}

	public function test_REQ_209_removed_profile_410_across_surfaces(): void {
		foreach ( array( PubState::REMOVED_SUPPRESSED, PubState::UNPUBLISHED ) as $state ) {
			$result  = ( new ProfileRoute() )->resolve( $this->slug( $state ) );
			$headers = ProfileRoute::headers_for( $result );
			$this->assertSame( 410, $result['status'], $state );
			$this->assertSame( 'noindex', $headers['X-Robots-Tag'] ?? '', $state );
			$this->assertStringContainsString( 'no-cache', $headers['Cache-Control'], 'a takedown is never cached: ' . $state );
			$this->assertNull( PublicProfile::find( $this->slug( $state ) ), 'no read model, so no card, schema or link: ' . $state );
			$this->assertNotContains( $this->by_state[ $state ], $this->indexed(), $state );
			$this->assertStringNotContainsString( $this->slug( $state ), $this->sitemap_xml(), $state );
		}
	}

	/**
	 * Ids of our profiles in the search index.
	 *
	 * @return int[]
	 */
	private function indexed(): array {
		global $wpdb;
		$ids = array_map( 'intval', (array) $wpdb->get_col( 'SELECT item_id FROM ' . SearchIndexer::table() ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return array_values( array_intersect( $ids, $this->by_state ) );
	}
}
