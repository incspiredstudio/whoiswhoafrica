<?php
/**
 * The sample:seed and sample:remove commands (REQ-208, REQ-052): fictional, marked, built through the domain services, idempotent,
 * removable, and refused in production. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\PublicNominee;
use WhoIsWho\Core\Content\PublicStory;
use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Cli\Application;
use WhoIsWho\Core\Platform\Cli\Output;
use WhoIsWho\Core\Platform\Cli\Sample\SampleMarker;
use WhoIsWho\Core\Platform\Cli\Sample\SampleSeeder;
use WhoIsWho\Core\Platform\Cli\SampleRemoveCommand;
use WhoIsWho\Core\Platform\Cli\SampleSeedCommand;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\WpTestCase;

final class SampleSeedCommandTest extends WpTestCase {

	/**
	 * Administrator the commands act as.
	 *
	 * @var int
	 */
	private int $admin = 0;

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
	}

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
		// Removes anything an earlier test left, so counts start from nothing.
		( new SampleRemoveCommand( 'local' ) )->run( array(), new Output( false ) );
		global $wpdb;
		foreach ( array( AwardsTables::CATEGORIES, AwardsTables::NOMINATIONS, AwardsTables::CHANGES ) as $name ) {
			$wpdb->query( 'DELETE FROM ' . AwardsTables::table( $name ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- test clean-up.
		}
		$this->admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	protected function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * A small dataset that touches every kind of thing.
	 *
	 * @return array<string, mixed>
	 */
	private function small(): array {
		$person = array(
			'name'                 => 'Test Person',
			'current_title'        => 'Director',
			'current_organisation' => 'Test Org',
			'bio'                  => 'Fictional.',
		);
		$org    = array(
			'name'     => 'Test Org',
			'org_type' => 'Company',
			'summary'  => 'Fictional.',
			'hq_city'  => 'Lagos',
		);
		$row    = static fn ( string $key, string $type, string $state, array $fields ): array => array(
			'key'      => $key,
			'type'     => $type,
			'slug'     => $key,
			'name'     => $fields['name'],
			'industry' => 'Technology',
			'country'  => 'Nigeria',
			'city'     => 'Lagos',
			'state'    => $state,
			'fields'   => $fields,
		);
		return array(
			'places'       => array( 'Nigeria' => array( 'Lagos' ) ),
			'industries'   => array( 'Technology' ),
			'org_types'    => array( 'Company' ),
			'profiles'     => array(
				$row( 'test-person', 'individual', 'reviewed', $person ),
				$row( 'test-org', 'organisation', 'reviewed', $org ),
				$row( 'test-pending', 'organisation', 'pending', array_merge( $org, array( 'name' => 'Test Pending' ) ) ),
				$row( 'test-unpublished', 'organisation', 'unpublished', array_merge( $org, array( 'name' => 'Test Unpublished' ) ) ),
				$row( 'test-removed', 'organisation', 'removed', array_merge( $org, array( 'name' => 'Test Removed' ) ) ),
			),
			'stories'      => array(
				array(
					'slug'      => 'test-story',
					'title'     => 'Test story',
					'type'      => 'feature',
					'industry'  => 'Technology',
					'days'      => -3,
					'scheduled' => false,
					'excerpt'   => 'Fictional.',
					'paras'     => array( 'One.', 'Two.' ),
					'quote'     => 'A line.',
					'profiles'  => array( 'test-person' ),
					'event'     => 'test-event',
					'featured'  => true,
					'sponsor'   => array(
						'name'      => 'Test Sponsor',
						'placement' => 'both',
						'start'     => -2,
						'end'       => 20,
					),
				),
				array(
					'slug'      => 'test-later',
					'title'     => 'Test later',
					'type'      => 'news',
					'industry'  => 'Technology',
					'days'      => 9,
					'scheduled' => true,
					'excerpt'   => 'Fictional.',
					'paras'     => array( 'One.' ),
					'quote'     => '',
					'profiles'  => array(),
					'event'     => '',
					'featured'  => false,
					'sponsor'   => null,
				),
			),
			'events'       => array(
				array(
					'slug'      => 'test-event',
					'title'     => 'Test event',
					'industry'  => 'Technology',
					'summary'   => 'Fictional.',
					'paras'     => array( 'One.' ),
					'days'      => 12,
					'time'      => '09:00',
					'span'      => 1,
					'venue'     => 'Test hall',
					'city'      => 'Lagos',
					'country'   => 'Nigeria',
					'organiser' => 'Test organiser',
					'url'       => 'https://events.example.net/test',
					'price'     => 'Free.',
					'online'    => false,
				),
				array(
					'slug'      => 'test-past-event',
					'title'     => 'Test past event',
					'industry'  => 'Technology',
					'summary'   => 'Fictional.',
					'paras'     => array( 'One.' ),
					'days'      => -40,
					'time'      => '09:00',
					'span'      => 0,
					'venue'     => 'Online',
					'city'      => '',
					'country'   => '',
					'organiser' => 'Test organiser',
					'url'       => '',
					'price'     => '',
					'online'    => true,
				),
			),
			'categories'   => array(
				array(
					'group' => 'technology-innovation',
					'title' => 'Test category',
				),
			),
			'nominations'  => array(
				array(
					'profile'  => 'test-person',
					'category' => 'Test category',
					'winner'   => true,
					'citation' => 'Fictional citation.',
				),
				array(
					'profile'  => 'test-org',
					'category' => 'Test category',
					'winner'   => false,
					'citation' => 'Fictional citation.',
				),
			),
			'pin_profiles' => array( 'test-person', 'test-org' ),
			'pin_stories'  => array( 'test-story' ),
		);
	}

	private function seed( ?array $data = null, string $env = 'local' ): Output {
		$out = new Output( false );
		$this->assertSame( 0, ( new SampleSeedCommand( $env, $data ?? $this->small() ) )->run( array(), $out ), implode( ' | ', $out->lines() ) );
		return $out;
	}

	public function test_REQ_208_seed_refuses_in_production_and_runs_locally_and_on_staging(): void {
		foreach ( array( 'production', '' ) as $env ) {
			$out = new Output( false );
			$this->assertSame( 1, ( new SampleSeedCommand( $env, $this->small() ) )->run( array(), $out ) );
			$this->assertStringContainsString( 'Refused', implode( ' ', $out->lines() ) );
			$this->assertSame( array(), SampleMarker::all_post_ids(), 'nothing is created when it refuses' );
			$this->assertSame( 1, ( new SampleRemoveCommand( $env ) )->run( array(), new Output( false ) ) );
		}
		$this->seed( null, 'staging' );
		$this->assertNotSame( array(), SampleMarker::all_post_ids() );
	}

	public function test_REQ_208_commands_are_registered_in_the_cli(): void {
		$names = Application::for_site()->names();
		$this->assertContains( 'sample:seed', $names );
		$this->assertContains( 'sample:remove', $names );
	}

	public function test_REQ_208_profiles_enter_published_reviewed_through_the_state_machine_and_exercise_every_state(): void {
		$this->seed();
		$states = new StateRepository();
		$by     = static fn ( string $key ): int => (int) SampleMarker::find_post( 'profile:' . $key, ProfileType::POST_TYPE );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, $states->pub_state( $by( 'test-person' ) ) );
		$this->assertSame( PubState::PUBLISHED_PENDING, $states->pub_state( $by( 'test-pending' ) ) );
		$this->assertSame( PubState::UNPUBLISHED, $states->pub_state( $by( 'test-unpublished' ) ) );
		$this->assertSame( PubState::REMOVED_SUPPRESSED, $states->pub_state( $by( 'test-removed' ) ) );
		$this->assertSame( 'publish', get_post_status( $by( 'test-person' ) ) );
		$this->assertSame( ProfileType::STATUS_HIDDEN, get_post_status( $by( 'test-removed' ) ), 'a removed profile is not public' );
		$this->assertNotNull( PublicProfile::find( 'test-person' ), 'a reviewed sample profile is on the public read model' );
		$this->assertNull( PublicProfile::find( 'test-removed' ), 'a removed one is not' );
		$this->assertNull( PublicProfile::find( 'test-unpublished' ) );
		$this->assertSame( 'Director', get_post_meta( $by( 'test-person' ), 'wiw_current_title', true ) );
	}

	public function test_REQ_208_every_post_and_term_it_makes_carries_the_sample_marker(): void {
		$this->seed();
		foreach ( SampleMarker::all_post_ids() as $id ) {
			$this->assertSame( '1', get_post_meta( $id, SampleMarker::META, true ) );
		}
		$this->assertGreaterThan( 5, count( SampleMarker::all_post_ids() ) );
		$types = array_unique( array_map( 'get_post_type', SampleMarker::all_post_ids() ) );
		foreach ( array( ProfileType::POST_TYPE, EventPostType::TYPE, 'post', 'page' ) as $type ) {
			$this->assertContains( $type, $types, 'the marker lookup sees ' . $type );
		}
	}

	public function test_REQ_208_stories_events_awards_pages_and_the_front_page_exist(): void {
		$this->seed();
		$story = SampleMarker::find_post( 'story:test-story', 'post' );
		$this->assertNotNull( $story );
		$this->assertSame( 'publish', get_post_status( $story ) );
		$this->assertTrue( is_sticky( $story ), 'the story is Featured' );
		$this->assertSame( 'Test Sponsor', get_post_meta( $story, 'wiw_sponsor_name', true ) );
		$this->assertSame( 'future', get_post_status( SampleMarker::find_post( 'story:test-later', 'post' ) ) );
		$this->assertNotNull( PublicStory::find( 'test-story' ), 'the story is on the public read model' );
		$event = SampleMarker::find_post( 'event:test-event', EventPostType::TYPE );
		$this->assertSame( 'publish', get_post_status( $event ) );
		$this->assertGreaterThan( current_time( 'Y-m-d H:i' ), (string) get_post_meta( $event, 'wiw_event_start', true ) );
		$this->assertLessThan( current_time( 'Y-m-d H:i' ), (string) get_post_meta( (int) SampleMarker::find_post( 'event:test-past-event', EventPostType::TYPE ), 'wiw_event_start', true ) );
		$this->assertSame( (string) $event, (string) get_post_meta( $story, 'related_event_id', true ) );

		$winners = PublicNominee::winners();
		$this->assertCount( 1, $winners );
		$this->assertCount( 2, PublicNominee::live() );

		foreach ( array( 'home', 'about', 'faq', 'awards', 'nominate', 'privacy', 'terms', 'cookies', 'data-rights', 'complaints' ) as $slug ) {
			$this->assertInstanceOf( \WP_Post::class, get_page_by_path( $slug ), $slug );
		}
		$this->assertInstanceOf( \WP_Post::class, get_page_by_path( 'awards/rules' ) );
		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame( (int) get_page_by_path( 'home' )->ID, (int) get_option( 'page_on_front' ) );
	}

	public function test_REQ_208_running_twice_creates_nothing_new(): void {
		$this->seed();
		$count = count( SampleMarker::all_post_ids() );
		$again = $this->seed();
		$this->assertSame( $count, count( SampleMarker::all_post_ids() ) );
		$this->assertStringContainsString( 'Nothing to create', implode( ' ', $again->lines() ) );
		global $wpdb;
		$this->assertSame( 1, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . AwardsTables::table( AwardsTables::CATEGORIES ) ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- test count.
		$this->assertSame( 2, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ) ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- test count.
	}

	public function test_REQ_208_remove_deletes_the_sample_content_and_restores_the_settings_and_is_idempotent(): void {
		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_on_front' );
		$keep = self::factory()->post->create( array( 'post_title' => 'A real post' ) );
		$this->seed();
		$this->assertNotSame( array(), SampleMarker::all_post_ids() );

		$out = new Output( false );
		$this->assertSame( 0, ( new SampleRemoveCommand( 'local' ) )->run( array(), $out ) );
		$this->assertSame( array(), SampleMarker::all_post_ids() );
		$this->assertSame( array(), SampleMarker::all_terms( \WhoIsWho\Core\Directory\Taxonomies::all() ) );
		$this->assertNull( ( new StateRepository() )->get( 1 ), 'no state row is left for a removed profile' );
		$this->assertInstanceOf( \WP_Post::class, get_post( $keep ), 'content that is not marked is untouched' );
		$this->assertSame( 'posts', get_option( 'show_on_front' ) );
		$this->assertSame( 0, (int) get_option( 'page_on_front', 0 ) );
		global $wpdb;
		$this->assertSame( 0, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ) ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- test count.
		$this->assertSame( 0, ( new SampleRemoveCommand( 'local' ) )->run( array(), new Output( false ) ), 'a second run is harmless' );
	}

	public function test_REQ_208_seeded_profiles_stories_and_events_carry_generated_placeholder_images_that_are_marked_and_removed(): void {
		$this->seed();
		$person = SampleMarker::find_post( 'profile:test-person', ProfileType::POST_TYPE );
		$this->assertNotNull( $person );
		$image = (int) get_post_meta( (int) $person, PublicProfile::IMAGE_META, true );
		$this->assertGreaterThan( 0, $image, 'a sample profile has a portrait' );
		$this->assertSame( 'attachment', get_post_type( $image ) );
		$this->assertSame( 'image/png', get_post_mime_type( $image ) );
		$this->assertSame( '1', (string) get_post_meta( $image, SampleMarker::META, true ), 'the image carries the sample marker' );
		$story = SampleMarker::find_post( 'story:test-story', 'post' );
		$this->assertGreaterThan( 0, (int) get_post_thumbnail_id( (int) $story ), 'a sample story has a cover' );
		$event = SampleMarker::find_post( 'event:test-event', EventPostType::TYPE );
		$this->assertGreaterThan( 0, (int) get_post_thumbnail_id( (int) $event ), 'a sample event has a poster' );

		$count = count( SampleMarker::all_post_ids() );
		$this->seed();
		$this->assertSame( $count, count( SampleMarker::all_post_ids() ), 'running again makes no second image' );

		( new SampleRemoveCommand( 'local' ) )->run( array(), new Output( false ) );
		$this->assertNull( get_post( $image ), 'the image is deleted with the sample content' );
	}

	public function test_REQ_208_the_shipped_dataset_builds_about_forty_profiles_twelve_stories_and_six_events(): void {
		$data = SampleSeeder::load_data();
		$this->assertGreaterThanOrEqual( 40, count( $data['profiles'] ) );
		$this->assertCount( 12, $data['stories'] );
		$this->assertCount( 6, $data['events'] );
		$this->assertStringNotContainsString( 'WHOISWHO', (string) wp_json_encode( $data ), 'no brand name outside the child theme' );
		$this->seed( $data );
		$this->assertGreaterThanOrEqual( 34, count( PublicProfile::public_choices() ), 'the reviewed profiles are public' );
		$sponsored = 0;
		foreach ( SampleMarker::all_post_ids() as $id ) {
			if ( '' !== (string) get_post_meta( $id, 'wiw_sponsor_name', true ) ) {
				++$sponsored;
			}
		}
		$this->assertGreaterThanOrEqual( 2, $sponsored );
		$this->assertCount( 5, ( new \WhoIsWho\Core\Awards\CategoryService( \WhoIsWho\Core\Platform\Audit\AuditLogger::for_site() ) )->in_group( 'leadership-public-impact', true ) );
	}

	public function test_REQ_208_a_missing_legal_page_is_made_from_the_draft_wording_and_marked_draft_legal(): void {
		wp_set_current_user( $this->admin );
		$existing = get_page_by_path( 'terms' );
		if ( $existing instanceof \WP_Post ) {
			wp_delete_post( $existing->ID, true );
		}
		$this->seed();
		$terms = get_page_by_path( 'terms' );
		$this->assertInstanceOf( \WP_Post::class, $terms );
		$this->assertSame( '1', get_post_meta( $terms->ID, '_wiw_draft_legal', true ) );
		$this->assertSame( '1', get_post_meta( $terms->ID, SampleMarker::META, true ) );
		$this->assertStringContainsString( 'draft wording', $terms->post_content );
	}
}
