<?php
/**
 * Directory list template and routes (REQ-001, REQ-007, REQ-218, REQ-208). Needs the WordPress test suite and the profile tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ProfileListTemplateTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__ ) . '/../wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		switch_theme( 'whoiswho-child' );
		foreach ( array( 'wiw_list', 'paged', 'industry', 'sort', 'q' ) as $var ) {
			set_query_var( $var, '' );
		}
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		foreach ( array( 'wiw_list', 'paged', 'industry', 'sort', 'q' ) as $var ) {
			set_query_var( $var, '' );
		}
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '' );
		$wp_rewrite->flush_rules( false );
		parent::tearDown();
	}

	/**
	 * Create a profile.
	 *
	 * @param string $name     Name.
	 * @param string $type     Entity type.
	 * @param bool   $reviewed Reviewed (else pending).
	 */
	private function profile( string $name, string $type, bool $reviewed ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => $type ) );
		( new ProfileFields() )->set( $id, 'industry', 'Logistics' );
		( new ProvenanceRepository() )->record(
			$id,
			array(
				'field'        => 'industry',
				'source_url'   => 'https://example.org/src',
				'retrieved_at' => '2026-11-10T09:00:00Z',
				'confidence'   => 'high',
				'method'       => 'import',
			)
		);
		if ( $reviewed ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		}
		return $id;
	}

	/**
	 * Render the list template for a list.
	 *
	 * @param string $list_key List key.
	 */
	private function render( string $list_key ): string {
		set_query_var( 'wiw_list', $list_key );
		ob_start();
		include get_template_directory() . '/templates/directory.php';
		return (string) ob_get_clean();
	}

	public function test_REQ_001_the_five_list_addresses_are_routed_to_the_list_variable(): void {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$wp_rewrite->flush_rules( false );
		$rules = implode( "\n", array_keys( (array) $wp_rewrite->wp_rewrite_rules() ) );
		foreach ( array( 'directory', 'people', 'entrepreneurs', 'businesses', 'institutions' ) as $path ) {
			$this->assertStringContainsString( $path, $rules, $path );
		}
		set_query_var( 'wiw_list', 'directory' );
		$this->assertSame( 'all', wiw_directory_current_list() );
		$this->assertStringEndsWith( '/directory/', wiw_directory_url( 'all' ) );
		$params = array(
			'industry' => 'energy',
			'page'     => 3,
			'sort'     => '',
		);
		$this->assertStringEndsWith( '/people/page/3/?industry=energy', wiw_directory_url( 'people', $params ) );
	}

	public function test_REQ_218_a_pending_profile_is_never_named_on_any_list(): void {
		$this->profile( 'Dirlist Pending Org', 'organisation', false );
		$this->profile( 'Dirlist Reviewed Org', 'organisation', true );
		foreach ( array( 'all', 'businesses' ) as $list ) {
			$out = $this->render( $list );
			$this->assertStringContainsString( 'Dirlist Reviewed Org', $out, $list );
			$this->assertStringNotContainsString( 'Dirlist Pending Org', $out, $list );
		}
		$this->assertStringNotContainsString( 'Dirlist Reviewed Org', $this->render( 'people' ), 'a business is not on People' );
	}

	public function test_REQ_007_sixteen_a_page_with_pagination_and_the_tabs_mark_the_current_list(): void {
		for ( $i = 1; $i <= 17; $i++ ) {
			$this->profile( sprintf( 'Dirlist Page Org %02d', $i ), 'institution', true );
		}
		$out = $this->render( 'institutions' );
		$this->assertSame( 16, substr_count( $out, '<article class="pcard' ) );
		$this->assertStringContainsString( 'Page 1 of 2', $out );
		$this->assertStringContainsString( '/institutions/page/2/', $out );
		$this->assertMatchesRegularExpression( '#<a href="[^"]*/institutions/" aria-current="page">Institutions</a>#', $out );
		$this->assertSame( 1, substr_count( $out, 'aria-current="page">' ) - substr_count( $out, 'pager-pos' ) );
		$this->assertSame( 1, substr_count( $out, '<h1>' ) );
		set_query_var( 'paged', 2 );
		$this->assertSame( 1, substr_count( $this->render( 'institutions' ), '<article class="pcard' ) );
	}

	public function test_REQ_007_a_filter_with_no_match_shows_the_empty_state_with_clear_filters(): void {
		$this->profile( 'Dirlist Filter Org', 'organisation', true );
		set_query_var( 'industry', 'no-such-industry' );
		$out = $this->render( 'all' );
		$this->assertStringContainsString( 'No profiles match', $out );
		$this->assertStringContainsString( 'Clear filters', $out );
		$this->assertStringContainsString( 'fbar-clear', $out );
		$this->assertStringNotContainsString( 'Dirlist Filter Org', $out );
	}

	public function test_REQ_208_the_filter_bar_offers_only_filters_that_apply_to_the_list(): void {
		$people = $this->render( 'people' );
		$this->assertStringContainsString( 'name="profession"', $people );
		$this->assertStringNotContainsString( 'name="org_type"', $people );
		$orgs = $this->render( 'businesses' );
		$this->assertStringContainsString( 'name="org_type"', $orgs );
		$this->assertStringNotContainsString( 'name="profession"', $orgs );
	}

	public function test_REQ_254_every_list_has_its_own_narrowing_box_first_in_the_filter_bar(): void {
		foreach ( array( 'all', 'people', 'entrepreneurs', 'businesses', 'institutions' ) as $list ) {
			$out = $this->render( $list );
			$bar = substr( $out, (int) strpos( $out, '<form class="fbar"' ) );
			$this->assertStringContainsString( 'name="q"', $bar, $list );
			$this->assertLessThan( (int) strpos( $bar, '<select' ), (int) strpos( $bar, 'name="q"' ), $list . ': search field comes first' );
			$this->assertStringContainsString( 'Narrow this list', $bar, $list );
			$this->assertStringContainsString( 'Apply filters', $bar, $list );
		}
	}

	public function test_REQ_008_the_narrowing_text_is_kept_in_the_field_and_the_address_and_is_not_the_site_search(): void {
		set_query_var( 'q', '  Alpha <b>Beta</b> ' );
		$out = $this->render( 'all' );
		$this->assertStringContainsString( 'value="Alpha Beta"', $out );
		$this->assertStringNotContainsString( '<b>Beta', $out );
		$this->assertStringContainsString( 'fbar-clear', $out, 'Clear shows while a narrowing text is on' );
		$this->assertStringNotContainsString( 'name="s"', $out, 'the box does not submit to the site search' );
		$params = array(
			'q'        => 'alpha',
			'industry' => 'energy',
		);
		$this->assertStringEndsWith( '/directory/?q=alpha&industry=energy', wiw_directory_url( 'all', $params ) );
	}
}
