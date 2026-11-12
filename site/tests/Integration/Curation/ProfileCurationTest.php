<?php
/**
 * The On the record curation engine (REQ-257, REQ-218, REQ-183). Needs the WordPress test suite and the real state tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Curation;

use WhoIsWho\Core\Content\Curation\CurationSettings;
use WhoIsWho\Core\Content\Curation\ProfileCuration;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ProfileCurationTest extends TestCase {

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
		delete_option( CurationSettings::OPTION_PREFIX . CurationSettings::ON_THE_RECORD );
		ProfileCuration::flush_cache();
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		delete_option( CurationSettings::OPTION_PREFIX . CurationSettings::ON_THE_RECORD );
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
	 * Engine with saved settings.
	 *
	 * @param array<string, mixed> $setting Settings.
	 */
	private function engine( array $setting ): ProfileCuration {
		update_option( CurationSettings::OPTION_PREFIX . CurationSettings::ON_THE_RECORD, $setting );
		$settings = new CurationSettings( new AuditLogger( new WpdbConnection( 'wiw_audit_log' ) ) );
		return new ProfileCuration( $settings, static fn (): string => '2026-11-12' );
	}

	/**
	 * Ids of the items.
	 *
	 * @param ProfileCuration $engine Engine.
	 * @return array<int, int>
	 */
	private function ids( ProfileCuration $engine ): array {
		return array_map( static fn ( PublicProfile $p ): int => $p->id, $engine->items( CurationSettings::ON_THE_RECORD ) );
	}

	/**
	 * Settings array.
	 *
	 * @param string          $mode   Mode.
	 * @param int             $count  Count.
	 * @param array<int, int> $pinned Pinned.
	 * @param string          $fill   Fill.
	 * @return array<string, mixed>
	 */
	private function setting( string $mode, int $count = 3, array $pinned = array(), string $fill = 'latest' ): array {
		return array(
			'mode'           => $mode,
			'count'          => $count,
			'pinned'         => $pinned,
			'fill'           => $fill,
			'featured_first' => false,
		);
	}

	public function test_REQ_257_latest_mode_returns_newest_eligible(): void {
		$a = $this->profile( 'Curation A' );
		$b = $this->profile( 'Curation B' );
		$c = $this->profile( 'Curation C' );
		$this->profile( 'Curation No Portrait', PubState::PUBLISHED_REVIEWED, false );
		$d = $this->profile( 'Curation D' );
		$this->assertSame( array( $d, $c ), $this->ids( $this->engine( $this->setting( 'latest', 2 ) ) ) );
		$this->assertSame( array( $d, $c, $b ), $this->ids( $this->engine( $this->setting( 'latest', 3 ) ) ) );
		$this->assertNotContains( $a, $this->ids( $this->engine( $this->setting( 'latest', 3 ) ) ) );
	}

	public function test_REQ_257_random_mode_is_stable_within_a_day_and_changes_with_settings(): void {
		for ( $i = 0; $i < 6; $i++ ) {
			$this->profile( 'Curation R' . $i );
		}
		$first  = $this->ids( $this->engine( $this->setting( 'random', 4 ) ) );
		$second = $this->ids( $this->engine( $this->setting( 'random', 4 ) ) );
		$this->assertCount( 4, $first );
		$this->assertSame( $first, $second );
		$this->assertCount( 4, array_unique( $first ) );
		$this->assertCount( 5, $this->ids( $this->engine( $this->setting( 'random', 5 ) ) ) );
	}

	public function test_REQ_257_manual_mode_never_returns_a_hidden_profile(): void {
		$a       = $this->profile( 'Curation M1' );
		$pending = $this->profile( 'Curation M2', PubState::PUBLISHED_PENDING );
		$c       = $this->profile( 'Curation M3' );
		$this->assertSame( array( $c, $a ), $this->ids( $this->engine( $this->setting( 'manual', 3, array( $c, $pending, $a ) ) ) ) );
		ProfileCuration::flush_cache();
		( new StateMachine() )->transition( $c, PubState::UNPUBLISHED, 'test' );
		$this->assertSame( array( $a ), $this->ids( $this->engine( $this->setting( 'manual', 3, array( $c, $pending, $a ) ) ) ) );
	}

	public function test_REQ_257_pinned_then_fill_keeps_pins_first(): void {
		$a = $this->profile( 'Curation P1' );
		$b = $this->profile( 'Curation P2' );
		$c = $this->profile( 'Curation P3' );
		$d = $this->profile( 'Curation P4' );
		$this->assertSame( array( $a, $d, $c ), $this->ids( $this->engine( $this->setting( 'pinned_then_fill', 3, array( $a ), 'latest' ) ) ) );
		$this->assertSame( array( $a, $b ), $this->ids( $this->engine( $this->setting( 'pinned_then_fill', 2, array( $a, $b, $c ), 'random' ) ) ) );
	}

	public function test_REQ_218_pending_profile_never_in_curated_block(): void {
		$pending = $this->profile( 'Curation Pending', PubState::PUBLISHED_PENDING );
		foreach ( array( 'latest', 'random', 'manual', 'pinned_then_fill' ) as $mode ) {
			$this->assertNotContains( $pending, $this->ids( $this->engine( $this->setting( $mode, 5, array( $pending ), 'latest' ) ) ), $mode );
		}
	}

	public function test_REQ_183_arrangement_does_not_change_curated_items(): void {
		$a      = $this->profile( 'Curation S1' );
		$b      = $this->profile( 'Curation S2' );
		$engine = $this->engine( $this->setting( 'latest', 2 ) );
		$before = $this->ids( $engine );
		update_post_meta( $a, '_wiw_arrangement', 'paid' );
		update_option( 'wiw_sponsored_label', array( $a ) );
		$this->assertSame( $before, $this->ids( $this->engine( $this->setting( 'latest', 2 ) ) ) );
		$this->assertSame( array( $b, $a ), $before );
	}
}
