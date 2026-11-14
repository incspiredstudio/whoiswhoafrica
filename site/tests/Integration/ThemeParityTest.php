<?php
/**
 * Theme parity with the prototype (issue #808): the page band's tones, search field and tab chips, the About pattern's accent,
 * portrait trio and figures, the Portrait trio block, the theme search form and the home nominee chips. Needs the WordPress test
 * suite and the profile tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

final class ThemeParityTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__ ) . '/../wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		switch_theme( 'whoiswho-child' );
	}

	/**
	 * Render a part.
	 *
	 * @param string               $part Part.
	 * @param array<string, mixed> $args Args.
	 */
	private function render( string $part, array $args = array() ): string {
		ob_start();
		get_template_part( $part, null, $args );
		return (string) ob_get_clean();
	}

	public function test_REQ_001_the_dark_band_carries_the_pattern_the_search_field_and_chip_tabs(): void {
		$out = $this->render(
			'template-parts/page-band',
			array(
				'title'   => 'The directory',
				'tone'    => 'dark',
				'compact' => true,
				'search'  => array(
					'action' => '/directory/',
					'name'   => 'q',
					'value'  => 'al"pha',
					'label'  => 'Narrow this list',
					'hidden' => array(
						'sort'     => 'updated',
						'industry' => '',
					),
				),
				'tabs'    => array(
					'label' => 'Directory lists',
					'items' => array(
						array(
							'label'   => 'All',
							'url'     => '/directory/',
							'current' => true,
						),
						array(
							'label' => 'People',
							'url'   => '/people/',
						),
					),
				),
			)
		);
		foreach ( array( 'page-band--dark', 'title-band', 'on-dark', 'pattern', 'title-band--compact' ) as $class ) {
			$this->assertStringContainsString( $class, $out );
		}
		$this->assertMatchesRegularExpression( '#<label class="sr" for="(band-q-[^"]+)">Narrow this list</label>\s*<input id="\1" type="search" name="q"#', $out, 'the field has its own label' );
		$this->assertStringContainsString( 'value="al&quot;pha"', $out, 'the value is escaped' );
		$this->assertStringContainsString( 'name="sort" value="updated"', $out );
		$this->assertStringNotContainsString( 'name="industry"', $out, 'empty filters stay out of the address' );
		$this->assertStringContainsString( '<nav class="tabs" aria-label="Directory lists">', $out );
		$this->assertStringContainsString( 'aria-current="page">All</a>', $out );
	}

	public function test_REQ_001_light_and_coloured_tones_and_the_plain_default(): void {
		$light = array(
			'title' => 'FAQ',
			'tone'  => 'light',
		);
		$blue  = array(
			'title' => 'Rights',
			'tone'  => 'blue',
		);
		$this->assertStringContainsString( 'title-band--light', $this->render( 'template-parts/page-band', $light ) );
		$this->assertStringContainsString( 'tone-blue', $this->render( 'template-parts/page-band', $blue ) );
		$plain = $this->render( 'template-parts/page-band', array( 'title' => 'Plain' ) );
		$this->assertStringContainsString( 'section--tight', $plain );
		$this->assertStringNotContainsString( 'wrap--text', $plain, 'every band lines up with the wrap' );
		$this->assertStringNotContainsString( 'role="search"', $plain );
	}

	public function test_REQ_260_the_about_pattern_has_the_accent_the_portrait_trio_and_the_figures(): void {
		$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( 'whoiswho/about-page' );
		$this->assertIsArray( $pattern );
		$content = (string) $pattern['content'];
		$this->assertStringContainsString( '<span class="ab-accent">', $content );
		$this->assertStringContainsString( '<!-- wp:wiw/portrait-trio /-->', $content );
		$this->assertStringContainsString( '<!-- wp:wiw/figures /-->', $content );
		$this->assertStringContainsString( 'is-style-outline', $content );
	}

	public function test_REQ_052_the_portrait_trio_block_shows_three_decorative_tiles_with_approved_marks_only(): void {
		$this->assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( 'wiw/portrait-trio' ) );
		$out = wiw_render_portrait_trio_block();
		$this->assertStringContainsString( 'class="ab-stack wiw-portrait-trio" aria-hidden="true"', $out );
		$this->assertSame( 3, substr_count( $out, 'class="ab-stack-item"' ) );
		preg_match_all( '#poster-mark--([a-z]+)#', $out, $marks );
		foreach ( $marks[1] as $mark ) {
			$this->assertContains( $mark, array( 'star', 'comb', 'link', 'spiral' ), 'only the four approved marks' );
		}
	}

	public function test_REQ_008_the_theme_search_form_submits_q_to_the_search_page(): void {
		ob_start();
		get_search_form();
		$out = (string) ob_get_clean();
		$this->assertStringContainsString( 'role="search"', $out );
		$this->assertStringContainsString( 'action="' . esc_url( home_url( '/search/' ) ) . '"', $out );
		$this->assertStringContainsString( 'name="q"', $out );
		$this->assertStringNotContainsString( 'name="s"', $out );
	}
}
