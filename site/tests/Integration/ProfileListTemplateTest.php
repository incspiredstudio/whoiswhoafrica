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
use WhoIsWho\Core\Directory\PublicProfile;
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

	public function test_REQ_001_the_four_list_addresses_are_routed_to_the_list_variable(): void {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$wp_rewrite->flush_rules( false );
		$rules = implode( "\n", array_keys( (array) $wp_rewrite->wp_rewrite_rules() ) );
		foreach ( array( 'directory', 'people', 'businesses', 'institutions' ) as $path ) {
			$this->assertStringContainsString( $path, $rules, $path );
		}
		$this->assertStringNotContainsString( 'entrepreneurs', $rules, 'People holds entrepreneurs (D-072); the old address only redirects.' );
		$this->assertSame( array( 'All', 'People', 'Businesses', 'Institutions' ), array_values( array_column( wiw_directory_lists(), 'tab' ) ) );
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

	public function test_REQ_007_the_pager_shows_first_last_and_neighbours_with_gaps(): void {
		$this->assertSame( array( 1, 2, 'gap', 837 ), wiw_pager_items( 1, 837 ) );
		$this->assertSame( array( 1, 'gap', 4, 5, 6, 'gap', 837 ), wiw_pager_items( 5, 837 ) );
		$this->assertSame( array( 1, 2, 3, 4, 'gap', 837 ), wiw_pager_items( 3, 837 ), 'a gap never hides a single page' );
		$this->assertSame( array( 1, 'gap', 836, 837 ), wiw_pager_items( 837, 837 ) );
		$this->assertSame( array( 1, 2 ), wiw_pager_items( 2, 2 ) );
		$this->assertSame( '', wiw_pager( 1, 1, static fn ( int $p ): string => '/x/' . $p ) );
	}

	public function test_REQ_043_the_pager_marks_the_current_page_and_offers_go_to_page_on_long_lists(): void {
		$url  = static fn ( int $p ): string => home_url( '/people/' . ( $p > 1 ? 'page/' . $p . '/' : '' ) . '?sort=name' );
		$html = wp_kses( wiw_pager( 5, 837, $url ), wiw_pager_kses() );
		$this->assertStringContainsString( '<nav class="pager" aria-label="Pages">', $html );
		$this->assertSame( 1, substr_count( $html, 'aria-current="page"' ) );
		$this->assertStringContainsString( 'Page 5 of 837', $html );
		$this->assertStringContainsString( 'aria-label="Page 837"', $html );
		$this->assertSame( 2, substr_count( $html, 'class="pager-gap" aria-hidden="true"' ) );
		$this->assertMatchesRegularExpression( '#<a class="pager-step" rel="prev" href="[^"]*/people/page/4/\?sort=name"#', $html );
		$this->assertStringContainsString( 'data-pager-jump="' . esc_attr( home_url( '/people/page/{page}/?sort=name' ) ) . '"', $html, 'the jump box keeps the filters' );
		$this->assertStringContainsString( 'hidden', $html, 'the jump box needs scripts, so it starts hidden' );
		$this->assertStringNotContainsString( 'pager-jump', wiw_pager( 2, 5, $url ), 'no jump box on a short list' );
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
		$this->assertSame( 2, substr_count( $out, 'aria-current="page"' ), 'the current list tab and the current page number' );
		$this->assertStringContainsString( '<span class="pager-step is-off" aria-disabled="true">', $out, 'Previous stays in place, disabled, on page one' );
		$this->assertMatchesRegularExpression( '#<a class="pager-step" rel="next" href="[^"]*/institutions/page/2/"#', $out );
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

	public function test_REQ_254_every_list_has_its_own_search_field_in_the_dark_band_with_chip_tabs(): void {
		foreach ( array( 'all', 'people', 'businesses', 'institutions' ) as $list ) {
			$out   = $this->render( $list );
			$start = (int) strpos( $out, 'page-band' );
			$band  = substr( $out, $start, (int) strpos( $out, '<form class="fbar"' ) - $start );
			$this->assertStringContainsString( 'page-band--dark', $band, $list );
			$this->assertStringContainsString( 'role="search"', $band, $list );
			$this->assertStringContainsString( 'type="search" name="q"', $band, $list );
			$this->assertStringContainsString( 'Narrow this list', $band, $list . ': the field has its own label' );
			$this->assertStringContainsString( '<nav class="tabs" aria-label="Directory lists">', $band, $list );
			$bar = substr( $out, (int) strpos( $out, '<form class="fbar"' ) );
			$this->assertStringNotContainsString( 'type="search"', $bar, $list . ': one search field only' );
			$this->assertStringContainsString( 'Apply filters', $bar, $list );
		}
	}

	public function test_REQ_254_the_band_search_keeps_the_filters_and_the_filter_bar_keeps_the_search_text(): void {
		set_query_var( 'q', 'alpha' );
		set_query_var( 'sort', 'updated' );
		$out  = $this->render( 'all' );
		$band = substr( $out, (int) strpos( $out, 'role="search"' ), 1200 );
		$this->assertStringContainsString( 'name="sort" value="updated"', $band );
		$this->assertStringNotContainsString( 'name="page"', $band );
		$bar = substr( $out, (int) strpos( $out, '<form class="fbar"' ) );
		$this->assertStringContainsString( 'type="hidden" name="q" value="alpha"', $bar );
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

	public function test_REQ_001_the_old_entrepreneurs_addresses_redirect_to_people_keeping_the_other_arguments(): void {
		$this->assertSame( home_url( '/people/' ), wiw_directory_legacy_target( '/entrepreneurs/', array() ) );
		$this->assertSame( home_url( '/people/' ), wiw_directory_legacy_target( '/entrepreneurs', array() ) );
		$this->assertSame(
			home_url( '/people/page/2/?industry=energy&q=ada' ),
			wiw_directory_legacy_target(
				'/entrepreneurs/page/2/',
				array(
					'industry' => 'energy',
					'q'        => 'ada',
				)
			)
		);
		$this->assertSame(
			home_url( '/people/?country=nigeria' ),
			wiw_directory_legacy_target(
				'/directory/',
				array(
					'type'    => 'entrepreneur',
					'country' => 'nigeria',
				)
			)
		);
		$this->assertSame( home_url( '/people/' ), wiw_directory_legacy_target( '/people/', array( 'type' => 'entrepreneurs' ) ) );
		$this->assertSame( '', wiw_directory_legacy_target( '/people/', array( 'industry' => 'energy' ) ) );
		$this->assertSame( '', wiw_directory_legacy_target( '/stories/', array( 'type' => 'entrepreneur' ) ), 'Only directory lists read a type.' );
		$this->assertSame( '', wiw_directory_legacy_target( '/profile/entrepreneurs/', array() ) );

		$saved                     = array(
			'REQUEST_URI'    => $_SERVER['REQUEST_URI'] ?? null,
			'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? null,
		);
		$_SERVER['REQUEST_URI']    = '/entrepreneurs/?sort=updated';
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$seen                      = array();
		$catch                     = static function ( $location, $status ) use ( &$seen ) {
			$seen = array( $status, $location );
			throw new \RuntimeException( 'redirected' );
		};
		add_filter( 'wp_redirect', $catch, 10, 2 );
		try {
			do_action( 'template_redirect' );
		} catch ( \RuntimeException $e ) {
			unset( $e );
		} finally {
			remove_filter( 'wp_redirect', $catch, 10 );
			foreach ( $saved as $key => $value ) {
				if ( null === $value ) {
					unset( $_SERVER[ $key ] );
				} else {
					$_SERVER[ $key ] = $value;
				}
			}
		}
		$this->assertSame( array( 301, home_url( '/people/?sort=updated' ) ), $seen, '/entrepreneurs/ answers 301 to /people/.' );
	}

	public function test_REQ_001_an_entrepreneur_reads_as_a_person_on_the_public_site(): void {
		$kind = wiw_profile_kind( 'entrepreneur' );
		$this->assertSame( 'Person', $kind['label'] );
		$this->assertSame( home_url( '/people/' ), $kind['list'] );
		$this->assertSame( 'People', $kind['list_title'] );
		$this->profile( 'Dirlist Founder', 'entrepreneur', true );
		$out = $this->render( 'people' );
		$this->assertStringContainsString( 'Dirlist Founder', $out, 'An entrepreneur is listed under People.' );
		$this->assertStringContainsString( 'founders', $out, 'The People intro covers founders.' );
		$this->assertStringNotContainsString( 'Entrepreneur', $out );
		$this->assertStringNotContainsString( '/entrepreneurs/', $out );
		$this->assertStringNotContainsString( '/entrepreneurs/', wp_json_encode( wiw_nav_model() ) );
		$this->assertSame( array( '/people/', '/businesses/', '/institutions/' ), array_map( static fn ( array $d ): string => (string) wp_parse_url( $d['url'], PHP_URL_PATH ), wiw_home_doors() ) );
	}

	public function test_REQ_040_the_whole_card_opens_the_profile_with_one_tab_stop(): void {
		$id      = $this->profile( 'Dirlist Card Person', 'individual', true );
		$profile = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $profile );
		ob_start();
		get_template_part( 'template-parts/profile-card', null, array( 'profile' => $profile ) );
		$html = (string) ob_get_clean();
		$doc  = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
		libxml_clear_errors();
		$xpath = new \DOMXPath( $doc );
		$links = $xpath->query( '//a[@href="' . $profile->url . '"]' );
		$focus = 0;
		foreach ( $links as $link ) {
			if ( '-1' !== $link->getAttribute( 'tabindex' ) ) {
				++$focus;
			}
		}
		$this->assertSame( 1, $focus, 'Exactly one focusable link to the profile.' );
		$this->assertSame( 0, $xpath->query( '//a[not(@href="' . $profile->url . '")] | //button' )->length, 'No other interactive element on the card.' );
		$media = $xpath->query( '//a[@href="' . $profile->url . '"][@tabindex="-1"][@aria-hidden="true"]//*[contains(@class,"pcard-media")] | //a[@href="' . $profile->url . '"][@tabindex="-1"][@aria-hidden="true"][contains(@class,"pcard-media")]' );
		$this->assertSame( 1, $media->length, 'The picture is inside a link to the profile that screen readers and the tab key skip.' );
		$this->assertSame( 1, $xpath->query( '//h3[@class="pcard-name"]/a[@class="stretched"]' )->length, 'The name link stretches over the card.' );
	}
}
