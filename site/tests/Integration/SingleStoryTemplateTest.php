<?php
/**
 * The single story template at /stories/:slug (REQ-026, REQ-027, REQ-028, REQ-182, REQ-265). Reads PublicStory only. Needs the
 * WordPress test suite and the profile tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Content\Stories\LinkedProfiles;
use WhoIsWho\Core\Content\Stories\StoryFields;
use WhoIsWho\Core\Content\Stories\StoryTypes;
use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\Support\FixtureSponsoredSource;
use WhoIsWho\Tests\TestCase;

final class SingleStoryTemplateTest extends TestCase {

	/**
	 * Profiles to remove.
	 *
	 * @var int[]
	 */
	private array $profiles = array();

	/**
	 * Fixture sponsor source.
	 *
	 * @var FixtureSponsoredSource
	 */
	private FixtureSponsoredSource $source;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__ ) . '/../wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		switch_theme( 'whoiswho-child' );
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
		remove_all_filters( LinkedProfiles::FILTER );
		$this->source = new FixtureSponsoredSource();
		add_filter( 'wiw_sponsored_source', fn (): FixtureSponsoredSource => $this->source );
		add_filter( 'wiw_story_today', fn (): string => '2026-12-10' );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->profiles as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->profiles = array();
		remove_all_filters( LinkedProfiles::FILTER );
		remove_all_filters( 'wiw_sponsored_source' );
		remove_all_filters( 'wiw_story_today' );
		parent::tearDown();
	}

	/**
	 * Create a story.
	 *
	 * @param string $status Post status.
	 */
	private function story( string $status = 'publish' ): int {
		$id = wp_insert_post(
			array(
				'post_title'   => 'Story ' . wp_generate_password( 5, false ),
				'post_content' => '<p>Body text.</p><blockquote>A pull quote.</blockquote>',
				'post_excerpt' => 'A short lede.',
				'post_status'  => $status,
				'post_type'    => 'post',
			)
		);
		$this->assertIsInt( $id );
		wp_set_object_terms( $id, 'feature', StoryTypes::TAXONOMY );
		return $id;
	}

	/**
	 * Create a reviewed (public) profile.
	 *
	 * @param string $name Name.
	 */
	private function profile( string $name ): int {
		$id               = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->profiles[] = $id;
		$machine          = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'individual' ) );
		( new ProfileFields() )->set( $id, 'bio', 'A biography.' );
		( new ProvenanceRepository() )->record(
			$id,
			array(
				'field'        => 'bio',
				'source_url'   => 'https://example.org/src',
				'retrieved_at' => '2026-11-10T09:00:00Z',
				'confidence'   => 'high',
				'method'       => 'import',
			)
		);
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		return $id;
	}

	/**
	 * Render the story template for a story.
	 *
	 * @param int $id Story.
	 */
	private function render( int $id ): string {
		global $wp_query;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- a test sets the main query.
		$wp_query = new \WP_Query(
			array(
				'p'           => $id,
				'post_type'   => 'post',
				'post_status' => 'any',
			)
		);
		ob_start();
		include get_template_directory() . '/single-post.php';
		return (string) ob_get_clean();
	}

	public function test_REQ_027_story_shows_type_title_lede_body_and_one_h1(): void {
		$out = $this->render( $this->story() );
		$this->assertSame( 1, substr_count( $out, '<h1' ) );
		$this->assertStringContainsString( 'Feature', $out );
		$this->assertStringContainsString( 'A short lede.', $out );
		$this->assertStringContainsString( 'Body text.', $out );
		$this->assertStringContainsString( 'A pull quote.', $out );
		$this->assertStringContainsString( 'class="crumbs"', $out );
	}

	public function test_REQ_027_story_linked_to_a_public_profile_shows_it_and_a_non_public_one_shows_nothing(): void {
		$public = $this->profile( 'Story Linked Person' );
		$id     = $this->story();
		add_filter(
			LinkedProfiles::FILTER,
			static fn (): array => array( $public => 'Story Linked Person' )
		);
		update_post_meta( $id, LinkedProfiles::META, array( $public, 999999 ) );
		$out = $this->render( $id );
		$this->assertStringContainsString( 'On the record', $out );
		$this->assertStringContainsString( 'Story Linked Person', $out );
		remove_all_filters( LinkedProfiles::FILTER );
		$none = $this->story();
		update_post_meta( $none, LinkedProfiles::META, array( 999999 ) );
		$this->assertStringNotContainsString( 'On the record', $this->render( $none ) );
	}

	public function test_REQ_265_story_shows_related_event_only_while_published(): void {
		$event = (int) wp_insert_post(
			array(
				'post_type'   => EventPostType::TYPE,
				'post_title'  => 'Linked Summit',
				'post_status' => 'draft',
			)
		);
		$id    = $this->story();
		update_post_meta( $id, 'related_event_id', $event );
		$this->assertStringNotContainsString( 'Related event', $this->render( $id ) );
		wp_update_post(
			array(
				'ID'          => $event,
				'post_status' => 'publish',
			)
		);
		$out = $this->render( $id );
		$this->assertStringContainsString( 'Related event', $out );
		$this->assertStringContainsString( 'Linked Summit', $out );
		delete_post_meta( $id, 'related_event_id' );
		$this->assertStringNotContainsString( 'Related event', $this->render( $id ) );
	}

	public function test_REQ_182_sponsored_story_shows_the_badge_sponsor_and_notice(): void {
		$id                              = $this->story();
		$this->source->placements[ $id ] = array(
			'sponsor'   => 'Acme Corp',
			'placement' => 'both',
			'start'     => '2026-12-01',
			'end'       => '2026-12-31',
		);
		$out                             = $this->render( $id );
		$this->assertStringContainsString( 'badge--sponsored', $out );
		$this->assertStringContainsString( 'Sponsored by Acme Corp', $out );
		$this->assertStringContainsString( 'did not write it', $out );
		$this->assertStringContainsString( 'no influence over any label', $out );
		wp_delete_post( $id, true );
		$plain = $this->render( $this->story() );
		$this->assertStringNotContainsString( 'badge--sponsored', $plain );
		$this->assertStringNotContainsString( 'did not write it', $plain );
	}

	public function test_REQ_208_a_draft_shows_a_staff_banner_to_staff_and_nothing_to_visitors(): void {
		$id  = $this->story( 'draft' );
		$out = $this->render( $id );
		$this->assertStringNotContainsString( 'Body text.', $out, 'A visitor gets no draft content.' );
		$editor = wp_insert_user(
			array(
				'user_login' => 'story' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => 'administrator',
			)
		);
		$this->assertIsInt( $editor );
		wp_set_current_user( $editor );
		$staff = $this->render( $id );
		$this->assertStringContainsString( 'Staff preview', $staff );
		$this->assertStringContainsString( 'Body text.', $staff );
		wp_set_current_user( 0 );
	}

	public function test_REQ_029_the_template_lists_other_stories_and_reserves_the_comments_hook(): void {
		$first  = $this->story();
		$second = $this->story();
		$out    = $this->render( $first );
		$this->assertStringContainsString( 'More stories', $out );
		$this->assertStringContainsString( get_permalink( $second ), $out );
		$count = 0;
		add_action(
			'wiw_story_comments',
			static function () use ( &$count ): void {
				++$count;
			}
		);
		$this->render( $first );
		remove_all_actions( 'wiw_story_comments' );
		$this->assertSame( 1, $count );
	}
}
