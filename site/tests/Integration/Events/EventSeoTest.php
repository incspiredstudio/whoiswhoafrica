<?php
/**
 * Event structured data, sitemap and search exclusion (REQ-262, REQ-264, REQ-208). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Events;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Content\Events\EventSeo;
use WhoIsWho\Core\Content\PublicEvent;
use WhoIsWho\Core\Directory\Sitemap\Sitemap;
use WhoIsWho\Tests\WpTestCase;

final class EventSeoTest extends WpTestCase {

	protected function tearDown(): void {
		( new Sitemap() )->purge();
		parent::tearDown();
	}

	/**
	 * An event.
	 *
	 * @param string $status Status.
	 * @param string $title  Title.
	 * @return int Id.
	 */
	private function event( string $status, string $title = 'Africa Summit' ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'    => EventPostType::TYPE,
				'post_status'  => $status,
				'post_title'   => $title,
				'post_excerpt' => 'Leaders meet.',
				'post_date'    => 'future' === $status ? '2035-01-01 10:00:00' : current_time( 'mysql' ),
			)
		);
		foreach ( array(
			'start'     => '2030-03-04 09:30',
			'end'       => '2030-03-05 17:00',
			'venue'     => 'Convention Centre',
			'city'      => 'Lagos',
			'country'   => 'Nigeria',
			'organiser' => 'Summit Org',
		) as $key => $value ) {
			update_post_meta( $id, 'wiw_event_' . $key, $value );
		}
		return $id;
	}

	public function test_REQ_262_published_event_emits_matching_structured_data(): void {
		$id    = $this->event( 'publish' );
		$event = PublicEvent::find( $id );
		$this->assertNotNull( $event );

		$html = EventSeo::render( $event );

		$this->assertStringContainsString( 'application/ld+json', $html );
		$data = json_decode( trim( strip_tags( $html ) ), true );
		$this->assertSame( 'Event', $data['@type'] );
		$this->assertSame( 'Africa Summit', $data['name'] );
		$this->assertSame( $event['title'], $data['name'] );
		$this->assertStringStartsWith( '2030-03-04T09:30:00', $data['startDate'] );
		$this->assertStringStartsWith( '2030-03-05T17:00:00', $data['endDate'] );
		$this->assertSame( 'Convention Centre', $data['location']['name'] );
		$this->assertSame( 'Lagos', $data['location']['address']['addressLocality'] );
		$this->assertSame( 'Summit Org', $data['organizer']['name'] );
		$this->assertSame( $event['url'], $data['url'] );
	}

	public function test_REQ_262_online_event_uses_a_virtual_location(): void {
		$id = $this->event( 'publish' );
		update_post_meta( $id, 'wiw_event_online', '1' );

		$data = EventSeo::for_event( (array) PublicEvent::find( $id ) );

		$this->assertSame( 'VirtualLocation', $data['location']['@type'] );
		$this->assertSame( 'https://schema.org/OnlineEventAttendanceMode', $data['eventAttendanceMode'] );
	}

	public function test_REQ_262_draft_event_has_no_structured_data(): void {
		foreach ( array( 'draft', 'future', 'private', 'pending', 'trash' ) as $status ) {
			$id = $this->event( $status, 'Hidden ' . $status );
			$this->assertNull( PublicEvent::find( $id ), $status );
		}
		$this->assertSame( '', EventSeo::render( array( 'title' => 'No start' ) ) );
	}

	public function test_REQ_264_published_event_is_in_sitemap_draft_is_not(): void {
		$public = $this->event( 'publish', 'Public Summit' );
		$draft  = $this->event( 'draft', 'Draft Summit' );
		$gone   = $this->event( 'trash', 'Trashed Summit' );
		( new Sitemap() )->purge();

		$sitemap = new Sitemap();
		$body    = $sitemap->respond( 'sitemap-events.xml' );
		$index   = $sitemap->respond( 'sitemap.xml' );

		$this->assertSame( 200, $body['status'] );
		$this->assertStringContainsString( esc_url( (string) get_permalink( $public ) ), $body['body'] );
		$this->assertStringNotContainsString( 'Draft', $body['body'] );
		$this->assertStringNotContainsString( 'draft-summit', $body['body'] );
		$this->assertStringNotContainsString( 'trashed-summit', $body['body'] );
		$this->assertStringContainsString( 'sitemap-events.xml', $index['body'] );
		unset( $draft, $gone );
	}

	public function test_REQ_264_publishing_an_event_refreshes_the_sitemap(): void {
		$id      = $this->event( 'draft', 'Soon Summit' );
		$sitemap = new Sitemap();
		$sitemap->register();
		$this->assertStringNotContainsString( 'soon-summit', $sitemap->respond( 'sitemap-events.xml' )['body'] );

		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);

		$this->assertStringContainsString( 'soon-summit', ( new Sitemap() )->respond( 'sitemap-events.xml' )['body'] );
	}

	public function test_REQ_262_events_are_not_in_search(): void {
		$this->event( 'publish', 'Searchable Summit' );

		$query = new \WP_Query(
			array(
				's'           => 'Searchable',
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 0, $query->found_posts );
		$this->assertTrue( (bool) get_post_type_object( EventPostType::TYPE )->exclude_from_search );
	}
}
