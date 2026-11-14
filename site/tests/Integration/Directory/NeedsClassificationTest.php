<?php
/**
 * The Profiles list's "Needs classification" view (REQ-009, REQ-091): profiles whose main industry is missing or Unclassified, or
 * that have no main country, with a count in the view links.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Classification;
use WhoIsWho\Core\Directory\Admin\ProfilesQuery;
use WhoIsWho\Core\Directory\Admin\ProfilesScreen;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\WpTestCase;

final class NeedsClassificationTest extends WpTestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		( new Taxonomies() )->register();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
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

	private function profile( string $name, int $industry, int $country ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => 'organisation' ) );
		if ( $industry > 0 ) {
			update_post_meta( $id, Classification::PRIMARY_INDUSTRY_META, (string) $industry );
		}
		if ( $country > 0 ) {
			update_post_meta( $id, Classification::PRIMARY_COUNTRY_META, (string) $country );
		}
		return $id;
	}

	public function test_REQ_009_the_needs_classification_view_lists_unclassified_or_countryless_profiles(): void {
		$existing = get_term_by( 'slug', Classification::unclassified_slug(), Taxonomies::INDUSTRY );
		$unc      = $existing instanceof \WP_Term ? (int) $existing->term_id : self::factory()->term->create(
			array(
				'taxonomy' => Taxonomies::INDUSTRY,
				'name'     => 'Unclassified',
				'slug'     => Classification::unclassified_slug(),
			)
		);
		$sector   = self::factory()->term->create( array( 'taxonomy' => Taxonomies::INDUSTRY ) );
		$leaf     = self::factory()->term->create(
			array(
				'taxonomy' => Taxonomies::INDUSTRY,
				'parent'   => $sector,
			)
		);
		$country  = self::factory()->term->create( array( 'taxonomy' => Taxonomies::PLACE ) );
		$done     = $this->profile( 'Zq Classified Body', $leaf, $country );
		$unknown  = $this->profile( 'Zq Unclassified Body', $unc, $country );
		$nowhere  = $this->profile( 'Zq Countryless Body', $leaf, 0 );
		$blank    = $this->profile( 'Zq Blank Body', 0, 0 );

		$filters = ProfilesQuery::filters_from(
			new Request(
				array(
					'classification' => 'needs',
					'name'           => 'Zq ',
				)
			)
		);
		$this->assertSame( 'needs', $filters['classification'] );
		$ids = array_column( ( new ProfilesQuery() )->run( $filters )['rows'], 'id' );
		$this->assertEqualsCanonicalizing( array( $unknown, $nowhere, $blank ), $ids );
		$this->assertNotContains( $done, $ids );
		$this->assertSame( 3, ( new ProfilesQuery() )->count( $filters ) );
		$this->assertArrayNotHasKey( 'classification', ProfilesQuery::filters_from( new Request( array( 'classification' => 'other' ) ) ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_editor' ) ) );
		$_GET = array(
			'page'           => ProfilesScreen::SCREEN,
			'classification' => 'needs',
		);
		ob_start();
		( new ProfilesScreen() )->render();
		$html  = (string) ob_get_clean();
		$_GET  = array();
		$needs = ( new ProfilesQuery() )->count( array( 'classification' => 'needs' ) );
		$this->assertMatchesRegularExpression( '#class="current" aria-current="page">Needs classification <span class="count">\(' . $needs . '\)</span>#', $html );
		$this->assertStringContainsString( 'name="classification" value="needs"', $html, 'the filter form keeps the view' );
		$this->assertStringContainsString( 'Zq Countryless Body', $html );
		$this->assertStringNotContainsString( 'Zq Classified Body', $html );
	}
}
