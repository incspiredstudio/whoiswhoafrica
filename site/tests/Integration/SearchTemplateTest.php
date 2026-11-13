<?php
/**
 * The search page template reads the search service and the read models only (REQ-254, REQ-008, REQ-208, REQ-182).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class SearchTemplateTest extends TestCase {

	/**
	 * The template source.
	 */
	private function source(): string {
		$path = dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho/templates/search.php';
		$this->assertFileExists( $path );
		return (string) file_get_contents( $path );
	}

	public function test_REQ_254_search_template_reads_only_the_search_service(): void {
		$src = $this->source();
		$this->assertStringContainsString( 'SearchService::for_site()', $src );
		foreach ( array( 'WP_Query', 'get_posts(', 'get_post_meta', 'query_posts', '$wpdb', 'get_post(' ) as $forbidden ) {
			$this->assertStringNotContainsString( $forbidden, $src, $forbidden . ' must not appear in the search template' );
		}
	}

	public function test_REQ_208_cards_are_built_through_the_read_models(): void {
		$src = $this->source();
		$this->assertStringContainsString( 'PublicProfile::find_by_id', $src );
		$this->assertStringContainsString( 'PublicStory::find', $src );
	}

	public function test_REQ_182_the_search_form_is_a_plain_get_form_that_works_without_scripts(): void {
		$src = $this->source();
		$this->assertStringContainsString( 'method="get"', $src );
		$this->assertStringContainsString( "home_url( '/search/' )", $src );
		$header = (string) file_get_contents( dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho/template-parts/site-header.php' );
		$this->assertStringContainsString( 'role="search" method="get"', $header );
	}

	public function test_REQ_254_the_search_route_is_registered_and_not_indexed(): void {
		$forms = (string) file_get_contents( dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho/inc/forms.php' );
		$this->assertStringContainsString( "'search'", $forms );
		$this->assertMatchesRegularExpression( "/array\( 'done', 'claim', 'search' \), true/", $forms );
	}
}
