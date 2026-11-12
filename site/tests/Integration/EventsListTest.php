<?php
/**
 * Events list template (REQ-262, REQ-264, REQ-265, REQ-029). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Tests\TestCase;

final class EventsListTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		wp_set_current_user( 0 );
		switch_theme( 'whoiswho-child' );
		EventPostType::register();
		foreach ( get_posts(
			array(
				'post_type'      => EventPostType::TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		) as $leftover ) {
			wp_delete_post( (int) $leftover, true );
		}
		set_query_var( 'city', '' );
		set_query_var( 'industry', '' );
	}

	/**
	 * Create an event.
	 *
	 * @param string $title      Title.
	 * @param int    $start_days Days from now.
	 * @param string $city       City.
	 * @param string $status     Post status.
	 */
	private function event( string $title, int $start_days, string $city = 'Lagos', string $status = 'publish' ): int {
		$id = (int) wp_insert_post(
			array(
				'post_type'   => EventPostType::TYPE,
				'post_title'  => $title,
				'post_status' => $status,
			)
		);
		update_post_meta( $id, 'wiw_event_start', gmdate( 'Y-m-d H:i', time() + $start_days * DAY_IN_SECONDS ) );
		update_post_meta( $id, 'wiw_event_city', $city );
		update_post_meta( $id, 'wiw_event_organiser', 'Org ' . $title );
		return $id;
	}

	/**
	 * Render the archive template.
	 */
	private function render(): string {
		ob_start();
		include get_template_directory() . '/archive-wiw_event.php';
		return (string) ob_get_clean();
	}

	public function test_REQ_264_lists_upcoming_soonest_first_then_past_newest_first_and_hides_drafts(): void {
		$this->event( 'Later Summit', 40 );
		$this->event( 'Sooner Forum', 5 );
		$this->event( 'Old Meetup', -20 );
		$this->event( 'Older Meetup', -60 );
		$this->event( 'Draft Secret', 10, 'Lagos', 'draft' );
		$out = $this->render();
		$this->assertStringNotContainsString( 'Draft Secret', $out );
		$this->assertLessThan( strpos( $out, 'Later Summit' ), strpos( $out, 'Sooner Forum' ), 'soonest first' );
		$this->assertLessThan( strpos( $out, 'Older Meetup' ), strpos( $out, 'Old Meetup' ), 'past newest first' );
		$this->assertLessThan( strpos( $out, 'Old Meetup' ), strpos( $out, 'Later Summit' ), 'upcoming before past' );
		$this->assertStringContainsString( 'Past events', $out );
		$this->assertStringContainsString( 'ag-tag--past', $out );
		$this->assertSame( 1, substr_count( $out, '<h1>' ) );
		$this->assertStringContainsString( '4 events', $out );
	}

	public function test_REQ_264_next_up_is_the_soonest_upcoming_event_and_absent_when_none(): void {
		$this->event( 'Far Away', 60 );
		$this->event( 'Very Soon', 3 );
		$out = $this->render();
		$this->assertMatchesRegularExpression( '#class="ev-next"[^>]*>.*?Very Soon#s', $out );
		foreach ( get_posts(
			array(
				'post_type'      => EventPostType::TYPE,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		) as $id ) {
			wp_delete_post( (int) $id, true );
		}
		$this->event( 'Gone By', -10 );
		$none = $this->render();
		$this->assertStringNotContainsString( 'ev-next-k', $none );
		$this->assertStringContainsString( 'No upcoming events yet', $none );
		$this->assertStringContainsString( 'Gone By', $none, 'past events still show' );
	}

	public function test_REQ_264_city_filter_narrows_the_list_and_an_empty_result_offers_clear_filters(): void {
		$this->event( 'Lagos Forum', 5, 'Lagos' );
		$this->event( 'Abuja Forum', 6, 'Abuja' );
		set_query_var( 'city', 'Abuja' );
		$out = $this->render();
		$this->assertStringContainsString( 'Abuja Forum', $out );
		$this->assertStringNotContainsString( 'Lagos Forum', $out );
		$this->assertStringContainsString( 'fbar-clear', $out );
		$this->assertStringContainsString( '1 event<', $out );
		set_query_var( 'city', 'Accra' );
		$empty = $this->render();
		$this->assertStringContainsString( 'No upcoming events match', $empty );
		$this->assertStringContainsString( 'Clear filters', $empty );
	}

	public function test_REQ_029_filter_bar_has_city_and_industry_lists_and_no_events_filter_leaks_into_stories(): void {
		$this->event( 'Any Forum', 5 );
		$out = $this->render();
		$this->assertStringContainsString( 'aria-label="City"', $out );
		$this->assertStringContainsString( 'aria-label="Industry"', $out );
		$this->assertStringContainsString( 'aria-label="Filter events"', $out );
	}
}
