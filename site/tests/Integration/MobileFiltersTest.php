<?php
/**
 * Filters on phones (REQ-044, REQ-007, REQ-001): the Filters button with the number of filters on, the active-filter chips that
 * each remove one filter, the bottom sheet with its "Show N results" and "Clear all", and the sector page's industry chips
 * (REQ-009). The no-script form is the same GET form as before. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class MobileFiltersTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
	}

	/**
	 * Render the filter bar.
	 *
	 * @param array<string, mixed> $args Args.
	 */
	private function render( array $args ): string {
		ob_start();
		get_template_part( 'template-parts/filter-bar', null, $args );
		return (string) ob_get_clean();
	}

	/**
	 * Directory-like args with two filters on, a search text, a sort and the list view.
	 *
	 * @return array<string, mixed>
	 */
	private function args(): array {
		return array(
			'action'   => '/people/',
			'label'    => 'Filter profiles',
			'hidden'   => array( 'q' => 'ada' ),
			'controls' => array(
				array(
					'name'    => 'industry',
					'label'   => 'Industry',
					'blank'   => 'All industries',
					'options' => array(
						'energy-sector' => array(
							'label'   => 'Energy',
							'options' => array(
								'energy-sector' => 'All Energy',
								'oil-and-gas'   => 'Oil & gas',
							),
						),
					),
					'value'   => 'oil-and-gas',
				),
				array(
					'name'    => 'country',
					'label'   => 'Country',
					'blank'   => 'All countries',
					'options' => array(
						'nigeria' => 'Nigeria',
						'ghana'   => 'Ghana',
					),
					'value'   => 'ghana',
				),
				array(
					'name'    => 'city',
					'label'   => 'City',
					'blank'   => 'All cities',
					'options' => array( 'accra' => 'Accra' ),
					'value'   => '',
				),
			),
			'sort'     => array(
				'name'    => 'sort',
				'value'   => 'updated',
				'options' => array(
					''        => 'Name A to Z',
					'updated' => 'Recently updated',
				),
			),
			'view'     => array(
				'value' => 'list',
				'grid'  => '/people/',
				'list'  => '/people/?view=list',
			),
			'count'    => '7 profiles',
			'total'    => 7,
			'tabs'     => array(
				'title' => 'Type',
				'items' => array(
					array(
						'label' => 'All',
						'url'   => '/directory/',
					),
					array(
						'label'   => 'People',
						'url'     => '/people/',
						'current' => true,
					),
				),
			),
			'clear'    => true,
		);
	}

	public function test_REQ_044_filters_button_names_the_number_of_filters_on_and_controls_the_sheet(): void {
		$out = $this->render( $this->args() );
		$this->assertMatchesRegularExpression( '/<button type="button" class="fbar-open"[^>]*aria-haspopup="dialog"[^>]*aria-controls="fbar-\d+-sheet"/', $out );
		$this->assertMatchesRegularExpression( '/class="fbar-open-n">\s*\(2\)/', $out );
		preg_match( '/aria-controls="(fbar-\d+-sheet)"/', $out, $m );
		$this->assertStringContainsString( '<dialog class="fbar-sheet" id="' . $m[1] . '"', $out );
	}

	public function test_REQ_044_no_count_on_the_button_when_no_filter_is_on(): void {
		$args                         = $this->args();
		$args['controls'][0]['value'] = '';
		$args['controls'][1]['value'] = '';
		$out                          = $this->render( $args );
		$this->assertStringContainsString( 'class="fbar-open"', $out );
		$this->assertStringNotContainsString( 'fbar-open-n', $out );
		$this->assertStringNotContainsString( 'fbar-chips', $out );
	}

	public function test_REQ_007_each_chip_removes_only_its_own_filter_and_keeps_the_rest(): void {
		$out = $this->render( $this->args() );
		$this->assertSame( 2, substr_count( $out, 'class="fbar-chip"' ) );
		// The industry chip shows the option's label, from its sector group, and drops only industry.
		$this->assertStringContainsString( 'href="/people/?view=list&#038;q=ada&#038;country=ghana&#038;sort=updated"', $out );
		$this->assertStringContainsString( 'Oil &amp; gas', $out );
		$this->assertStringContainsString( 'href="/people/?view=list&#038;q=ada&#038;industry=oil-and-gas&#038;sort=updated"', $out );
		$this->assertStringContainsString( 'Remove filter Industry:', $out );
		$this->assertStringContainsString( 'Remove filter Country:', $out );
		$this->assertStringNotContainsString( 'Remove filter City', $out );
		$this->assertStringNotContainsString( 'Remove filter Sort', $out );
	}

	public function test_REQ_007_filter_state_lists_params_in_force_and_chips(): void {
		$state = wiw_filter_bar_state( $this->args() );
		$this->assertSame(
			array(
				'view'     => 'list',
				'q'        => 'ada',
				'industry' => 'oil-and-gas',
				'country'  => 'ghana',
				'sort'     => 'updated',
			),
			$state['params']
		);
		$this->assertSame( array( 'industry', 'country' ), array_column( $state['chips'], 'name' ) );
		$this->assertSame( '/people/', wiw_filter_url( '/people/', array( 'a' => '' ) ) );
		$this->assertSame( '/people/?a=b%20c', wiw_filter_url( '/people/', array( 'a' => 'b c' ) ) );
	}

	public function test_REQ_044_sheet_has_the_show_results_count_clear_all_and_type_chips(): void {
		$out = $this->render( $this->args() );
		$this->assertMatchesRegularExpression( '/<button type="button" class="btn fbar-sheet-go"[^>]*>Show 7 results<\/button>/', $out );
		$this->assertStringContainsString( 'data-one="Show %d result"', $out );
		$this->assertMatchesRegularExpression( '/class="btn alt fbar-sheet-clear" href="\/people\/">Clear all/', $out );
		$this->assertMatchesRegularExpression( '/<p class="fbar-sheet-k" id="(fbar-\d+-t)">Type<\/p>\s*<ul class="fbar-sheet-tabs" aria-labelledby="\1">.*aria-current="true">People/s', $out );
		$this->assertStringContainsString( 'aria-label="Close filters"', $out );
		$this->assertMatchesRegularExpression( '/<form class="fbar" id="fbar-\d+" method="get"/', $out );
		// The sheet is inside the form and the form keeps exactly one submit button, the hidden Apply button, for no-script use.
		$this->assertMatchesRegularExpression( '/<form class="fbar".*<dialog class="fbar-sheet".*<\/dialog>\s*<\/form>/s', $out );
		$this->assertSame( 1, substr_count( $out, 'type="submit"' ) );
	}

	public function test_REQ_044_one_result_reads_singular_and_no_total_reads_show_results(): void {
		$args          = $this->args();
		$args['total'] = 1;
		$this->assertStringContainsString( '>Show 1 result</button>', $this->render( $args ) );
		unset( $args['total'] );
		$this->assertStringContainsString( '>Show results</button>', $this->render( $args ) );
	}

	public function test_REQ_044_no_filters_button_when_the_bar_has_no_choices(): void {
		$out = $this->render(
			array(
				'action' => '/x/',
				'count'  => '3 things',
			)
		);
		$this->assertStringNotContainsString( 'fbar-open', $out );
	}

	public function test_REQ_009_sector_page_chips_are_its_industries_with_profiles_in_name_order(): void {
		$rows  = array(
			array(
				'term_id' => 1,
				'slug'    => 'energy',
				'name'    => 'Energy',
				'count'   => 5,
				'parent'  => 0,
			),
			array(
				'term_id' => 2,
				'slug'    => 'power',
				'name'    => 'Power',
				'count'   => 2,
				'parent'  => 1,
			),
			array(
				'term_id' => 3,
				'slug'    => 'oil-and-gas',
				'name'    => 'Oil and gas',
				'count'   => 3,
				'parent'  => 1,
			),
			array(
				'term_id' => 4,
				'slug'    => 'mining',
				'name'    => 'Mining',
				'count'   => 0,
				'parent'  => 1,
			),
			array(
				'term_id' => 5,
				'slug'    => 'banking',
				'name'    => 'Banking',
				'count'   => 4,
				'parent'  => 9,
			),
		);
		$chips = wiw_sector_industries( $rows, 1 );
		$this->assertSame( array( 'oil-and-gas', 'power' ), array_column( $chips, 'slug' ) );
		$this->assertSame( home_url( '/industries/oil-and-gas/' ), $chips[0]['url'] );
		$this->assertSame( array(), wiw_sector_industries( $rows, 0 ) );
	}
}
