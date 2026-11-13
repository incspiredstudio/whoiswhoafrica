<?php
/**
 * The FAQ blocks (REQ-260): grouped questions as accessible disclosures, first one open, structured data off by default.
 * Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class FaqBlockTest extends TestCase {

	private const CONTENT = '<!-- wp:whoiswho/faq-group {"heading":"Being listed"} -->
<!-- wp:whoiswho/faq-question {"title":"How do I get listed?"} -->
<!-- wp:paragraph --><p>Use the <a href="/get-listed/">Get listed</a> page.</p><!-- /wp:paragraph -->
<!-- /wp:whoiswho/faq-question -->
<!-- wp:whoiswho/faq-question {"title":"Does it cost anything?"} -->
<!-- wp:paragraph --><p>No.</p><!-- /wp:paragraph -->
<!-- /wp:whoiswho/faq-question -->
<!-- /wp:whoiswho/faq-group -->
<!-- wp:whoiswho/faq-group {"heading":"Awards"} -->
<!-- wp:whoiswho/faq-question {"title":"Who can nominate?"} -->
<!-- wp:paragraph --><p>Anyone.</p><!-- /wp:paragraph -->
<!-- /wp:whoiswho/faq-question -->
<!-- /wp:whoiswho/faq-group -->';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( 'whoiswho/faq-group' ) ) {
			do_action( 'init' );
		}
		wiw_faq_opened( null, true );
		delete_option( 'wiw_faq_schema' );
	}

	protected function tearDown(): void {
		wiw_faq_opened( null, true );
		remove_all_filters( 'wiw_faq_schema_enabled' );
		delete_option( 'wiw_faq_schema' );
		parent::tearDown();
	}

	public function test_REQ_260_the_blocks_are_registered_and_a_question_only_lives_in_a_group(): void {
		// The WordPress test case resets the script registry between tests, so register again (the blocks register once).
		wiw_faq_register();
		$registry = \WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'whoiswho/faq-group' ) );
		$question = $registry->get_registered( 'whoiswho/faq-question' );
		$this->assertInstanceOf( \WP_Block_Type::class, $question );
		$this->assertSame( array( 'whoiswho/faq-group' ), $question->parent );
		$this->assertTrue( wp_script_is( 'wiw-faq-editor', 'registered' ), 'The editor script is registered with its dependencies.' );
		$this->assertContains( 'wp-block-editor', wp_scripts()->registered['wiw-faq-editor']->deps );
		$this->assertContains( 'wiw-faq-editor', $registry->get_registered( 'whoiswho/faq-group' )->editor_script_handles );
	}

	public function test_REQ_260_groups_render_in_order_with_their_questions_as_disclosures(): void {
		$out = do_blocks( self::CONTENT );
		$this->assertSame( 2, substr_count( $out, '<section class="faq-group">' ) );
		$this->assertSame( 3, substr_count( $out, '<details class="faq-q"' ) );
		$this->assertSame( 3, substr_count( $out, '<summary>' ) );
		$this->assertLessThan( strpos( $out, 'Awards' ), strpos( $out, 'Being listed' ), 'groups keep their order' );
		$this->assertLessThan( strpos( $out, 'Does it cost anything?' ), strpos( $out, 'How do I get listed?' ), 'questions keep their order' );
		$this->assertStringContainsString( '<h2 class="faq-group-h">Being listed</h2>', $out );
		$this->assertStringContainsString( '<a href="/get-listed/">Get listed</a>', $out, 'answers keep their links' );
	}

	public function test_REQ_260_only_the_first_question_on_the_page_is_open(): void {
		$out = do_blocks( self::CONTENT );
		$this->assertSame( 1, substr_count( $out, '<details class="faq-q" open' ) );
		$this->assertMatchesRegularExpression( '#<details class="faq-q" open><summary>How do I get listed\?#', $out );
	}

	public function test_REQ_260_a_question_without_a_title_is_not_rendered_and_titles_are_escaped(): void {
		$out = do_blocks( '<!-- wp:whoiswho/faq-group {"heading":"G"} --><!-- wp:whoiswho/faq-question {"title":""} --><!-- wp:paragraph --><p>Orphan answer.</p><!-- /wp:paragraph --><!-- /wp:whoiswho/faq-question --><!-- wp:whoiswho/faq-question {"title":"<script>x</script> Safe?"} --><!-- wp:paragraph --><p>Yes.</p><!-- /wp:paragraph --><!-- /wp:whoiswho/faq-question --><!-- /wp:whoiswho/faq-group -->' );
		$this->assertStringNotContainsString( 'Orphan answer.', $out );
		$this->assertStringNotContainsString( '<script', $out );
		$this->assertStringContainsString( 'Safe?', $out );
	}

	public function test_REQ_260_structured_data_is_off_by_default_and_complete_when_switched_on(): void {
		$this->assertFalse( wiw_faq_schema_enabled() );
		$json = wiw_faq_schema_json( self::CONTENT );
		$data = json_decode( $json, true );
		$this->assertSame( 'FAQPage', $data['@type'] );
		$this->assertCount( 3, $data['mainEntity'] );
		$this->assertSame( 'How do I get listed?', $data['mainEntity'][0]['name'] );
		$this->assertStringContainsString( 'Get listed', $data['mainEntity'][0]['acceptedAnswer']['text'] );
		$this->assertSame( '', wiw_faq_schema_json( '<!-- wp:paragraph --><p>No FAQ here.</p><!-- /wp:paragraph -->' ) );
		update_option( 'wiw_faq_schema', '1' );
		$this->assertTrue( wiw_faq_schema_enabled() );
	}

	public function test_REQ_260_the_structured_data_is_printed_only_on_the_faq_page_and_only_when_on(): void {
		$page = self::make_page( 'faq', self::CONTENT );
		$this->go_to( get_permalink( $page ) );
		ob_start();
		do_action( 'wp_head' );
		$off = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'FAQPage', $off );

		update_option( 'wiw_faq_schema', '1' );
		ob_start();
		do_action( 'wp_head' );
		$on = (string) ob_get_clean();
		$this->assertStringContainsString( '"@type":"FAQPage"', $on );

		$other = self::make_page( 'faq-elsewhere', self::CONTENT );
		$this->go_to( get_permalink( $other ) );
		ob_start();
		do_action( 'wp_head' );
		$elsewhere = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'FAQPage', $elsewhere, 'only on the FAQ page' );
		wp_delete_post( $page, true );
		wp_delete_post( $other, true );
	}

	/**
	 * Create a published page.
	 *
	 * @param string $slug    Slug.
	 * @param string $content Content.
	 */
	private static function make_page( string $slug, string $content ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $slug,
				'post_name'    => $slug,
				'post_content' => $content,
			)
		);
	}

	/**
	 * Point the main query at an address.
	 *
	 * @param string $url Address.
	 */
	private function go_to( string $url ): void {
		global $wp_query, $post;
		$id       = url_to_postid( $url );
		$post     = get_post( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$wp_query = new \WP_Query( array( 'page_id' => $id ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$wp_query->get_posts();
	}
}
