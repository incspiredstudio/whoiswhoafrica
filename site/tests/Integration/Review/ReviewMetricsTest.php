<?php
/**
 * Review throughput, queue age and rejection reasons (REQ-149). Needs the WordPress test suite. Plain TestCase: the state
 * machine opens real transactions on real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Review;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\Metrics\DecisionLog;
use WhoIsWho\Core\Review\Metrics\ReviewMetrics;
use WhoIsWho\Core\Review\ReviewService;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ReviewMetricsTest extends TestCase {

	/**
	 * Posts created.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Users created.
	 *
	 * @var int[]
	 */
	private array $users = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
		$wpdb->query( 'DELETE FROM ' . DecisionLog::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
		$wpdb->query( 'DELETE FROM ' . DecisionLog::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$this->posts = array();
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function reviewer(): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'rev' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'user_email' => wp_generate_password( 8, false ) . '@example.test',
				'role'       => 'wiw_reviewer',
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	private function profile( string $waiting_since = '' ): int {
		global $wpdb;
		$id = wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => 'Metrics test ' . count( $this->posts ),
				'post_status' => 'draft',
			)
		);
		$this->assertIsInt( $id );
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		if ( '' !== $waiting_since ) {
			$wpdb->update( StateRepository::table(), array( 'updated_at' => $waiting_since ), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return $id;
	}

	public function test_REQ_149_decision_records_time_and_reason(): void {
		$reviewer = $this->reviewer();
		$waited   = $this->profile( gmdate( 'Y-m-d H:i:s', time() - 7200 ) );
		( new ReviewService() )->reject( $waited, 'The sources do not support it.', $reviewer, 'unverifiable' );
		$approved = $this->profile( gmdate( 'Y-m-d H:i:s', time() - 600 ) );
		( new ReviewService() )->approve( $approved, $reviewer );

		$rows = ( new DecisionLog() )->recent( 10 );
		$this->assertCount( 2, $rows );
		$by_profile = array_column( $rows, null, 'profile_id' );
		$this->assertSame( 'rejected', $by_profile[ $waited ]['decision'] );
		$this->assertSame( 'unverifiable', $by_profile[ $waited ]['reason_code'] );
		$this->assertSame( $reviewer, $by_profile[ $waited ]['actor_id'] );
		$this->assertEqualsWithDelta( 7200, $by_profile[ $waited ]['waited_seconds'], 30 );
		$this->assertSame( 'approved', $by_profile[ $approved ]['decision'] );
		$this->assertSame( '', $by_profile[ $approved ]['reason_code'] );
		$this->assertEqualsWithDelta( 600, $by_profile[ $approved ]['waited_seconds'], 30 );
	}

	public function test_REQ_149_an_unknown_reason_code_is_stored_as_other(): void {
		$reviewer = $this->reviewer();
		$id       = $this->profile();
		( new ReviewService() )->reject( $id, 'No.', $reviewer, 'made up' );
		$this->assertSame( 'other', ( new DecisionLog() )->recent( 1 )[0]['reason_code'] );
	}

	public function test_REQ_149_metrics_panel_counts_rejection_reasons(): void {
		$reviewer = $this->reviewer();
		$service  = new ReviewService();
		$service->reject( $this->profile(), 'Dup.', $reviewer, 'duplicate' );
		$service->reject( $this->profile(), 'Dup 2.', $reviewer, 'duplicate' );
		$service->reject( $this->profile(), 'Wrong.', $reviewer, 'inaccurate' );
		$service->approve( $this->profile(), $reviewer );
		$waiting = $this->profile( gmdate( 'Y-m-d H:i:s', time() - 3 * 86400 ) );

		$metrics = new ReviewMetrics();
		$this->assertSame(
			array(
				'duplicate'  => 2,
				'inaccurate' => 1,
			),
			$metrics->rejection_reasons()
		);
		$weeks = $metrics->decisions_per_week( 4 );
		$this->assertCount( 4, $weeks );
		$this->assertSame( 4, array_sum( array_column( $weeks, 'decisions' ) ) );
		$this->assertGreaterThanOrEqual( 3 * 86400 - 120, (int) $metrics->oldest_waiting_seconds(), 'the oldest item still in the queue' );

		$html = $metrics->html();
		$this->assertStringContainsString( 'duplicate', $html );
		$this->assertStringContainsString( '3 days', $html );
		$this->assertGreaterThan( 0, $waiting );
	}
}
