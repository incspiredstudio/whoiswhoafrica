<?php
/**
 * The Sponsored label and slot on the stories read side (REQ-263, REQ-182, REQ-026, REQ-116). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Stories;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Content\PublicStory;
use WhoIsWho\Core\Content\Stories\StoryFields;
use WhoIsWho\Core\Content\Stories\StoryTypes;
use WhoIsWho\Tests\Support\FixtureSponsoredSource;
use WhoIsWho\Tests\TestCase;

final class SponsoredSlotTest extends TestCase {

	/**
	 * Fixture source.
	 *
	 * @var FixtureSponsoredSource
	 */
	private FixtureSponsoredSource $source;

	/**
	 * Today for the read side.
	 *
	 * @var string
	 */
	private string $today = '2026-12-10';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		wp_set_current_user( 0 );
		StoryTypes::register();
		StoryTypes::ensure_terms();
		StoryFields::register_meta();
		EventPostType::register();
		foreach ( get_posts(
			array(
				'post_type'      => array( 'post', EventPostType::TYPE ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		) as $leftover ) {
			wp_delete_post( (int) $leftover, true );
		}
		update_option( 'sticky_posts', array() );
		$this->source = new FixtureSponsoredSource();
		add_filter( 'wiw_sponsored_source', fn (): FixtureSponsoredSource => $this->source );
		add_filter( 'wiw_story_today', fn (): string => $this->today );
	}

	protected function tearDown(): void {
		remove_all_filters( 'wiw_sponsored_source' );
		remove_all_filters( 'wiw_story_today' );
		parent::tearDown();
	}

	/**
	 * Create a story.
	 *
	 * @param string $date Post date.
	 */
	private function story( string $date = '2026-01-01 10:00:00' ): int {
		$id = wp_insert_post(
			array(
				'post_title'   => 'Story ' . wp_generate_password( 5, false ),
				'post_content' => 'Body text.',
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_date'    => $date,
			)
		);
		$this->assertIsInt( $id );
		wp_set_object_terms( $id, 'news', StoryTypes::TAXONOMY );
		return $id;
	}

	/**
	 * Place a story.
	 *
	 * @param int    $id        Story.
	 * @param string $placement home, stories or both.
	 * @param string $start     Start date.
	 * @param string $end       End date.
	 */
	private function place( int $id, string $placement, string $start, string $end ): void {
		$this->source->placements[ $id ] = array(
			'sponsor'   => 'Acme',
			'placement' => $placement,
			'start'     => $start,
			'end'       => $end,
		);
	}

	public function test_REQ_263_slot_appears_only_between_dates(): void {
		$id = $this->story();
		$this->place( $id, 'home', '2026-12-01', '2026-12-31' );
		$this->today = '2026-11-30';
		$this->assertNull( PublicStory::list( array( 'surface' => 'home' ) )['slot'] );
		foreach ( array( '2026-12-01', '2026-12-15', '2026-12-31' ) as $day ) {
			$this->today = $day;
			$this->assertSame( $id, PublicStory::list( array( 'surface' => 'home' ) )['slot']['id'], $day );
		}
		$this->today = '2026-12-10';
		$this->assertNull( PublicStory::list( array( 'surface' => 'stories' ) )['slot'], 'A home placement does not fill the stories slot.' );
		$this->assertNull( PublicStory::list( array() )['slot'], 'No surface, no slot.' );
		$this->place( $id, 'both', '2026-12-01', '2026-12-31' );
		$this->assertSame( $id, PublicStory::list( array( 'surface' => 'stories' ) )['slot']['id'] );
	}

	public function test_REQ_263_placement_expires_and_removes_label(): void {
		$id = $this->story();
		$this->place( $id, 'both', '2026-12-01', '2026-12-31' );
		$this->today = '2026-12-31';
		$this->assertSame( 'Sponsored by Acme', PublicStory::find( $id )['sponsor']['label'] );
		$this->assertTrue( PublicStory::find( $id )['sponsor']['active'] );
		$this->today = '2027-01-01';
		$found       = PublicStory::find( $id );
		$this->assertNotNull( $found, 'The story stays.' );
		$this->assertNull( $found['sponsor'] );
		$this->assertNull( PublicStory::list( array( 'surface' => 'home' ) )['slot'] );
		$this->assertContains( $id, wp_list_pluck( PublicStory::list( array() )['items'], 'id' ) );
	}

	public function test_REQ_263_placement_does_not_reorder_unsponsored_stories(): void {
		$a      = $this->story( '2026-01-01 10:00:00' );
		$b      = $this->story( '2026-02-01 10:00:00' );
		$c      = $this->story( '2026-03-01 10:00:00' );
		$before = wp_list_pluck( PublicStory::list( array( 'surface' => 'home' ) )['items'], 'id' );
		$this->place( $a, 'home', '2026-12-01', '2026-12-31' );
		$list = PublicStory::list( array( 'surface' => 'home' ) );
		$this->assertSame( $a, $list['slot']['id'] );
		$this->assertSame( $before, wp_list_pluck( $list['items'], 'id' ) );
		$this->assertSame( array( $c, $b, $a ), $before );
	}

	public function test_REQ_263_an_event_cannot_be_the_slot(): void {
		$event = wp_insert_post(
			array(
				'post_title'  => 'Summit',
				'post_status' => 'publish',
				'post_type'   => EventPostType::TYPE,
			)
		);
		$this->assertIsInt( $event );
		$this->place( $event, 'home', '2026-12-01', '2026-12-31' );
		$this->assertNull( PublicStory::list( array( 'surface' => 'home' ) )['slot'] );
	}

	public function test_REQ_182_sponsored_story_shows_label_on_card_and_share_preview(): void {
		$id = $this->story();
		$this->place( $id, 'stories', '', '' );
		$card = PublicStory::list( array( 'surface' => 'stories' ) )['items'][0];
		$this->assertSame( 'Sponsored by Acme', $card['sponsor']['label'] );
		$this->assertSame( 'Sponsored by Acme', PublicStory::find( $id )['share']['label'] );
		unset( $this->source->placements[ $id ] );
		$this->assertNull( PublicStory::find( $id )['share']['label'] );
	}

	public function test_REQ_026_sources_only_read_sponsor_data(): void {
		$root = dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/src/Content';
		foreach ( array( 'SponsoredSource.php', 'PublicStory.php', 'Stories/MetaSponsoredSource.php', 'Stories/NullSponsoredSource.php' ) as $file ) {
			$code = (string) file_get_contents( $root . '/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$this->assertDoesNotMatchRegularExpression( '/\b(update|add|delete)_post_meta\s*\(/', $code, $file );
			$this->assertStringNotContainsString( 'Commerce\\', $code, $file );
		}
	}
}
