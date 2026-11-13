<?php
/**
 * The pinning picker and preview for the curated blocks (REQ-257, REQ-218). Needs the WordPress test suite and the real state tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Curation;

use WhoIsWho\Core\Content\Curation\Admin\PinPicker;
use WhoIsWho\Core\Content\Curation\CurationSettings;
use WhoIsWho\Core\Content\Curation\ProfileCuration;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class PinPickerTest extends TestCase {

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
		foreach ( CurationSettings::BLOCKS as $block ) {
			delete_option( CurationSettings::OPTION_PREFIX . $block );
		}
		ProfileCuration::flush_cache();
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		foreach ( CurationSettings::BLOCKS as $block ) {
			delete_option( CurationSettings::OPTION_PREFIX . $block );
		}
		parent::tearDown();
	}

	/**
	 * Create a profile.
	 *
	 * @param string $name     Name.
	 * @param string $state    published_reviewed or published_pending.
	 * @param bool   $portrait Whether it has a portrait.
	 */
	private function profile( string $name, string $state = PubState::PUBLISHED_REVIEWED, bool $portrait = true ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'individual' ) );
		if ( PubState::PUBLISHED_REVIEWED === $state ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		}
		if ( $portrait ) {
			update_post_meta( $id, PublicProfile::IMAGE_META, 99 );
		}
		return $id;
	}

	/**
	 * Create a story.
	 *
	 * @param string $title  Title.
	 * @param string $status Post status.
	 */
	private function story( string $title, string $status = 'publish' ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => $title,
				'post_status' => $status,
			)
		);
		$this->posts[] = $id;
		return $id;
	}

	/**
	 * Ids of a candidate list.
	 *
	 * @param array<int, array{id: int, label: string}> $rows Rows.
	 * @return array<int, int>
	 */
	private function ids( array $rows ): array {
		return array_map( static fn ( array $r ): int => $r['id'], $rows );
	}

	public function test_REQ_257_picker_offers_only_eligible_items(): void {
		$ok      = $this->profile( 'Picker Visible Qxz' );
		$pending = $this->profile( 'Picker Pending Qxz', PubState::PUBLISHED_PENDING );
		$nophoto = $this->profile( 'Picker NoPortrait Qxz', PubState::PUBLISHED_REVIEWED, false );
		$picker  = new PinPicker();
		$found   = $this->ids( $picker->candidates( CurationSettings::ON_THE_RECORD, 'Qxz' ) );
		$this->assertContains( $ok, $found );
		$this->assertNotContains( $pending, $found, 'a pending-review profile cannot be picked' );
		$this->assertNotContains( $nophoto, $found, 'a profile without a portrait cannot be picked' );
		$this->assertTrue( $picker->can_pin( CurationSettings::ON_THE_RECORD, $ok ) );
		$this->assertFalse( $picker->can_pin( CurationSettings::ON_THE_RECORD, $pending ) );
		$this->assertFalse( $picker->can_pin( CurationSettings::ON_THE_RECORD, 999999 ) );
	}

	public function test_REQ_257_picker_search_matches_the_title_and_hides_hidden_profiles(): void {
		$one = $this->profile( 'Picker Alpha Wvy' );
		$two = $this->profile( 'Picker Beta Wvy' );
		( new StateMachine() )->transition( $two, PubState::UNPUBLISHED, 'test', 1 );
		$picker = new PinPicker();
		$this->assertSame( array( $one ), $this->ids( $picker->candidates( CurationSettings::ON_THE_RECORD, 'alpha wvy' ) ) );
		$this->assertSame( array( $one ), $this->ids( $picker->candidates( CurationSettings::ON_THE_RECORD, 'Wvy' ) ), 'an unpublished profile is not offered' );
	}

	public function test_REQ_257_picker_offers_only_public_stories(): void {
		$live   = $this->story( 'Picker Story Live Hqk' );
		$draft  = $this->story( 'Picker Story Draft Hqk', 'draft' );
		$picker = new PinPicker();
		$found  = $this->ids( $picker->candidates( CurationSettings::STORIES_HOME, 'Hqk' ) );
		$this->assertSame( array( $live ), $found );
		$this->assertFalse( $picker->can_pin( CurationSettings::STORIES_HOME, $draft ) );
		$this->assertTrue( $picker->can_pin( CurationSettings::STORIES_PAGE, $live ) );
	}

	public function test_REQ_257_unknown_block_offers_nothing(): void {
		$this->assertSame( array(), ( new PinPicker() )->candidates( 'nope', 'x' ) );
		$this->assertFalse( ( new PinPicker() )->can_pin( 'nope', 1 ) );
	}

	public function test_REQ_257_describe_marks_pins_that_are_no_longer_public(): void {
		$ok      = $this->profile( 'Picker Describe Ok Zlm' );
		$pending = $this->profile( 'Picker Describe Pending Zlm', PubState::PUBLISHED_PENDING );
		$rows    = ( new PinPicker() )->describe( CurationSettings::ON_THE_RECORD, array( $pending, $ok ) );
		$this->assertSame( array( $pending, $ok ), $this->ids( $rows ), 'the chosen order is kept' );
		$this->assertFalse( $rows[0]['public'] );
		$this->assertTrue( $rows[1]['public'] );
		$this->assertSame( 'Picker Describe Pending Zlm', $rows[0]['label'] );
	}

	public function test_REQ_257_preview_matches_public_block(): void {
		$a       = $this->profile( 'Picker Preview A' );
		$b       = $this->profile( 'Picker Preview B' );
		$c       = $this->profile( 'Picker Preview C' );
		$d       = $this->profile( 'Picker Preview Pending', PubState::PUBLISHED_PENDING );
		$setting = array(
			'mode'           => 'manual',
			'count'          => 3,
			'pinned'         => array( $c, $d, $a ),
			'fill'           => 'latest',
			'featured_first' => false,
		);
		$picker  = new PinPicker();
		$preview = $this->ids( $picker->preview( CurationSettings::ON_THE_RECORD, $setting ) );
		$this->assertSame( array( $c, $a ), $preview, 'Manual shows exactly the chosen, eligible items in the chosen order' );
		// Nothing was saved by previewing.
		$this->assertFalse( get_option( CurationSettings::OPTION_PREFIX . CurationSettings::ON_THE_RECORD, false ) );
		// After saving the same setting the public engine returns the same items in the same order.
		update_option( CurationSettings::OPTION_PREFIX . CurationSettings::ON_THE_RECORD, $setting );
		$public = ( new ProfileCuration( new CurationSettings( \WhoIsWho\Core\Platform\Audit\AuditLogger::for_site() ) ) )->items( CurationSettings::ON_THE_RECORD );
		$this->assertSame( $preview, array_map( static fn ( PublicProfile $p ): int => $p->id, $public ) );
	}

	public function test_REQ_257_story_preview_matches_public_block(): void {
		$one     = $this->story( 'Picker Story One Jdp' );
		$two     = $this->story( 'Picker Story Two Jdp' );
		$three   = $this->story( 'Picker Story Three Jdp' );
		$setting = array(
			'mode'           => 'manual',
			'count'          => 3,
			'pinned'         => array( $two, $three, $one ),
			'fill'           => 'latest',
			'featured_first' => false,
		);
		$preview = $this->ids( ( new PinPicker() )->preview( CurationSettings::STORIES_PAGE, $setting ) );
		$this->assertSame( array( $two, $three, $one ), $preview );
	}
}
