<?php
/**
 * The Sponsored disclosure on share previews (REQ-026, REQ-155, REQ-182). A story's preview says "Sponsored by [name]" only while
 * its placement is active, and a profile's preview never does. The tags are emitted by the plugin (Content\Stories\StorySeo for
 * stories and Directory\Seo\Tags for profiles), not by the theme. Needs the WordPress test suite and the real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Stories;

use WhoIsWho\Core\Content\PublicStory;
use WhoIsWho\Core\Content\Stories\StorySeo;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\Seo\Tags;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\Support\FixtureSponsoredSource;
use WhoIsWho\Tests\TestCase;

final class SponsoredSharePreviewTest extends TestCase {

	/**
	 * Placements the read side sees.
	 *
	 * @var FixtureSponsoredSource
	 */
	private FixtureSponsoredSource $source;

	/**
	 * Today for the placement window.
	 *
	 * @var string
	 */
	private string $today = '2026-12-10';

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		$this->source = new FixtureSponsoredSource();
		add_filter( 'wiw_sponsored_source', fn (): FixtureSponsoredSource => $this->source );
		add_filter( 'wiw_story_today', fn (): string => $this->today );
	}

	protected function tearDown(): void {
		global $wpdb;
		remove_all_filters( 'wiw_sponsored_source' );
		remove_all_filters( 'wiw_story_today' );
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		parent::tearDown();
	}

	/**
	 * A published story.
	 */
	private function story(): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'A Founder Speaks',
				'post_excerpt' => 'How the company began.',
			)
		);
		$this->posts[] = $id;
		return $id;
	}

	/**
	 * The share description of a story today.
	 *
	 * @param int $id Story.
	 */
	private function description( int $id ): string {
		$story = PublicStory::find( $id );
		$this->assertNotNull( $story );
		return StorySeo::tags( $story )['og']['og:description'];
	}

	public function test_REQ_026_sponsored_story_share_preview_has_label(): void {
		$id                              = $this->story();
		$this->source->placements[ $id ] = array(
			'sponsor'   => 'Acme',
			'placement' => 'stories',
			'start'     => '2026-12-01',
			'end'       => '2026-12-31',
		);

		$this->assertStringContainsString( 'Sponsored by Acme', $this->description( $id ), 'Inside the slot dates the preview carries the label and the sponsor name.' );
		$html = StorySeo::markup_for( $id );
		$this->assertStringContainsString( 'Sponsored by Acme', $html );

		$this->today = '2027-01-01';
		$this->assertStringNotContainsString( 'Sponsored', $this->description( $id ), 'After the end date the label is gone.' );
		$this->assertStringNotContainsString( 'Sponsored', StorySeo::markup_for( $id ) );
		$this->assertStringContainsString( 'How the company began.', $this->description( $id ), 'The story and its text stay.' );

		$this->today = '2026-11-30';
		$this->assertStringNotContainsString( 'Sponsored', $this->description( $id ), 'Before the start date there is no label.' );
	}

	public function test_REQ_155_profile_share_preview_never_has_sponsored(): void {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Share Preview Qwv',
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		// Even a placement recorded against the profile's own id never reaches a profile preview.
		$this->source->placements[ $id ] = array(
			'sponsor'   => 'Acme',
			'placement' => 'both',
			'start'     => '2026-12-01',
			'end'       => '2026-12-31',
		);
		$profile                         = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $profile );
		$tags = new Tags();
		$text = strtolower( $tags->render( $tags->for_profile( $profile ) ) );
		$this->assertStringNotContainsString( 'sponsor', $text, 'No Sponsored text in any profile share preview.' );
	}
}
