<?php
/**
 * The story block curation engine (REQ-257, REQ-263, REQ-183). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Curation;

use WhoIsWho\Core\Content\Curation\CurationSettings;
use WhoIsWho\Core\Content\Curation\StoryCuration;
use WhoIsWho\Core\Content\Stories\StoryFields;
use WhoIsWho\Core\Content\Stories\StoryTypes;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Tests\Support\FixtureSponsoredSource;
use WhoIsWho\Tests\TestCase;

final class StoryCurationTest extends TestCase {

	/**
	 * Fixture source.
	 *
	 * @var FixtureSponsoredSource
	 */
	private FixtureSponsoredSource $source;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		wp_set_current_user( 0 );
		StoryTypes::register();
		StoryTypes::ensure_terms();
		StoryFields::register_meta();
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
		update_option( 'sticky_posts', array() );
		delete_option( CurationSettings::OPTION_PREFIX . CurationSettings::STORIES_HOME );
		$this->source = new FixtureSponsoredSource();
		add_filter( 'wiw_sponsored_source', fn (): FixtureSponsoredSource => $this->source );
		add_filter( 'wiw_story_today', static fn (): string => '2026-12-10' );
	}

	protected function tearDown(): void {
		remove_all_filters( 'wiw_sponsored_source' );
		remove_all_filters( 'wiw_story_today' );
		delete_option( CurationSettings::OPTION_PREFIX . CurationSettings::STORIES_HOME );
		update_option( 'sticky_posts', array() );
		parent::tearDown();
	}

	/**
	 * Create a story.
	 *
	 * @param string $date   Post date.
	 * @param string $status Post status.
	 */
	private function story( string $date, string $status = 'publish' ): int {
		$id = wp_insert_post(
			array(
				'post_title'   => 'Story ' . wp_generate_password( 5, false ),
				'post_content' => 'Body text.',
				'post_status'  => $status,
				'post_type'    => 'post',
				'post_date'    => $date,
			)
		);
		$this->assertIsInt( $id );
		wp_set_object_terms( $id, 'news', StoryTypes::TAXONOMY );
		return $id;
	}

	/**
	 * Engine with saved settings for the Home block.
	 *
	 * @param array<string, mixed> $over Setting overrides.
	 */
	private function engine( array $over = array() ): StoryCuration {
		update_option(
			CurationSettings::OPTION_PREFIX . CurationSettings::STORIES_HOME,
			array_merge(
				array(
					'mode'           => 'latest',
					'count'          => 3,
					'pinned'         => array(),
					'fill'           => 'latest',
					'featured_first' => true,
				),
				$over
			)
		);
		return new StoryCuration( new CurationSettings( new AuditLogger( new WpdbConnection( 'wiw_audit_log' ) ) ), static fn (): string => '2026-12-10' );
	}

	/**
	 * Ids of the items of the Home block.
	 *
	 * @param StoryCuration $engine Engine.
	 * @return array<int, int>
	 */
	private function ids( StoryCuration $engine ): array {
		return array_map( static fn ( array $card ): int => (int) $card['id'], $engine->items( CurationSettings::STORIES_HOME )['items'] );
	}

	public function test_REQ_257_stories_featured_first_then_latest(): void {
		$old      = $this->story( '2026-01-01 10:00:00' );
		$mid      = $this->story( '2026-02-01 10:00:00' );
		$new      = $this->story( '2026-03-01 10:00:00' );
		$featured = $this->story( '2026-01-15 10:00:00' );
		$this->story( '2026-04-01 10:00:00', 'draft' );
		update_option( 'sticky_posts', array( $featured ) );
		$this->assertSame( array( $featured, $new, $mid ), $this->ids( $this->engine() ), 'Featured first, then newest; the draft never shows' );
		$this->assertSame( array( $new, $mid, $featured ), $this->ids( $this->engine( array( 'featured_first' => false ) ) ), 'plain latest when the block does not ask for Featured first' );
		$this->assertNotContains( $old, $this->ids( $this->engine() ), 'the count limits the block' );
	}

	public function test_REQ_257_stories_manual_mode_returns_chosen_order(): void {
		$a     = $this->story( '2026-01-01 10:00:00' );
		$b     = $this->story( '2026-02-01 10:00:00' );
		$c     = $this->story( '2026-03-01 10:00:00' );
		$draft = $this->story( '2026-03-02 10:00:00', 'draft' );
		$this->assertSame(
			array( $a, $c, $b ),
			$this->ids(
				$this->engine(
					array(
						'mode'   => 'manual',
						'pinned' => array( $a, $draft, $c, $b ),
					)
				)
			),
			'chosen order, unpublished pins drop out'
		);
	}

	public function test_REQ_257_pinned_then_fill_and_random_are_stable_for_the_day(): void {
		$ids = array();
		foreach ( range( 1, 6 ) as $i ) {
			$ids[] = $this->story( sprintf( '2026-0%d-01 10:00:00', $i ) );
		}
		$mixed = $this->engine(
			array(
				'mode'   => 'pinned_then_fill',
				'count'  => 3,
				'pinned' => array( $ids[0] ),
				'fill'   => 'latest',
			)
		);
		$this->assertSame( array( $ids[0], $ids[5], $ids[4] ), $this->ids( $mixed ), 'the pin, then the newest others' );
		$random = $this->engine(
			array(
				'mode'           => 'random',
				'count'          => 4,
				'featured_first' => false,
			)
		);
		$first  = $this->ids( $random );
		$this->assertCount( 4, $first );
		$this->assertSame( $first, $this->ids( $random ), 'same order all day' );
	}

	public function test_REQ_263_slot_never_reorders_curated_stories(): void {
		$a = $this->story( '2026-01-01 10:00:00' );
		$b = $this->story( '2026-02-01 10:00:00' );
		$c = $this->story( '2026-03-01 10:00:00' );
		update_option( 'sticky_posts', array( $a ) );
		$engine  = $this->engine();
		$without = $engine->items( CurationSettings::STORIES_HOME );
		$this->assertNull( $without['slot'] );

		$this->source->placements[ $b ] = array(
			'sponsor'   => 'Acme',
			'placement' => 'both',
			'start'     => '2026-12-01',
			'end'       => '2026-12-31',
		);
		$with                           = $engine->items( CurationSettings::STORIES_HOME );
		$this->assertSame( $b, $with['slot']['id'], 'the active placement fills the slot' );
		$this->assertSame( array_column( $without['items'], 'id' ), array_column( $with['items'], 'id' ), 'items identical with and without a placement' );
		$this->assertSame( array( $a, $c, $b ), array_column( $with['items'], 'id' ) );

		unset( $this->source->placements[ $b ] );
		$this->assertSame( array_column( $without['items'], 'id' ), array_column( $engine->items( CurationSettings::STORIES_HOME )['items'], 'id' ), 'ending the arrangement changes nothing' );
	}

	public function test_REQ_257_the_stories_page_block_uses_the_stories_surface_and_unknown_blocks_are_empty(): void {
		$id                              = $this->story( '2026-01-01 10:00:00' );
		$this->source->placements[ $id ] = array(
			'sponsor'   => 'Acme',
			'placement' => 'home',
			'start'     => '',
			'end'       => '',
		);
		$engine                          = $this->engine();
		$this->assertNotNull( $engine->items( CurationSettings::STORIES_HOME )['slot'] );
		$this->assertNull( $engine->items( CurationSettings::STORIES_PAGE )['slot'], 'a Home-only placement does not fill the Stories page slot' );
		$this->assertSame(
			array(
				'items' => array(),
				'slot'  => null,
			),
			$engine->items( CurationSettings::ON_THE_RECORD )
		);
	}
}
