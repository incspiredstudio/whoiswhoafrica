<?php
/**
 * Story share preview and Article structured data (REQ-182, REQ-026, REQ-044). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Stories;

use WhoIsWho\Core\Content\PublicStory;
use WhoIsWho\Core\Content\Stories\StorySeo;
use WhoIsWho\Tests\WpTestCase;

final class StorySeoTest extends WpTestCase {

	/**
	 * A story.
	 *
	 * @param string $status Status.
	 * @return int Id.
	 */
	private function story( string $status = 'publish' ): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => $status,
				'post_title'   => 'A Founder Speaks',
				'post_excerpt' => 'How the company began.',
				'post_date'    => 'future' === $status ? '2035-01-01 10:00:00' : current_time( 'mysql' ),
				'post_author'  => self::factory()->user->create( array( 'display_name' => 'Ada Writer' ) ),
			)
		);
	}

	public function test_REQ_182_sponsored_story_shows_label_on_card_and_share_preview(): void {
		$story                   = (array) PublicStory::find( $this->story() );
		$story['share']['label'] = 'Sponsored by Acme';

		$tags = StorySeo::tags( $story );

		$this->assertSame( 'A Founder Speaks', $tags['og']['og:title'] );
		$this->assertStringContainsString( 'Sponsored by Acme', $tags['og']['og:description'] );
		$this->assertStringContainsString( 'How the company began.', $tags['og']['og:description'] );
		$this->assertStringContainsString( 'Sponsored by Acme', $tags['description'] );
		$html = StorySeo::render_tags( $tags );
		$this->assertStringContainsString( 'property="og:title"', $html );
		$this->assertStringContainsString( 'name="description"', $html );
	}

	public function test_REQ_182_unsponsored_story_has_plain_description(): void {
		$story = (array) PublicStory::find( $this->story() );

		$tags = StorySeo::tags( $story );

		$this->assertSame( 'How the company began.', $tags['description'] );
		$this->assertSame( 'article', $tags['og']['og:type'] );
	}

	public function test_REQ_026_article_data_matches_headline_date_author_and_image(): void {
		$story          = (array) PublicStory::find( $this->story() );
		$story['cover'] = 'https://example.org/cover.jpg';

		$data = StorySeo::article( $story );

		$this->assertSame( 'Article', $data['@type'] );
		$this->assertSame( $story['title'], $data['headline'] );
		$this->assertSame( $story['date'], $data['datePublished'] );
		$this->assertSame( 'Ada Writer', $data['author']['name'] );
		$this->assertSame( 'https://example.org/cover.jpg', $data['image'] );
		$this->assertStringContainsString( 'application/ld+json', StorySeo::render_article( $story ) );
	}

	public function test_REQ_044_unpublished_story_emits_no_markup(): void {
		foreach ( array( 'draft', 'future', 'pending', 'private', 'trash' ) as $status ) {
			$id = $this->story( $status );
			$this->assertNull( PublicStory::find( $id ), $status );
			$this->assertSame( '', StorySeo::markup_for( $id ), $status );
		}
	}

	public function test_REQ_044_published_story_emits_tags_and_article(): void {
		$html = StorySeo::markup_for( $this->story() );

		$this->assertStringContainsString( 'og:title', $html );
		$this->assertStringContainsString( '"@type":"Article"', $html );
	}
}
