<?php
/**
 * The publication state machine and the status guard (REQ-210, REQ-208, REQ-209, REQ-123). Needs the WordPress test suite.
 *
 * Plain TestCase on purpose: the machine opens real transactions and the tables must be real, not the temporary tables
 * WordPress's own test case substitutes. Posts and rows made here are removed in tearDown.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Review;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateException;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class StateMachineTest extends TestCase {

	/**
	 * Posts created by the test.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		remove_all_actions( 'wiw_profile_state_in_transaction' );
		remove_all_actions( 'wiw_profile_state_changed' );
		parent::tearDown();
	}

	private function profile( string $state = PubState::IMPORTED_NOT_PUBLIC, string $type = 'organisation' ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => 'Test profile',
				'post_status' => 'draft',
			)
		);
		$this->assertIsInt( $id );
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, $state, array( 'entity_type' => $type ) );
		return $id;
	}

	public function test_REQ_210_only_state_machine_changes_pub_state(): void {
		$id = $this->profile();
		try {
			( new StateRepository() )->update( $id, array( 'pub_state' => PubState::PUBLISHED_REVIEWED ) );
			$this->fail( 'The repository must refuse a pub_state write.' );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, ( new StateRepository() )->pub_state( $id ) );
		}
		( new StateMachine() )->transition( $id, PubState::PUBLISHED_REVIEWED, '', 5 );
		$row = ( new StateRepository() )->get( $id );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, $row['pub_state'] );
		$this->assertSame( '5', $row['reviewer_id'] );
		$this->assertNotEmpty( $row['reviewed_at'] );
	}

	public function test_REQ_210_direct_status_write_is_refused_and_logged(): void {
		global $wpdb;
		$id     = $this->profile();
		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wiw_audit_log WHERE action = 'profile.status_write_refused'" ); // phpcs:ignore WordPress.DB
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( ProfileType::STATUS_HIDDEN, get_post_status( $id ), 'The status is put back.' );
		$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wiw_audit_log WHERE action = 'profile.status_write_refused'" ); // phpcs:ignore WordPress.DB
		$this->assertSame( $before + 1, $after, 'The refusal is logged.' );

		$new           = wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => 'Sneaky',
				'post_status' => 'publish',
			)
		);
		$this->posts[] = (int) $new;
		$this->assertSame( ProfileType::STATUS_HIDDEN, get_post_status( $new ), 'A profile cannot be created public.' );
	}

	public function test_REQ_210_state_change_is_atomic(): void {
		$id = $this->profile();
		add_action(
			'wiw_profile_state_in_transaction',
			static function (): void {
				throw new \RuntimeException( 'search index failed' );
			}
		);
		try {
			( new StateMachine() )->transition( $id, PubState::PUBLISHED_REVIEWED );
			$this->fail( 'The failure must surface.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'search index failed', $e->getMessage() );
		}
		clean_post_cache( $id );
		$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, ( new StateRepository() )->pub_state( $id ), 'State rolled back.' );
		$this->assertSame( ProfileType::STATUS_HIDDEN, get_post_status( $id ), 'Post status rolled back.' );
	}

	public function test_REQ_209_transition_without_reason_is_refused(): void {
		$id = $this->profile( PubState::PUBLISHED_PENDING );
		foreach ( array( PubState::UNPUBLISHED, PubState::REJECTED, PubState::REMOVED_SUPPRESSED ) as $to ) {
			try {
				( new StateMachine() )->transition( $id, $to, '  ' );
				$this->fail( "{$to} without a reason must be refused" );
			} catch ( StateException $e ) {
				$this->assertSame( PubState::PUBLISHED_PENDING, ( new StateRepository() )->pub_state( $id ) );
			}
		}
		try {
			( new StateMachine() )->transition( $id, PubState::PUBLISHED_REVIEWED, '', null, 'batch1' );
			$this->fail( 'A bulk action without a reason must be refused' );
		} catch ( StateException $e ) {
			$this->assertSame( PubState::PUBLISHED_PENDING, ( new StateRepository() )->pub_state( $id ) );
		}
		( new StateMachine() )->transition( $id, PubState::UNPUBLISHED, 'Takedown request' );
		$this->assertSame( PubState::UNPUBLISHED, ( new StateRepository() )->pub_state( $id ) );
	}

	public function test_REQ_210_disallowed_transition_is_refused(): void {
		$id = $this->profile( PubState::PUBLISHED_PENDING );
		( new StateMachine() )->transition( $id, PubState::PUBLISHED_REVIEWED );
		$this->expectException( StateException::class );
		( new StateMachine() )->transition( $id, PubState::IMPORTED_NOT_PUBLIC );
	}

	public function test_REQ_210_removed_is_terminal_and_start_states_are_limited(): void {
		$this->assertSame( array(), StateMachine::TRANSITIONS[ PubState::REMOVED_SUPPRESSED ] );
		foreach ( PubState::all() as $state ) {
			$this->assertArrayHasKey( $state, StateMachine::TRANSITIONS );
		}
		$id            = wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'X',
			)
		);
		$this->posts[] = (int) $id;
		$this->expectException( StateException::class );
		( new StateMachine() )->enter( $id, PubState::PUBLISHED_REVIEWED, array( 'entity_type' => 'individual' ) );
	}

	public function test_REQ_208_only_published_states_use_public_status(): void {
		$expected = array(
			PubState::IMPORTED_NOT_PUBLIC => 'wiw_hidden',
			PubState::PUBLISHED_PENDING   => 'publish',
			PubState::PUBLISHED_REVIEWED  => 'publish',
			PubState::UNPUBLISHED         => 'wiw_hidden',
			PubState::REJECTED            => 'wiw_hidden',
			PubState::REMOVED_SUPPRESSED  => 'wiw_hidden',
		);
		foreach ( $expected as $state => $status ) {
			$this->assertSame( $status, StateMachine::post_status_for( $state ) );
		}
		$id = $this->profile( PubState::PUBLISHED_PENDING );
		$this->assertSame( 'publish', get_post_status( $id ) );
		( new StateMachine() )->transition( $id, PubState::UNPUBLISHED, 'Takedown' );
		$this->assertSame( 'wiw_hidden', get_post_status( $id ) );
		( new StateMachine() )->transition( $id, PubState::PUBLISHED_REVIEWED );
		$this->assertSame( 'publish', get_post_status( $id ) );
	}

	public function test_REQ_210_change_is_audited_and_announced_after_commit(): void {
		global $wpdb;
		$id   = $this->profile();
		$seen = array();
		add_action(
			'wiw_profile_state_changed',
			static function ( int $profile, ?string $from, string $to ) use ( &$seen ): void {
				$seen[] = array( $profile, $from, $to );
			},
			10,
			3
		);
		( new StateMachine() )->transition( $id, PubState::REJECTED, 'Not notable' );
		$this->assertSame( array( array( $id, PubState::IMPORTED_NOT_PUBLIC, PubState::REJECTED ) ), $seen );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wiw_audit_log WHERE action = 'profile.state_change' AND subject_id = %s ORDER BY id DESC LIMIT 1", (string) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$this->assertSame( 'Not notable', $row['reason'] );
		$this->assertSame( '{"pub_state":"rejected"}', $row['new_value'] );
	}
}
