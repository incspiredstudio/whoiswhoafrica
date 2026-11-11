<?php
/**
 * The PublicEvent read model (REQ-262, REQ-264, REQ-265). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Events;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Content\PublicEvent;
use WhoIsWho\Core\Content\Stories\StoryFields;
use WhoIsWho\Core\Content\Stories\StoryTypes;
use WhoIsWho\Tests\TestCase;

final class PublicEventTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		wp_set_current_user( 0 );
		EventPostType::register();
		StoryTypes::register();
		StoryTypes::ensure_terms();
		StoryFields::register_meta();
		if ( ! taxonomy_exists( 'wiw_industry' ) ) {
			register_taxonomy( 'wiw_industry', array( 'post' ), array( 'public' => true ) );
		}
		register_taxonomy_for_object_type( 'wiw_industry', EventPostType::TYPE );
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
	}

	/**
	 * Create an event with offsets in days from now.
	 *
	 * @param int                  $start_days Start offset.
	 * @param int|null             $end_days   End offset or null.
	 * @param array<string, mixed> $args       Post overrides and extra meta under "meta".
	 */
	private function event( int $start_days, ?int $end_days = null, array $args = array() ): int {
		$meta = isset( $args['meta'] ) && is_array( $args['meta'] ) ? $args['meta'] : array();
		unset( $args['meta'] );
		$id = wp_insert_post(
			array_merge(
				array(
					'post_type'   => EventPostType::TYPE,
					'post_title'  => 'Event ' . wp_generate_password( 5, false ),
					'post_status' => 'publish',
				),
				$args
			)
		);
		$this->assertIsInt( $id );
		$fmt = 'Y-m-d H:i';
		update_post_meta( $id, 'wiw_event_start', gmdate( $fmt, time() + $start_days * DAY_IN_SECONDS ) );
		if ( null !== $end_days ) {
			update_post_meta( $id, 'wiw_event_end', gmdate( $fmt, time() + $end_days * DAY_IN_SECONDS ) );
		}
		foreach ( array_merge(
			array(
				'city'    => 'Lagos',
				'country' => 'Nigeria',
				'online'  => '0',
			),
			$meta
		) as $key => $value ) {
			update_post_meta( $id, 'wiw_event_' . $key, $value );
		}
		return $id;
	}

	public function test_REQ_262_event_is_past_after_end_date(): void {
		$id    = $this->event( -3, -1 );
		$event = PublicEvent::find( $id );
		$this->assertNotNull( $event );
		$this->assertTrue( $event['past'] );
		$this->assertFalse( $event['upcoming'] );
		$this->assertNull( $event['registration_url'], 'No registration for a past event.' );
		$this->assertSame( 'publish', get_post_status( $id ), 'A past event stays published.' );

		$running = PublicEvent::find( $this->event( -1, 2, array( 'meta' => array( 'registration_url' => 'https://example.com/r' ) ) ) );
		$this->assertTrue( $running['upcoming'], 'Still running until the end date.' );
		$this->assertSame( 'https://example.com/r', $running['registration_url'] );
	}

	public function test_REQ_262_event_without_end_uses_start_date(): void {
		$this->assertTrue( PublicEvent::find( $this->event( -2 ) )['past'] );
		$this->assertTrue( PublicEvent::find( $this->event( 2 ) )['upcoming'] );
	}

	public function test_REQ_262_upcoming_event_without_link_has_no_registration(): void {
		$this->assertNull( PublicEvent::find( $this->event( 5 ) )['registration_url'] );
	}

	public function test_REQ_262_draft_event_has_no_public_data(): void {
		$draft  = $this->event( 5, null, array( 'post_status' => 'draft' ) );
		$future = $this->event(
			5,
			null,
			array(
				'post_status'   => 'future',
				'post_date'     => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			)
		);
		$trash  = $this->event( 5 );
		wp_trash_post( $trash );
		foreach ( array( $draft, $future, $trash ) as $id ) {
			$this->assertNull( PublicEvent::find( $id ) );
		}
		$this->assertSame( 0, PublicEvent::list( array() )['total'] );
		$this->assertSame( array(), PublicEvent::next_upcoming( 3 ) );
	}

	public function test_REQ_264_events_list_orders_upcoming_first(): void {
		$past_old = $this->event( -30, -29 );
		$past_new = $this->event( -5, -4 );
		$soon     = $this->event( 3 );
		$later    = $this->event( 20 );
		$list     = PublicEvent::list( array() );
		$this->assertSame( array( $soon, $later, $past_new, $past_old ), wp_list_pluck( $list['items'], 'id' ) );
		$this->assertSame( 4, $list['total'] );
		$this->assertFalse( $list['error'] );
		$this->assertArrayHasKey( 'month', $list['items'][0] );
	}

	public function test_REQ_264_list_filters_by_city_and_industry(): void {
		wp_insert_term( 'Finance', 'wiw_industry', array( 'slug' => 'finance' ) );
		$lagos = $this->event( 3 );
		$accra = $this->event( 4, null, array( 'meta' => array( 'city' => 'Accra' ) ) );
		wp_set_object_terms( $accra, 'finance', 'wiw_industry' );
		$this->assertSame( array( $accra ), wp_list_pluck( PublicEvent::list( array( 'city' => 'Accra' ) )['items'], 'id' ) );
		$this->assertSame( array( $accra ), wp_list_pluck( PublicEvent::list( array( 'industry' => 'finance' ) )['items'], 'id' ) );
		$this->assertSame( array( $lagos ), wp_list_pluck( PublicEvent::list( array( 'city' => 'Lagos' ) )['items'], 'id' ) );
		$this->assertSame( 0, PublicEvent::list( array( 'city' => 'Nowhere' ) )['total'] );
		$this->assertSame( array( 'Accra', 'Lagos' ), PublicEvent::cities() );
	}

	public function test_REQ_264_home_shows_next_three_upcoming_events(): void {
		$this->event( -5, -4 );
		$a = $this->event( 1 );
		$b = $this->event( 2 );
		$c = $this->event( 3 );
		$this->event( 4 );
		$this->assertSame( array( $a, $b, $c ), wp_list_pluck( PublicEvent::next_upcoming( 3 ), 'id' ) );
	}

	public function test_REQ_265_event_lists_only_published_linked_stories(): void {
		$event     = $this->event( 5 );
		$make      = function ( string $status ) use ( $event ): int {
			$id = wp_insert_post(
				array(
					'post_title'  => 'Linked ' . $status,
					'post_status' => $status,
					'post_type'   => 'post',
				)
			);
			wp_set_object_terms( $id, 'news', StoryTypes::TAXONOMY );
			update_post_meta( $id, 'related_event_id', $event );
			return (int) $id;
		};
		$published = $make( 'publish' );
		$make( 'draft' );
		$this->assertSame( array( $published ), wp_list_pluck( PublicEvent::find( $event )['stories'], 'id' ) );
	}

	public function test_REQ_262_events_are_not_in_search(): void {
		$this->assertTrue( get_post_type_object( EventPostType::TYPE )->exclude_from_search );
	}
}
