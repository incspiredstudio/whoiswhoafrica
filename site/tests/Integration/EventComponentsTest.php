<?php
/**
 * Event components: agenda row, poster, details card, related (REQ-264, REQ-265, D-005). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class EventComponentsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
	}

	/**
	 * A PublicEvent-shaped array.
	 *
	 * @param array<string, mixed> $over Overrides.
	 * @return array<string, mixed>
	 */
	private function event( array $over = array() ): array {
		return array_merge(
			array(
				'id'               => 1,
				'slug'             => 'summit',
				'url'              => 'https://example.test/events/summit/',
				'title'            => 'Leaders Summit <b>',
				'summary'          => 'A summary.',
				'start'            => '2027-03-12 09:00',
				'end'              => '2027-03-12 17:00',
				'month'            => '2027-03',
				'upcoming'         => true,
				'past'             => false,
				'online'           => false,
				'venue'            => 'Convention Centre',
				'city'             => 'Abuja',
				'country'          => 'Nigeria',
				'organiser'        => 'Summit Org',
				'price_note'       => 'Free',
				'registration_url' => 'https://organiser.example/register',
				'cover'            => '',
				'industries'       => array(),
			),
			$over
		);
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

	public function test_REQ_264_agenda_row_shows_date_block_title_place_organiser_and_status_and_escapes_the_title(): void {
		$out = $this->render( 'template-parts/event-row', array( 'event' => $this->event() ) );
		$this->assertStringContainsString( '<b>12</b>Mar', $out );
		$this->assertStringContainsString( 'Leaders Summit &lt;b&gt;', $out );
		$this->assertStringContainsString( 'Convention Centre, Abuja, Nigeria · Summit Org', $out );
		$this->assertStringContainsString( 'ag-tag--upcoming', $out );
		$this->assertStringNotContainsString( 'is-past', $out );
		$this->assertStringNotContainsString( 'author', strtolower( $out ) );
	}

	public function test_REQ_264_past_row_is_muted_and_compact_row_has_no_image(): void {
		$past = $this->render(
			'template-parts/event-row',
			array(
				'event' => $this->event(
					array(
						'upcoming' => false,
						'cover'    => 'https://example.test/a.jpg',
					)
				),
			)
		);
		$this->assertStringContainsString( 'is-past', $past );
		$this->assertStringContainsString( 'ag-tag--past', $past );
		$this->assertStringContainsString( 'ag-thumb', $past );
		$compact = $this->render(
			'template-parts/event-row',
			array(
				'event'   => $this->event( array( 'cover' => 'https://example.test/a.jpg' ) ),
				'compact' => true,
			)
		);
		$this->assertStringContainsString( 'ag-row--compact', $compact );
		$this->assertStringNotContainsString( 'ag-thumb', $compact );
	}

	public function test_REQ_264_online_event_is_tagged_online(): void {
		$out = $this->render( 'template-parts/event-row', array( 'event' => $this->event( array( 'online' => true ) ) ) );
		$this->assertStringContainsString( 'ag-tag--online', $out );
		$this->assertStringContainsString( '>Online<', $out );
	}

	public function test_REQ_264_upcoming_details_link_opens_the_organisers_site_in_a_new_tab(): void {
		$out = $this->render( 'template-parts/event-details', array( 'event' => $this->event() ) );
		$this->assertStringContainsString( 'href="https://organiser.example/register" target="_blank" rel="noopener noreferrer"', $out );
		$this->assertStringContainsString( 'Event details', $out );
		$this->assertStringContainsString( 'takes no payment', $out );
		foreach ( array( 'When', 'Where', 'Organiser', 'Cost' ) as $label ) {
			$this->assertStringContainsString( '<dt>' . $label . '</dt>', $out );
		}
	}

	public function test_REQ_264_past_event_says_it_ended_and_has_no_registration_button(): void {
		$past = $this->event(
			array(
				'upcoming'         => false,
				'registration_url' => null,
			)
		);
		$out  = $this->render( 'template-parts/event-details', array( 'event' => $past ) );
		$this->assertStringContainsString( 'This event has ended', $out );
		$this->assertStringNotContainsString( 'ev-reg', $out );
		$this->assertStringNotContainsString( 'target="_blank"', $out );
		$poster = $this->render( 'template-parts/event-poster', array( 'event' => $past ) );
		$this->assertStringContainsString( 'Past event', $poster );
		$this->assertStringContainsString( 'ev-poster is-past', $poster );
	}

	public function test_REQ_264_poster_has_one_h1_and_the_date_at_poster_size_and_no_comments_or_author(): void {
		$out = $this->render( 'template-parts/event-poster', array( 'event' => $this->event() ) );
		$this->assertSame( 1, substr_count( $out, '<h1>' ) );
		$this->assertStringContainsString( '<b aria-hidden="true">12</b>', $out );
		$this->assertStringContainsString( 'March 2027', $out );
		foreach ( array( 'comment', 'author', 'reading time', 'min read' ) as $word ) {
			$this->assertStringNotContainsStringIgnoringCase( $word, $out );
		}
	}

	public function test_REQ_265_related_card_and_related_stories_list(): void {
		$out = $this->render(
			'template-parts/event-related',
			array(
				'event'   => $this->event(),
				'stories' => array(
					array(
						'title' => 'A story',
						'url'   => 'https://example.test/stories/a/',
						'type'  => 'Profile',
					),
				),
			)
		);
		$this->assertStringContainsString( 'Related event', $out );
		$this->assertStringContainsString( 'ev-rel-card', $out );
		$this->assertStringContainsString( 'Related stories', $out );
		$this->assertSame( '', trim( $this->render( 'template-parts/event-related', array() ) ) );
	}

	public function test_REQ_264_helpers_format_dates_places_and_group_by_month(): void {
		$this->assertSame( '12 March 2027, 09:00 to 17:00', wiw_event_when( $this->event() ) );
		$this->assertSame(
			'12 March 2027 to 14 March 2027',
			wiw_event_when(
				$this->event(
					array(
						'start' => '2027-03-12',
						'end'   => '2027-03-14',
					)
				)
			)
		);
		$this->assertSame( 'Online', wiw_event_where( $this->event( array( 'online' => true ) ) ) );
		$groups = wiw_event_months( array( $this->event(), $this->event( array( 'month' => '2027-04' ) ), $this->event() ) );
		$this->assertSame( array( '2027-03', '2027-04' ), array_keys( $groups ) );
		$this->assertCount( 2, $groups['2027-03'] );
	}
}
