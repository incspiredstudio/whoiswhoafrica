<?php
/**
 * The starter pages seed (REQ-260, REQ-120): idempotent, never overwrites edits, no commercial terms page. Needs the WordPress
 * test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Pages;

use WhoIsWho\Core\Content\Pages\SeedPagesCommand;
use WhoIsWho\Core\Content\Pages\StarterPages;
use WhoIsWho\Core\Platform\Cli\Output;
use WhoIsWho\Tests\WpTestCase;

final class StarterPagesTest extends WpTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( 0 );
	}

	public function test_REQ_260_seed_creates_the_fixed_routes_with_a_draft_note(): void {
		$result = ( new StarterPages() )->seed();
		foreach ( array( 'about', 'faq', 'awards/rules', 'complaints', 'privacy', 'terms', 'cookies', 'nominate' ) as $path ) {
			$page = get_page_by_path( $path, OBJECT, 'page' );
			if ( 'nominate' === $path ) {
				$this->assertContains( $path, $result['created'] );
				continue;
			}
			$this->assertInstanceOf( \WP_Post::class, $page, $path );
			$this->assertSame( 'publish', $page->post_status, $path );
			$this->assertStringContainsString( 'notice--draft', $page->post_content, $path );
		}
		$this->assertSame( 'awards', get_post( get_page_by_path( 'awards/rules', OBJECT, 'page' )->post_parent )->post_name );
	}

	public function test_REQ_260_faq_has_groups_and_questions(): void {
		( new StarterPages() )->seed();
		$faq = get_page_by_path( 'faq', OBJECT, 'page' );
		$this->assertGreaterThanOrEqual( 3, substr_count( $faq->post_content, '<!-- wp:whoiswho/faq-group ' ) );
		$this->assertGreaterThanOrEqual( 8, substr_count( $faq->post_content, '<!-- wp:whoiswho/faq-question ' ) );
	}

	public function test_REQ_260_seed_is_idempotent_and_does_not_overwrite_edits(): void {
		( new StarterPages() )->seed();
		$about = get_page_by_path( 'about', OBJECT, 'page' );
		wp_update_post(
			array(
				'ID'           => $about->ID,
				'post_content' => 'Edited by staff',
			)
		);
		$count  = (int) wp_count_posts( 'page' )->publish;
		$second = ( new StarterPages() )->seed();
		$this->assertSame( array(), $second['created'] );
		$this->assertSame( $count, (int) wp_count_posts( 'page' )->publish );
		$this->assertSame( 'Edited by staff', get_post( $about->ID )->post_content );
	}

	public function test_REQ_260_a_trashed_or_drafted_page_is_not_recreated(): void {
		( new StarterPages() )->seed();
		$terms = get_page_by_path( 'terms', OBJECT, 'page' );
		wp_trash_post( $terms->ID );
		$nominate = get_posts(
			array(
				'post_type'   => 'page',
				'name'        => 'nominate',
				'post_status' => 'draft',
			)
		);
		$this->assertCount( 1, $nominate );
		$result = ( new StarterPages() )->seed();
		$this->assertNotContains( 'terms', $result['created'] );
		$this->assertNotContains( 'nominate', $result['created'] );
	}

	public function test_REQ_120_no_commercial_terms_page_is_seeded(): void {
		$defs = array_keys( ( new StarterPages() )->definitions() );
		foreach ( StarterPages::FORBIDDEN as $path ) {
			$this->assertNotContains( $path, $defs );
		}
		foreach ( $defs as $path ) {
			$this->assertDoesNotMatchRegularExpression( '/commercial|sponsor|advertis|rate-card/i', $path );
		}
	}

	public function test_REQ_260_command_prints_paths_only_and_exits_zero(): void {
		$out = new Output();
		ob_start();
		$code    = ( new SeedPagesCommand() )->run( array(), $out );
		$printed = (string) ob_get_clean();
		$this->assertStringContainsString( 'created about', $printed );
		$this->assertSame( 0, $code );
		$this->assertSame( 'pages:seed', ( new SeedPagesCommand() )->name() );
	}
}
