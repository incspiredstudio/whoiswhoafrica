<?php
/**
 * Dynamic blocks (M4-PGE-03; REQ-260, REQ-052): figures, the Awards stage button, the Awards categories and the edition label
 * show data the system knows and cannot be typed over. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class DynamicBlocksTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		wp_set_current_user( 0 );
		switch_theme( 'whoiswho-child' );
	}

	/**
	 * Render a block by name with the attributes an editor could have typed.
	 *
	 * @param string               $name  Block name.
	 * @param array<string, mixed> $attrs Attributes.
	 * @param string               $inner Inner HTML an editor typed.
	 */
	private function render( string $name, array $attrs = array(), string $inner = '' ): string {
		return render_block(
			array(
				'blockName'    => $name,
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => $inner,
				'innerContent' => array( $inner ),
			)
		);
	}

	public function test_REQ_260_the_four_blocks_are_registered_as_dynamic(): void {
		foreach ( array( 'wiw/figures', 'wiw/awards-stage-button', 'wiw/awards-categories', 'wiw/edition' ) as $name ) {
			$type = \WP_Block_Type_Registry::get_instance()->get_registered( $name );
			$this->assertNotNull( $type, $name );
			$this->assertTrue( $type->is_dynamic(), $name . ' renders on the server' );
		}
	}

	public function test_REQ_260_figures_show_four_calculated_counts_and_ignore_typed_content(): void {
		$out = $this->render( 'wiw/figures', array( 'profiles' => '99999' ), '<dl><dd>12345</dd></dl>' );
		foreach ( array( 'Profiles', 'Industries', 'Countries', 'Stories' ) as $label ) {
			$this->assertStringContainsString( '<dt>' . $label . '</dt>', $out );
		}
		$this->assertSame( 4, preg_match_all( '#<dd>\d+</dd>#', $out ) );
		$this->assertStringNotContainsString( '99999', $out );
		$this->assertStringNotContainsString( '12345', $out );
		$this->assertStringContainsString( 'ab-figs', $out );
	}

	public function test_REQ_259_stage_button_follows_the_stage_read_model(): void {
		$button = \WhoIsWho\Core\Awards\AwardsStage::for_site()->button();
		$out    = $this->render( 'wiw/awards-stage-button', array( 'label' => 'Typed over' ) );
		$this->assertStringContainsString( esc_html( $button['label'] ), $out );
		$this->assertStringContainsString( 'href="' . esc_url( $button['url'] ) . '"', $out );
		$this->assertStringNotContainsString( 'Typed over', $out );
	}

	public function test_REQ_260_categories_block_reads_the_award_categories(): void {
		global $wpdb;
		( new \WhoIsWho\Core\Platform\Migrations\Runner( dirname( __DIR__ ) . '/../wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$table = \WhoIsWho\Core\Awards\AwardsTables::table( \WhoIsWho\Core\Awards\AwardsTables::CATEGORIES );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'group_key'  => 'technology-innovation',
				'title'      => 'Block Test Category',
				'sort_order' => 1,
				'archived'   => 0,
			)
		);
		$id = (int) $wpdb->insert_id;
		try {
			$out = $this->render( 'wiw/awards-categories', array(), '<p>wiw-typed</p>' );
			foreach ( \WhoIsWho\Core\Awards\AwardsTables::GROUPS as $label ) {
				$this->assertStringContainsString( esc_html( $label ), $out );
			}
			$this->assertStringContainsString( 'Block Test Category', $out );
			$this->assertStringContainsString( 'href="' . esc_url( home_url( '/awards/category/' . $id . '/' ) ) . '"', $out );
			$this->assertStringNotContainsString( 'wiw-typed', $out );
		} finally {
			$wpdb->delete( $table, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	public function test_REQ_052_edition_label_comes_from_the_brand_configuration(): void {
		$out = $this->render( 'wiw/edition', array( 'text' => 'Typed edition' ) );
		$this->assertStringContainsString( esc_html( wiw_brand( 'edition' ) ), $out );
		$this->assertStringNotContainsString( 'Typed edition', $out );
	}
}
