<?php
/**
 * Related stories on a profile (REQ-028). Needs the WordPress test suite and the real state tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Stories;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Content\Stories\LinkedProfiles;
use WhoIsWho\Core\Content\Stories\StoryFields;
use WhoIsWho\Core\Content\Stories\StoryTypes;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class RelatedStoriesTest extends TestCase {

	/**
	 * Profiles to remove.
	 *
	 * @var int[]
	 */
	private array $profiles = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		// Other tests remove every callback on this hook; put back the one the Directory module registers.
		remove_all_filters( LinkedProfiles::FILTER );
		add_filter( LinkedProfiles::FILTER, static fn ( $choices ): array => ( is_array( $choices ) ? $choices : array() ) + PublicProfile::public_choices() );
		StoryTypes::register();
		StoryTypes::ensure_terms();
		StoryFields::register_meta();
		EventPostType::register();
		foreach ( get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		) as $leftover ) {
			wp_delete_post( (int) $leftover, true );
		}
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->profiles as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->profiles = array();
		parent::tearDown();
	}

	/**
	 * A reviewed public profile.
	 */
	private function profile(): PublicProfile {
		$id               = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Related Person',
			)
		);
		$this->profiles[] = $id;
		$machine          = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'individual' ) );
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		$profile = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $profile );
		return $profile;
	}

	/**
	 * A story linking profiles.
	 *
	 * @param array<int, int> $linked Profile ids.
	 * @param string          $status Post status.
	 * @param string          $date   Post date.
	 */
	private function story( array $linked, string $status = 'publish', string $date = '2026-01-01 10:00:00' ): int {
		$id = wp_insert_post(
			array(
				'post_title'  => 'Story ' . wp_generate_password( 5, false ),
				'post_status' => $status,
				'post_type'   => 'post',
				'post_date'   => $date,
			)
		);
		$this->assertIsInt( $id );
		update_post_meta( $id, LinkedProfiles::META, $linked );
		return $id;
	}

	public function test_REQ_028_profile_lists_published_linked_stories(): void {
		$profile = $this->profile();
		$old     = $this->story( array( $profile->id ), 'publish', '2026-01-01 10:00:00' );
		$new     = $this->story( array( $profile->id ), 'publish', '2026-03-01 10:00:00' );
		$this->story( array( 999999 ) );
		$this->assertSame( array( $new, $old ), wp_list_pluck( $profile->related_stories(), 'id' ) );
		$this->assertSame( array( $new ), wp_list_pluck( $profile->related_stories( 1 ), 'id' ) );
		update_post_meta( $old, LinkedProfiles::META, array() );
		$this->assertSame( array( $new ), wp_list_pluck( $profile->related_stories(), 'id' ), 'Unlinking removes the story at once.' );
	}

	public function test_REQ_028_draft_story_is_not_listed_on_profile(): void {
		$profile = $this->profile();
		$draft   = $this->story( array( $profile->id ), 'draft' );
		$live    = $this->story( array( $profile->id ) );
		$this->assertSame( array( $live ), wp_list_pluck( $profile->related_stories(), 'id' ) );
		wp_update_post(
			array(
				'ID'          => $live,
				'post_status' => 'draft',
			)
		);
		$this->assertSame( array(), $profile->related_stories() );
		$this->assertNotSame( $draft, $live );
	}
}
