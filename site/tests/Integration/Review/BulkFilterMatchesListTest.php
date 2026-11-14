<?php
/**
 * Bulk unpublish acts on exactly what the Profiles list shows (REQ-193, REQ-009): for every list view and filter, the "Needs
 * classification" view included, the bulk count equals the number of published profiles the list shows, the confirmation form
 * carries the view, and the audit row records the filter used. Release test of 0.3.0: the box said 58 while the view listed 7.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Review;

use WhoIsWho\Core\Directory\Admin\ProfilesQuery;
use WhoIsWho\Core\Directory\Classification;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\Admin\BulkUnpublishPanel;
use WhoIsWho\Core\Review\Bulk\BulkFilter;
use WhoIsWho\Core\Review\Bulk\BulkRepository;
use WhoIsWho\Core\Review\Bulk\BulkUnpublish;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\WpTestCase;

final class BulkFilterMatchesListTest extends WpTestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Leaf industry and country used for classified profiles.
	 *
	 * @var array{leaf: int, country: int, unclassified: int}
	 */
	private array $terms;

	protected function setUp(): void {
		parent::setUp();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		( new Taxonomies() )->register();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		$existing    = get_term_by( 'slug', Classification::unclassified_slug(), Taxonomies::INDUSTRY );
		$unc         = $existing instanceof \WP_Term ? (int) $existing->term_id : self::factory()->term->create(
			array(
				'taxonomy' => Taxonomies::INDUSTRY,
				'name'     => 'Unclassified',
				'slug'     => Classification::unclassified_slug(),
			)
		);
		$sector      = self::factory()->term->create( array( 'taxonomy' => Taxonomies::INDUSTRY ) );
		$this->terms = array(
			'leaf'         => self::factory()->term->create(
				array(
					'taxonomy' => Taxonomies::INDUSTRY,
					'parent'   => $sector,
				)
			),
			'country'      => self::factory()->term->create( array( 'taxonomy' => Taxonomies::PLACE ) ),
			'unclassified' => $unc,
		);
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

	/**
	 * A profile.
	 *
	 * @param string $name       Name.
	 * @param string $state      Publication state.
	 * @param bool   $classified Whether it has a real industry and a country.
	 * @param string $type       Entity type.
	 * @param string $source     Source.
	 */
	private function profile( string $name, string $state, bool $classified, string $type = 'organisation', string $source = 'bulkview-qwv' ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter(
			$id,
			PubState::PUBLISHED_PENDING === $state || PubState::PUBLISHED_REVIEWED === $state ? PubState::PUBLISHED_PENDING : $state,
			array(
				'entity_type' => $type,
				'source'      => $source,
			)
		);
		if ( PubState::PUBLISHED_REVIEWED === $state ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		}
		update_post_meta( $id, Classification::PRIMARY_INDUSTRY_META, (string) ( $classified ? $this->terms['leaf'] : $this->terms['unclassified'] ) );
		if ( $classified ) {
			update_post_meta( $id, Classification::PRIMARY_COUNTRY_META, (string) $this->terms['country'] );
		}
		return $id;
	}

	/**
	 * The published profiles a list filter shows, counted from the list's own rows.
	 *
	 * @param array<string, string> $filters Filters.
	 */
	private function published_in_list( array $filters ): int {
		$n = 0;
		foreach ( ( new ProfilesQuery() )->run( $filters )['rows'] as $row ) {
			$n += in_array( $row['state'], array( PubState::PUBLISHED_PENDING, PubState::PUBLISHED_REVIEWED ), true ) ? 1 : 0;
		}
		return $n;
	}

	private function fixtures(): void {
		for ( $i = 1; $i <= 4; $i++ ) {
			$this->profile( 'Bulkview Done Qwv ' . $i, PubState::PUBLISHED_REVIEWED, true );
		}
		$this->profile( 'Bulkview Needs Qwv 1', PubState::PUBLISHED_REVIEWED, false );
		$this->profile( 'Bulkview Needs Qwv 2', PubState::PUBLISHED_PENDING, false, 'institution' );
		$this->profile( 'Bulkview Needs Qwv 3', PubState::IMPORTED_NOT_PUBLIC, false );
		$this->profile( 'Bulkview Other Qwv 1', PubState::PUBLISHED_REVIEWED, false, 'individual', 'bulkview-other-qwv' );
	}

	public function test_REQ_193_bulk_count_equals_the_list_for_every_view_and_filter(): void {
		$this->fixtures();
		$cases = array(
			'needs view'          => array(
				'classification' => 'needs',
				'name'           => 'Bulkview',
			),
			'all with name'       => array( 'name' => 'Bulkview' ),
			'source'              => array( 'source' => 'bulkview-qwv' ),
			'needs and source'    => array(
				'classification' => 'needs',
				'source'         => 'bulkview-qwv',
			),
			'needs and type'      => array(
				'classification' => 'needs',
				'type'           => 'institution',
				'name'           => 'Bulkview',
			),
			'state reviewed'      => array(
				'state' => PubState::PUBLISHED_REVIEWED,
				'name'  => 'Bulkview',
			),
			'state not public'    => array(
				'state' => PubState::IMPORTED_NOT_PUBLIC,
				'name'  => 'Bulkview',
			),
			'origin'              => array(
				'origin' => 'import',
				'name'   => 'Bulkview',
			),
			'needs and group'     => array(
				'classification' => 'needs',
				'name'           => 'Bulkview',
				'group'          => 'source',
			),
			'needs and other src' => array(
				'classification' => 'needs',
				'source'         => 'bulkview-other-qwv',
			),
		);
		$bulk  = new BulkFilter();
		foreach ( $cases as $label => $raw ) {
			$filters = ProfilesQuery::filters_from( new Request( $raw ) );
			$this->assertSame( $this->published_in_list( $filters ), $bulk->count( BulkFilter::clean( $filters ) ), 'Bulk and list disagree: ' . $label );
		}
		$needs = ProfilesQuery::filters_from(
			new Request(
				array(
					'classification' => 'needs',
					'name'           => 'Bulkview',
				)
			)
		);
		$this->assertSame( 4, ( new ProfilesQuery() )->count( $needs ), 'The view lists the four profiles that need classification.' );
		$this->assertSame( 3, $bulk->count( BulkFilter::clean( $needs ) ), 'Three of them are published.' );
	}

	public function test_REQ_193_the_panel_in_the_needs_view_counts_the_view_and_records_it(): void {
		$this->fixtures();
		$filters = ProfilesQuery::filters_from(
			new Request(
				array(
					'classification' => 'needs',
					'source'         => 'bulkview-qwv',
				)
			)
		);
		$actor   = self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) );
		wp_set_current_user( $actor );
		ob_start();
		( new BulkUnpublishPanel() )->render( $filters );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( '2 published profiles match the filter.', $html );
		$this->assertStringContainsString( 'name="confirmed" value="2"', $html );
		$this->assertStringContainsString( 'name="f_classification" value="needs"', $html, 'The confirmation carries the view.' );
		$this->assertStringContainsString( 'The list shows 3 profiles; 2 of them are published', $html );
		$this->assertStringNotContainsString( 'Bulkview Done', $html );

		$service = new BulkUnpublish( new JobRunner( new MemoryQueue() ) );
		$ref     = $service->start( BulkFilter::clean( $filters ), 'Needs classification clean-up', 2, $actor );
		$row     = ( new BulkRepository() )->get( $ref );
		$this->assertIsArray( $row );
		$this->assertSame( 2, (int) $row['match_count'] );
		$this->assertSame( 'needs', json_decode( (string) $row['filter_json'], true )['classification'] ?? '' );
		global $wpdb;
		$after = (string) $wpdb->get_var( $wpdb->prepare( "SELECT new_value FROM {$wpdb->prefix}wiw_audit_log WHERE action = %s AND batch_ref = %s", 'bulk_unpublish.batch', $ref ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertSame( 'needs', json_decode( $after, true )['filter']['classification'] ?? '', 'The audit row records the view used.' );
	}
}
