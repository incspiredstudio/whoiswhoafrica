<?php
/**
 * Search through the service a visitor uses (REQ-208, REQ-209, REQ-183, REQ-254): one profile per publication state and one story
 * per post state, searched by their unique word. Only what a visitor may see is returned; unpublishing a profile removes it from
 * the very next search; a paid placement on a story does not change the order of results. Needs the WordPress test suite and the
 * real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Visibility;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Content\Search\StoryIndexer;
use WhoIsWho\Core\Content\Stories\StoryFields;
use WhoIsWho\Core\Content\Stories\StoryFlags;
use WhoIsWho\Core\Content\Stories\StoryTypes;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Search\SearchIndexer;
use WhoIsWho\Core\Directory\Search\SearchService;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class SearchVisibilityTest extends TestCase {

	private const TOKEN = 'Zvqkx';

	/**
	 * Posts created.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		StoryTypes::register();
		StoryTypes::ensure_terms();
		StoryFields::register_meta();
		EventPostType::register();
		( new StoryIndexer() )->register();
		( new SearchIndexer() )->register();
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( SearchIndexer::table(), array( 'item_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		parent::tearDown();
	}

	/**
	 * A profile in a state, named with the shared search word.
	 *
	 * @param string $state Publication state.
	 */
	private function profile( string $state ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => self::TOKEN . ' ' . str_replace( '_', ' ', $state ) . ' Co',
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$start         = in_array( $state, array( PubState::IMPORTED_NOT_PUBLIC, PubState::REJECTED ), true ) ? PubState::IMPORTED_NOT_PUBLIC : PubState::PUBLISHED_PENDING;
		$machine->enter( $id, $start, array( 'entity_type' => 'organisation' ) );
		if ( PubState::PUBLISHED_REVIEWED === $state ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		} elseif ( in_array( $state, array( PubState::REJECTED, PubState::UNPUBLISHED, PubState::REMOVED_SUPPRESSED ), true ) ) {
			$machine->transition( $id, $state, 'test' );
		}
		( new SearchIndexer() )->on_state_change( $id, null, (string) ( new StateRepository() )->pub_state( $id ) );
		return $id;
	}

	/**
	 * A story.
	 *
	 * @param string               $title Title.
	 * @param array<string, mixed> $args  Overrides.
	 */
	private function story( string $title, array $args = array() ): int {
		$id            = (int) wp_insert_post(
			array_merge(
				array(
					'post_title'   => $title,
					'post_content' => 'Body text about harbour logistics.',
					'post_status'  => 'publish',
				),
				$args
			)
		);
		$this->posts[] = $id;
		return $id;
	}

	/**
	 * Ids a visitor's search returns, profiles and stories together.
	 *
	 * @param string $query Query.
	 * @return int[]
	 */
	private function found( string $query = self::TOKEN ): array {
		$reply = ( new SearchService() )->search( $query );
		return array_merge( array_column( $reply['profiles'], 'id' ), array_column( $reply['stories'], 'id' ) );
	}

	public function test_REQ_208_visibility_matrix_search_surface(): void {
		$expected_public = array(
			PubState::PUBLISHED_REVIEWED  => true,
			PubState::PUBLISHED_PENDING   => true,
			PubState::IMPORTED_NOT_PUBLIC => false,
			PubState::UNPUBLISHED         => false,
			PubState::REJECTED            => false,
			PubState::REMOVED_SUPPRESSED  => false,
		);
		$profiles        = array();
		foreach ( array_keys( $expected_public ) as $state ) {
			$profiles[ $state ] = $this->profile( $state );
		}
		$stories = array(
			'publish' => $this->story( self::TOKEN . ' published story' ),
			'draft'   => $this->story( self::TOKEN . ' draft story', array( 'post_status' => 'draft' ) ),
			'future'  => $this->story(
				self::TOKEN . ' scheduled story',
				array(
					'post_status' => 'future',
					'post_date'   => gmdate( 'Y-m-d H:i:s', time() + 86400 ),
				)
			),
			'private' => $this->story( self::TOKEN . ' private story', array( 'post_status' => 'private' ) ),
		);
		$trashed = $this->story( self::TOKEN . ' trashed story' );
		wp_trash_post( $trashed );

		$found    = $this->found();
		$failures = array();
		foreach ( $expected_public as $state => $public ) {
			if ( in_array( $profiles[ $state ], $found, true ) !== $public ) {
				$failures[] = "profile in {$state}: expected " . ( $public ? 'found' : 'absent' );
			}
		}
		foreach ( $stories as $status => $id ) {
			$should = 'publish' === $status;
			if ( in_array( $id, $found, true ) !== $should ) {
				$failures[] = "story {$status}: expected " . ( $should ? 'found' : 'absent' );
			}
		}
		if ( in_array( $trashed, $found, true ) ) {
			$failures[] = 'trashed story found';
		}
		$this->assertSame( array(), $failures, implode( "\n", $failures ) );
	}

	public function test_REQ_209_removed_profile_leaves_search_at_once(): void {
		$id = $this->profile( PubState::PUBLISHED_REVIEWED );
		$this->assertContains( $id, $this->found(), 'a reviewed profile is found' );
		( new StateMachine() )->transition( $id, PubState::UNPUBLISHED, 'Unpublished for the test', 1 );
		( new SearchIndexer() )->on_state_change( $id, PubState::PUBLISHED_REVIEWED, PubState::UNPUBLISHED );
		$this->assertNotContains( $id, $this->found(), 'the next search no longer returns it' );
	}

	public function test_REQ_183_arrangement_does_not_change_search_order(): void {
		$first  = $this->story( self::TOKEN . ' harbour alpha', array( 'post_date' => '2026-10-01 10:00:00' ) );
		$second = $this->story( self::TOKEN . ' harbour beta', array( 'post_date' => '2026-10-02 10:00:00' ) );
		$third  = $this->story( self::TOKEN . ' harbour gamma', array( 'post_date' => '2026-10-03 10:00:00' ) );
		$before = $this->found();
		$this->assertEqualsCanonicalizing( array( $first, $second, $third ), $before );

		// A paid placement on the oldest story (the one sanctioned writer of the sponsor fields).
		( new StoryFlags( AuditLogger::for_site() ) )->commerce_write( $first, 'Fictional Sponsor Ltd', 'both', '2000-01-01', '2099-12-31' );
		( new StoryIndexer() )->upsert( $first );
		$after = $this->found();
		$this->assertSame( $before, $after, 'the order of results does not change when a story is sponsored' );
	}
}
