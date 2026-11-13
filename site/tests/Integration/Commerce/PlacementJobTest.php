<?php
/**
 * The placement job and Sponsored label service (REQ-182, REQ-116, REQ-183). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Commerce;

use WhoIsWho\Core\Commerce\ArrangementRepository;
use WhoIsWho\Core\Commerce\PlacementJob;
use WhoIsWho\Core\Commerce\PlacementRepository;
use WhoIsWho\Core\Content\Stories\StoryFlags;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\TestCase;

final class PlacementJobTest extends TestCase {

	private const PROBE = 'wiw_job_audit_probe';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		foreach ( array( ArrangementRepository::table(), PlacementRepository::table() ) as $table ) {
			$wpdb->query( 'TRUNCATE TABLE ' . $table ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		}
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		parent::tearDown();
	}

	private function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( '2026-11-12 10:00:00', new \DateTimeZone( 'UTC' ) );
	}

	private function job(): PlacementJob {
		$audit = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00', static fn (): int => 1 );
		return new PlacementJob( new PlacementRepository(), new StoryFlags( $audit ) );
	}

	/**
	 * A story with a placement.
	 *
	 * @param string $starts Start.
	 * @param string $ends   End.
	 * @return array{story: int, deal: int, placement: int}
	 */
	private function placed( string $starts = '2026-12-01', string $ends = '2026-12-31' ): array {
		$story     = (int) wp_insert_post(
			array(
				'post_title'  => 'Story',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);
		$deal      = ( new ArrangementRepository() )->insert(
			array(
				'customer_id'  => 1,
				'amount_minor' => 100,
				'agreed'       => true,
			),
			$this->now()
		);
		$placement = ( new PlacementRepository() )->insert(
			array(
				'arrangement_id' => $deal,
				'story_id'       => $story,
				'sponsor_name'   => 'Obi Holdings',
				'slot'           => 'home',
				'starts_on'      => $starts,
				'ends_on'        => $ends,
			),
			$this->now()
		);
		return array(
			'story'     => $story,
			'deal'      => $deal,
			'placement' => $placement,
		);
	}

	public function test_REQ_182_label_applies_only_between_start_and_end(): void {
		$set = $this->placed();
		$job = $this->job();
		$this->assertSame(
			array(
				'applied' => 0,
				'cleared' => 0,
			),
			$job->run( '2026-11-30' ),
			'Before the start: nothing.'
		);
		$this->assertSame( '', (string) get_post_meta( $set['story'], 'wiw_sponsor_name', true ) );
		$this->assertSame( 1, $job->run( '2026-12-01' )['applied'] );
		$this->assertSame( 'Obi Holdings', get_post_meta( $set['story'], 'wiw_sponsor_name', true ) );
		$this->assertSame( 'home', get_post_meta( $set['story'], 'wiw_sponsor_placement', true ) );
		$this->assertSame( 1, $job->run( '2026-12-31' )['applied'] );
		$this->assertSame( 1, $job->run( '2027-01-01' )['cleared'], 'After the end: cleared.' );
		$this->assertSame( '', (string) get_post_meta( $set['story'], 'wiw_sponsor_name', true ) );
		$this->assertNotNull( ( new PlacementRepository() )->get( $set['placement'] ), 'Nothing is deleted.' );
	}

	public function test_REQ_116_label_removed_when_placement_ends_or_deal_cancelled(): void {
		$set = $this->placed();
		$job = $this->job();
		$job->run( '2026-12-10' );
		( new PlacementRepository() )->mark_ended( $set['placement'], $this->now() );
		$this->assertSame( 1, $job->run( '2026-12-10' )['cleared'] );
		$this->assertSame( '', (string) get_post_meta( $set['story'], 'wiw_sponsor_name', true ) );

		$other = $this->placed();
		$job->run( '2026-12-10' );
		$this->assertSame( 'Obi Holdings', get_post_meta( $other['story'], 'wiw_sponsor_name', true ) );
		( new ArrangementRepository() )->update( $other['deal'], array( 'cancelled' => true ), $this->now() );
		$this->assertSame( 1, $job->run( '2026-12-10' )['cleared'] );
		$this->assertSame( '', (string) get_post_meta( $other['story'], 'wiw_sponsor_name', true ) );
	}

	public function test_REQ_182_label_service_cannot_write_profile_columns(): void {
		$profile = (int) wp_insert_post(
			array(
				'post_title'  => 'A profile',
				'post_status' => 'draft',
				'post_type'   => 'wiw_profile',
			)
		);
		$audit   = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00', static fn (): int => 1 );
		$flags   = new StoryFlags( $audit );
		try {
			$flags->commerce_write( $profile, 'Obi Holdings', 'home', '2026-12-01', '2026-12-31' );
			$this->fail( 'The label service wrote to a profile.' );
		} catch ( \DomainException $e ) {
			$this->assertNotSame( '', $e->getMessage() );
		}
		$this->assertSame( '', (string) get_post_meta( $profile, 'wiw_sponsor_name', true ) );
		$rows = ( new PlacementRepository() )->all_with_deal_state();
		$this->assertSame( array(), $rows );
	}

	public function test_REQ_183_slot_does_not_reorder_unsponsored_stories_or_search(): void {
		$set    = $this->placed();
		$before = get_post( $set['story'] );
		$this->assertInstanceOf( \WP_Post::class, $before );
		$this->job()->run( '2026-12-10' );
		clean_post_cache( $set['story'] );
		$after = get_post( $set['story'] );
		$this->assertInstanceOf( \WP_Post::class, $after );
		$this->assertSame( $before->post_date, $after->post_date );
		$this->assertSame( $before->menu_order, $after->menu_order );
		$this->assertFalse( is_sticky( $set['story'] ) );
		$this->assertSame( 'Obi Holdings', get_post_meta( $set['story'], 'wiw_sponsor_name', true ) );
	}
}
