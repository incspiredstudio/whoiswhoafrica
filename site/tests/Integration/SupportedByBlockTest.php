<?php
/**
 * The Supported by block (M3-COM-15, M4-AWD-25; REQ-186, REQ-199): absent when no sponsor is attached, otherwise name or logo, link
 * and the independence note, and nothing commercial. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Content\PublicSponsor;
use WhoIsWho\Tests\WpTestCase;

final class SupportedByBlockTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( 0 );
		switch_theme( 'whoiswho-child' );
		if ( ! post_type_exists( PublicSponsor::TYPE ) ) {
			register_post_type( PublicSponsor::TYPE, array( 'public' => false ) );
		}
	}

	private function render_for( int $page_id ): string {
		$GLOBALS['post'] = get_post( $page_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- test setup.
		setup_postdata( $GLOBALS['post'] );
		return render_block(
			array(
				'blockName'    => 'wiw/supported-by',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	public function test_REQ_186_the_block_is_registered_and_dynamic(): void {
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( 'wiw/supported-by' );
		$this->assertNotNull( $type );
		$this->assertTrue( $type->is_dynamic() );
	}

	public function test_REQ_186_the_block_is_absent_when_there_are_no_sponsors(): void {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertSame( '', trim( $this->render_for( $page ) ) );
	}

	public function test_REQ_199_a_sponsor_shows_name_link_and_the_note_and_nothing_commercial(): void {
		$page    = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$sponsor = self::factory()->post->create(
			array(
				'post_type'   => PublicSponsor::TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Example Sponsor Ltd',
			)
		);
		update_post_meta( $sponsor, PublicSponsor::META_ATT, 'page:' . $page );
		update_post_meta( $sponsor, PublicSponsor::META_LINK, 'https://sponsor.example/' );
		update_post_meta( $sponsor, 'wiw_deal_amount', '5000000' );
		$out = $this->render_for( $page );
		$this->assertStringContainsString( 'Supported by', $out );
		$this->assertStringContainsString( 'Example Sponsor Ltd', $out );
		$this->assertStringContainsString( 'href="https://sponsor.example/"', $out );
		$this->assertStringContainsString( 'rel="sponsored noopener"', $out );
		$this->assertStringContainsString( 'do not choose or influence nominees or winners', $out );
		$this->assertStringNotContainsString( '5000000', $out );
		$this->assertDoesNotMatchRegularExpression( '/amount|price|kobo|deal/i', $out );
	}

	public function test_REQ_186_a_sponsor_of_another_page_does_not_show(): void {
		$page    = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$other   = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$sponsor = self::factory()->post->create(
			array(
				'post_type'   => PublicSponsor::TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Elsewhere Sponsor',
			)
		);
		update_post_meta( $sponsor, PublicSponsor::META_ATT, 'page:' . $other );
		$this->assertStringNotContainsString( 'Elsewhere Sponsor', $this->render_for( $page ) );
	}

	public function test_REQ_186_the_pattern_is_registered_and_inserts_the_block(): void {
		$registry = \WP_Block_Patterns_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'whoiswho/supported-by' ) );
		$pattern = $registry->get_registered( 'whoiswho/supported-by' );
		$this->assertStringContainsString( 'wp:wiw/supported-by', (string) $pattern['content'] );
	}
}
