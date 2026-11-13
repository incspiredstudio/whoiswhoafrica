<?php
/**
 * Event page template (REQ-262, REQ-264, REQ-265, D-005). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Tests\TestCase;

final class EventPageTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		wp_set_current_user( 0 );
		switch_theme( 'whoiswho-child' );
		EventPostType::register();
		foreach ( get_posts(
			array(
				'post_type'      => array( EventPostType::TYPE, 'post' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		) as $leftover ) {
			wp_delete_post( (int) $leftover, true );
		}
	}

	/**
	 * Create an event.
	 *
	 * @param string $title  Title.
	 * @param int    $days   Start offset in days.
	 * @param string $status Post status.
	 */
	private function event( string $title, int $days, string $status = 'publish' ): int {
		$id = (int) wp_insert_post(
			array(
				'post_type'    => EventPostType::TYPE,
				'post_title'   => $title,
				'post_status'  => $status,
				'post_content' => '<p>Programme text.</p>',
				'post_excerpt' => 'Short summary.',
			)
		);
		update_post_meta( $id, 'wiw_event_start', gmdate( 'Y-m-d H:i', time() + $days * DAY_IN_SECONDS ) );
		update_post_meta( $id, 'wiw_event_city', 'Abuja' );
		update_post_meta( $id, 'wiw_event_organiser', 'Summit Org' );
		update_post_meta( $id, 'wiw_event_registration_url', 'https://organiser.example/register' );
		return $id;
	}

	/**
	 * Render the single template for an event.
	 *
	 * @param int $id Event id.
	 */
	private function render( int $id ): string {
		global $wp_query, $post;
		$post     = get_post( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$wp_query = new \WP_Query( // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			array(
				'p'           => $id,
				'post_type'   => EventPostType::TYPE,
				'post_status' => 'any',
			)
		); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$wp_query->get_posts();
		// get_header() and get_footer() use require_once, so a template rendered by an earlier test in the same run leaves
		// nothing for this one: load the file ourselves when it was already included.
		$reload    = static function ( string $file ): void {
			if ( in_array( $file, array_map( 'realpath', get_included_files() ), true ) ) {
				include $file;
			}
		};
		$on_header = static function () use ( $reload ): void {
			$reload( (string) realpath( get_template_directory() . '/header.php' ) );
		};
		$on_footer = static function () use ( $reload ): void {
			$reload( (string) realpath( get_template_directory() . '/footer.php' ) );
		};
		add_action( 'get_header', $on_header );
		add_action( 'get_footer', $on_footer );
		ob_start();
		include get_template_directory() . '/single-wiw_event.php';
		$out = (string) ob_get_clean();
		remove_action( 'get_header', $on_header );
		remove_action( 'get_footer', $on_footer );
		return $out;
	}

	public function test_REQ_264_upcoming_event_shows_poster_details_body_and_registration_in_a_new_tab(): void {
		$id  = $this->event( 'Leaders Summit', 10 );
		$out = $this->render( $id );
		$this->assertSame( 1, substr_count( $out, '<h1>' ) );
		$this->assertStringContainsString( 'ev-poster', $out );
		$this->assertStringNotContainsString( 'ev-poster is-past', $out );
		$this->assertStringContainsString( 'Event details', $out );
		$this->assertStringContainsString( 'Programme text.', $out );
		$this->assertStringContainsString( 'Short summary.', $out );
		$this->assertStringContainsString( 'target="_blank" rel="noopener noreferrer"', $out );
		$main = (string) substr( $out, (int) strpos( $out, '<main' ), (int) strpos( $out, '</main>' ) - (int) strpos( $out, '<main' ) );
		$this->assertNotSame( '', $main );
		foreach ( array( 'comment', 'reading time', 'author' ) as $word ) {
			$this->assertStringNotContainsStringIgnoringCase( $word, $main );
		}
	}

	public function test_REQ_264_past_event_stays_published_says_it_ended_and_has_no_registration_button(): void {
		$id  = $this->event( 'Old Forum', -10 );
		$out = $this->render( $id );
		$this->assertStringContainsString( 'ev-poster is-past', $out );
		$this->assertStringContainsString( 'Past event', $out );
		$this->assertStringContainsString( 'This event has ended', $out );
		$this->assertStringNotContainsString( 'ev-reg', $out );
		$this->assertSame( 'publish', get_post_status( $id ) );
	}

	public function test_REQ_265_related_stories_list_shows_published_stories_only(): void {
		$id    = $this->event( 'Linked Event', 12 );
		$story = (int) wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Published Linked Story',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $story, 'related_event_id', $id );
		$draft = (int) wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Draft Linked Story',
				'post_status' => 'draft',
			)
		);
		update_post_meta( $draft, 'related_event_id', $id );
		$out = $this->render( $id );
		$this->assertStringContainsString( 'Related stories', $out );
		$this->assertStringContainsString( 'Published Linked Story', $out );
		$this->assertStringNotContainsString( 'Draft Linked Story', $out );
	}

	public function test_REQ_264_more_events_lists_other_upcoming_events_and_a_draft_answers_404_without_content(): void {
		$id = $this->event( 'Main Event', 5 );
		$this->event( 'Another Event', 8 );
		$out = $this->render( $id );
		$this->assertStringContainsString( 'More events', $out );
		$this->assertStringContainsString( 'Another Event', $out );
		$draft = $this->event( 'Secret Draft', 7, 'draft' );
		$page  = $this->render( $draft );
		$this->assertStringNotContainsString( 'Programme text.', $page );
		$this->assertStringNotContainsString( 'ev-poster', $page );
	}
}
