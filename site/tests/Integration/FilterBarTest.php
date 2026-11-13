<?php
/**
 * The shared filter bar and text tabs (REQ-029, REQ-264). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class FilterBarTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
	}

	/**
	 * Render a part.
	 *
	 * @param string               $part Part.
	 * @param array<string, mixed> $args Args.
	 */
	private function render( string $part, array $args ): string {
		ob_start();
		get_template_part( $part, null, $args );
		return (string) ob_get_clean();
	}

	/**
	 * Standard args.
	 *
	 * @param array<string, mixed> $over Overrides.
	 * @return array<string, mixed>
	 */
	private function args( array $over = array() ): array {
		return array_merge(
			array(
				'action'   => '/stories/',
				'label'    => 'Filter stories',
				'hidden'   => array( 'type' => 'Profile' ),
				'controls' => array(
					array(
						'name'    => 'industry',
						'label'   => 'Industry',
						'blank'   => 'All industries',
						'options' => array(
							'energy' => 'Energy',
							'health' => 'Health',
						),
						'value'   => '',
					),
				),
				'count'    => '12 stories',
				'clear'    => false,
			),
			$over
		);
	}

	public function test_REQ_029_form_is_a_get_form_with_a_name_a_status_count_and_a_hidden_apply_button(): void {
		$out = $this->render( 'template-parts/filter-bar', $this->args() );
		$this->assertStringContainsString( 'method="get"', $out );
		$this->assertStringContainsString( 'aria-label="Filter stories"', $out );
		$this->assertStringContainsString( 'role="status">12 stories', $out );
		$this->assertStringContainsString( '<button class="sr" type="submit">Apply filters', $out );
		$this->assertStringContainsString( 'name="type" value="Profile"', $out );
		$this->assertStringContainsString( 'aria-label="Industry"', $out );
	}

	public function test_REQ_029_clear_link_shows_only_while_a_filter_is_on_and_active_filter_is_marked(): void {
		$this->assertStringNotContainsString( 'fbar-clear', $this->render( 'template-parts/filter-bar', $this->args() ) );
		$args                         = $this->args( array( 'clear' => true ) );
		$args['controls'][0]['value'] = 'energy';
		$out                          = $this->render( 'template-parts/filter-bar', $args );
		$this->assertStringContainsString( 'class="fbar-clear" href="/stories/"', $out );
		$this->assertStringContainsString( 'fbar-sel is-on', $out );
		$this->assertStringContainsString( '<option value="energy" selected=\'selected\'>', $out );
	}

	public function test_REQ_029_search_and_sort_render_and_output_is_escaped(): void {
		$out = $this->render(
			'template-parts/filter-bar',
			$this->args(
				array(
					'search'   => array(
						'name'  => 'q',
						'value' => '"><script>x</script>',
					),
					'sort'     => array(
						'name'    => 'sort',
						'value'   => 'new',
						'options' => array(
							'new' => 'Newest',
							'az'  => 'A to Z',
						),
					),
					'controls' => array(),
				)
			)
		);
		$this->assertStringNotContainsString( '<script>x', $out );
		$this->assertStringContainsString( 'type="search" name="q"', $out );
		$this->assertStringContainsString( 'Sort: Newest', $out );
	}

	public function test_REQ_264_text_tabs_mark_the_current_one_and_render_nothing_when_empty(): void {
		$out = $this->render(
			'template-parts/text-tabs',
			array(
				'label' => 'Story types',
				'items' => array(
					array(
						'label' => 'All',
						'url'   => '/stories/',
					),
					array(
						'label'   => 'Profile',
						'url'     => '/stories/?type=Profile',
						'current' => true,
					),
				),
			)
		);
		$this->assertSame( 1, substr_count( $out, 'aria-current="page"' ) );
		$this->assertStringContainsString( 'aria-label="Story types"', $out );
		$this->assertSame( '', trim( $this->render( 'template-parts/text-tabs', array( 'label' => 'x' ) ) ) );
	}

	public function test_REQ_007_view_switch_marks_the_current_view_and_links_keep_the_filters(): void {
		$out = $this->render(
			'template-parts/filter-bar',
			$this->args(
				array(
					'view' => array(
						'value' => 'list',
						'grid'  => '/people/?industry=energy',
						'list'  => '/people/?industry=energy&view=list',
					),
				)
			)
		);
		$this->assertStringContainsString( 'class="view-toggle"', $out );
		$this->assertStringContainsString( 'href="/people/?industry=energy"', $out );
		$this->assertSame( 1, substr_count( $out, 'aria-current="true"' ) );
		$this->assertMatchesRegularExpression( '/aria-current="true"[^>]*>.*?List/s', $out );
		$this->assertStringContainsString( 'name="view" value="list"', $out );
	}

	public function test_REQ_007_no_view_switch_when_not_asked_for(): void {
		$out = $this->render( 'template-parts/filter-bar', $this->args() );
		$this->assertStringNotContainsString( 'view-toggle', $out );
		$this->assertStringNotContainsString( 'name="view"', $out );
	}
}
