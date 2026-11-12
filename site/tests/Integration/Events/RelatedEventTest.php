<?php
/**
 * The related event field on stories (REQ-265). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Events;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Content\PublicStory;
use WhoIsWho\Core\Content\Stories\RelatedEvent;
use WhoIsWho\Core\Content\Stories\StoryFields;
use WhoIsWho\Core\Content\Stories\StoryTypes;
use WhoIsWho\Tests\TestCase;

final class RelatedEventTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Users to remove.
	 *
	 * @var int[]
	 */
	private array $users = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		StoryTypes::register();
		StoryFields::register_meta();
		EventPostType::register();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
		$this->posts = array();
		$this->users = array();
		$_POST       = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Create an event.
	 *
	 * @param string $status Post status.
	 */
	private function event( string $status ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'   => EventPostType::TYPE,
				'post_title'  => 'Summit ' . wp_generate_password( 4, false ),
				'post_status' => $status,
			)
		);
		$this->posts[] = $id;
		return $id;
	}

	/**
	 * Create a story.
	 */
	private function story(): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'A story',
				'post_status' => 'publish',
			)
		);
		$this->posts[] = $id;
		return $id;
	}

	/**
	 * Save the picker as an administrator would.
	 *
	 * @param int $story Story.
	 * @param int $event Event id as submitted.
	 */
	private function submit( int $story, int $event ): void {
		$user          = (int) wp_insert_user(
			array(
				'user_login' => 'admin' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => 'administrator',
			)
		);
		$this->users[] = $user;
		wp_set_current_user( $user );
		$_POST = array(
			RelatedEvent::NONCE_FIELD => wp_create_nonce( RelatedEvent::NONCE_ACTION ),
			RelatedEvent::FIELD       => (string) $event,
		);
		RelatedEvent::save( $story );
		wp_set_current_user( 0 );
	}

	public function test_REQ_265_only_published_events_selectable(): void {
		$live  = $this->event( 'publish' );
		$draft = $this->event( 'draft' );
		$this->assertArrayHasKey( $live, RelatedEvent::choices() );
		$this->assertArrayNotHasKey( $draft, RelatedEvent::choices() );
		$story = $this->story();
		$this->submit( $story, $draft );
		$this->assertSame( '', (string) get_post_meta( $story, RelatedEvent::META, true ), 'A draft event cannot be saved as the link.' );
		$this->submit( $story, $story );
		$this->assertSame( '', (string) get_post_meta( $story, RelatedEvent::META, true ), 'A story is not an event.' );
		$this->submit( $story, $live );
		$this->assertSame( $live, (int) get_post_meta( $story, RelatedEvent::META, true ) );
	}

	public function test_REQ_265_story_shows_related_event_only_while_published(): void {
		$event = $this->event( 'publish' );
		$story = $this->story();
		$this->submit( $story, $event );
		$this->assertSame( $event, PublicStory::find( $story )['event']['id'] );
		wp_update_post(
			array(
				'ID'          => $event,
				'post_status' => 'draft',
			)
		);
		$this->assertNull( PublicStory::find( $story )['event'], 'The card is hidden while the event is not published.' );
		$this->assertSame( $event, (int) get_post_meta( $story, RelatedEvent::META, true ), 'The link is kept.' );
		wp_update_post(
			array(
				'ID'          => $event,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( $event, PublicStory::find( $story )['event']['id'] );
	}

	public function test_REQ_265_unlinking_removes_related_event(): void {
		$event = $this->event( 'publish' );
		$story = $this->story();
		$this->submit( $story, $event );
		$this->assertNotNull( PublicStory::find( $story )['event'] );
		$this->submit( $story, 0 );
		$this->assertNull( PublicStory::find( $story )['event'] );
		$this->assertSame( '', (string) get_post_meta( $story, RelatedEvent::META, true ) );
	}

	public function test_REQ_265_a_request_without_the_nonce_changes_nothing(): void {
		$event = $this->event( 'publish' );
		$story = $this->story();
		$_POST = array( RelatedEvent::FIELD => (string) $event );
		RelatedEvent::save( $story );
		$this->assertSame( '', (string) get_post_meta( $story, RelatedEvent::META, true ) );
	}
}
