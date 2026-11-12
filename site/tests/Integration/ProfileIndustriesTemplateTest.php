<?php
/**
 * Industries index and industry page (REQ-007, REQ-009). Needs the WordPress test suite and the profile tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ProfileIndustriesTemplateTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Terms to remove.
	 *
	 * @var int[]
	 */
	private array $terms = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__ ) . '/../wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		switch_theme( 'whoiswho-child' );
		set_query_var( 'wiw_industries', '' );
		set_query_var( 'wiw_industry_slug', '' );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( $this->terms as $term_id ) {
			wp_delete_term( $term_id, Taxonomies::INDUSTRY );
		}
		$this->posts = array();
		$this->terms = array();
		set_query_var( 'wiw_industries', '' );
		set_query_var( 'wiw_industry_slug', '' );
		parent::tearDown();
	}

	/**
	 * Create an industry term.
	 *
	 * @param string $name Name.
	 */
	private function industry( string $name ): string {
		$term = wp_insert_term( $name, Taxonomies::INDUSTRY );
		$this->assertIsArray( $term );
		$this->terms[] = (int) $term['term_id'];
		$made          = get_term( (int) $term['term_id'], Taxonomies::INDUSTRY );
		$this->assertInstanceOf( \WP_Term::class, $made );
		return $made->slug;
	}

	/**
	 * Create a profile in an industry.
	 *
	 * @param string $name     Name.
	 * @param string $slug     Industry slug.
	 * @param bool   $reviewed Reviewed (else pending).
	 */
	private function profile( string $name, string $slug, bool $reviewed ): void {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		wp_set_object_terms( $id, array( $slug ), Taxonomies::INDUSTRY );
		if ( $reviewed ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		}
	}

	/**
	 * Render the industries template.
	 *
	 * @param string $slug Industry slug, or empty for the index.
	 */
	private function render( string $slug ): string {
		set_query_var( 'wiw_industries', '' === $slug ? '1' : '' );
		set_query_var( 'wiw_industry_slug', $slug );
		ob_start();
		include get_template_directory() . '/templates/industries.php';
		return (string) ob_get_clean();
	}

	public function test_REQ_007_the_index_counts_reviewed_profiles_only(): void {
		$slug = $this->industry( 'Indtest Energy' );
		$this->profile( 'Indtest Reviewed One', $slug, true );
		$this->profile( 'Indtest Reviewed Two', $slug, true );
		$this->profile( 'Indtest Pending', $slug, false );
		$out = $this->render( '' );
		$this->assertMatchesRegularExpression( '#Indtest Energy</span>\s*<span class="ind-count">\s*2 profiles#', $out );
		$this->assertStringContainsString( '/industries/' . $slug . '/', $out );
		$this->assertSame( 1, substr_count( $out, '<h1>' ) );
	}

	public function test_REQ_007_an_industry_page_lists_reviewed_profiles_and_never_a_pending_one(): void {
		$slug = $this->industry( 'Indtest Mining' );
		$this->profile( 'Indtest Mining Reviewed', $slug, true );
		$this->profile( 'Indtest Mining Pending', $slug, false );
		$out = $this->render( $slug );
		$this->assertStringContainsString( 'Indtest Mining Reviewed', $out );
		$this->assertStringNotContainsString( 'Indtest Mining Pending', $out );
		$this->assertStringContainsString( '1 reviewed profile in this industry.', $out );
	}

	public function test_REQ_009_an_industry_without_public_profiles_shows_an_empty_state_and_no_stories_block(): void {
		$slug = $this->industry( 'Indtest Empty' );
		$this->profile( 'Indtest Only Pending', $slug, false );
		$out = $this->render( $slug );
		$this->assertStringContainsString( 'No public profiles in this industry yet', $out );
		$this->assertStringNotContainsString( 'Indtest Only Pending', $out );
		$this->assertStringNotContainsString( 'ind-stories', $out, 'The stories block is absent, not empty.' );
	}

	public function test_REQ_007_an_unknown_industry_is_not_a_page(): void {
		set_query_var( 'wiw_industry_slug', 'no-such-industry' );
		$this->assertNull( wiw_industry_term() );
		$this->assertSame( '', wiw_industries_view() );
	}
}
