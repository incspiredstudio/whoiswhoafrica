<?php
/**
 * Random mode seed, caching and purge on save (REQ-257). Needs the WordPress test suite and the real state tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Curation;

use WhoIsWho\Core\Content\Curation\CurationCache;
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

final class CurationCacheTest extends TestCase {

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
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function profiles( int $count ): void {
		$machine = new StateMachine();
		for ( $i = 0; $i < $count; $i++ ) {
			$id            = (int) wp_insert_post(
				array(
					'post_type'  => ProfileType::POST_TYPE,
					'post_title' => 'Cache Person ' . $i,
				)
			);
			$this->posts[] = $id;
			$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'individual' ) );
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
			update_post_meta( $id, PublicProfile::IMAGE_META, 99 );
		}
	}

	private function settings(): CurationSettings {
		return new CurationSettings( new AuditLogger( new WpdbConnection( 'wiw_audit_log' ) ) );
	}

	/**
	 * Save a Random setting directly.
	 */
	private function save_random(): void {
		update_option(
			CurationSettings::OPTION_PREFIX . CurationSettings::ON_THE_RECORD,
			array(
				'mode'           => 'random',
				'count'          => 8,
				'pinned'         => array(),
				'fill'           => 'random',
				'featured_first' => false,
			)
		);
	}

	/**
	 * Ids of On the record for a day.
	 *
	 * @param string $day Date.
	 * @return array<int, int>
	 */
	private function ids( string $day ): array {
		$engine = new ProfileCuration( $this->settings(), static fn (): string => $day );
		return array_map( static fn ( PublicProfile $p ): int => $p->id, $engine->items( CurationSettings::ON_THE_RECORD ) );
	}

	/**
	 * Cached candidate lists.
	 */
	private function cached(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_' . ProfileCuration::CACHE_PREFIX ) . '%' ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function test_REQ_257_random_mode_is_stable_for_the_day(): void {
		$this->profiles( 8 );
		$this->save_random();
		$first = $this->ids( '2026-11-12' );
		$this->assertCount( 8, $first );
		$this->assertGreaterThan( 0, $this->cached(), 'The candidate list is cached.' );
		$this->assertSame( $first, $this->ids( '2026-11-12' ) );
		ProfileCuration::flush_cache();
		$this->assertSame( $first, $this->ids( '2026-11-12' ), 'The seed, not the cache, fixes the day.' );
	}

	public function test_REQ_257_random_mode_changes_next_day(): void {
		$this->profiles( 8 );
		$this->save_random();
		$this->assertNotSame( $this->ids( '2026-11-12' ), $this->ids( '2026-11-13' ) );
	}

	public function test_REQ_257_every_eligible_item_gets_a_turn_across_days(): void {
		$this->profiles( 6 );
		update_option(
			CurationSettings::OPTION_PREFIX . CurationSettings::ON_THE_RECORD,
			array(
				'mode'           => 'random',
				'count'          => 1,
				'pinned'         => array(),
				'fill'           => 'random',
				'featured_first' => false,
			)
		);
		$seen = array();
		for ( $day = 1; $day <= 60; $day++ ) {
			$seen = array_merge( $seen, $this->ids( gmdate( 'Y-m-d', (int) strtotime( '2027-01-01 +' . $day . ' days' ) ) ) );
		}
		$this->assertCount( 6, array_unique( $seen ), 'Over enough days every eligible profile is shown.' );
	}

	public function test_REQ_257_saving_settings_purges_cache(): void {
		$this->profiles( 4 );
		$this->save_random();
		$this->ids( '2026-11-12' );
		$this->assertGreaterThan( 0, $this->cached() );

		$purged = array();
		add_action(
			'wiw_purge_urls',
			static function ( array $urls ) use ( &$purged ): void {
				$purged = $urls;
			}
		);
		CurationCache::register();
		$user = (int) wp_insert_user(
			array(
				'user_login' => 'cur_' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'user_email' => 'cur' . wp_generate_password( 6, false ) . '@example.org',
			)
		);
		get_userdata( $user )->add_cap( CurationSettings::CAP );
		wp_set_current_user( $user );
		$this->settings()->set( CurationSettings::ON_THE_RECORD, array( 'count' => 3 ) );

		$this->assertSame( 0, $this->cached(), 'Saving drops every cached list.' );
		$this->assertContains( home_url( '/' ), $purged, 'Saving purges the public Home page.' );
	}

	public function test_REQ_257_daily_refresh_warms_random_blocks_and_purges_home(): void {
		$this->profiles( 3 );
		$this->save_random();
		$purged = array();
		add_action(
			'wiw_purge_urls',
			static function ( array $urls ) use ( &$purged ): void {
				$purged = $urls;
			}
		);
		CurationCache::refresh( $this->settings() );
		$this->assertGreaterThan( 0, $this->cached() );
		$this->assertContains( home_url( '/' ), $purged );
	}
}
