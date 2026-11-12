<?php
/**
 * Pending revisions of claimed profiles (REQ-253, REQ-125). Plain TestCase with the real tables, cleaning up after itself.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Review;

use WhoIsWho\Core\Directory\Edit\ProfileEditor;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Purge\PurgeQueue;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Log\LogSink;
use WhoIsWho\Core\Platform\Log\Logger;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Core\Review\ReviewService;
use WhoIsWho\Core\Review\Revisions\RevisionService;
use WhoIsWho\Core\Review\StateException;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class PendingRevisionTest extends TestCase {

	/**
	 * Posts.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Users.
	 *
	 * @var int[]
	 */
	private array $users = array();

	/**
	 * Highest version id before the test.
	 *
	 * @var int
	 */
	private int $floor = 0;

	/**
	 * Notifications sent.
	 *
	 * @var array<int, array{int, string, string, int}>
	 */
	private array $notified = array();

	/**
	 * Queue behind the purge queue.
	 *
	 * @var MemoryQueue
	 */
	private MemoryQueue $queue;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! post_type_exists( ProfileType::POST_TYPE ) ) {
			( new ProfileType() )->register();
		}
		if ( ! taxonomy_exists( Taxonomies::ORG_TYPE ) ) {
			( new Taxonomies() )->register();
		}
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
		$this->notified = array();
		$this->queue    = new MemoryQueue();
		global $wpdb;
		$this->floor = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(id),0) FROM ' . VersionRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
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
		$this->posts = array();
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'rev_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
				'user_email' => 'rev_' . bin2hex( random_bytes( 4 ) ) . '@example.test',
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	/**
	 * A reviewed, claimed profile whose assigned manager is returned in the second slot.
	 *
	 * @return array{int, int}
	 */
	private function claimed_profile(): array {
		$manager       = $this->user( 'wiw_editor' );
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Revision test bank',
			)
		);
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => 'organisation' ) );
		( new ReviewService() )->approve( $id, $this->user( 'wiw_reviewer' ) );
		update_post_meta( $id, 'wiw_hq_city', 'Lagos' );
		( new StateRepository() )->update(
			$id,
			array(
				'claimed'          => 1,
				'assigned_user_id' => $manager,
			)
		);
		return array( $id, $manager );
	}

	private function service(): RevisionService {
		$sink  = new class() implements LogSink {
			/** @param string $line Line. */
			public function write( string $line ): void {
				unset( $line );
			}
		};
		$store = new \ArrayObject();
		$jobs  = new JobRunner(
			$this->queue,
			null,
			array(
				'get' => static fn ( string $key, $fallback ) => $store[ $key ] ?? $fallback,
				'set' => static function ( string $key, $value ) use ( $store ): void {
					$store[ $key ] = $value;
				},
			)
		);
		return new RevisionService(
			null,
			function ( int $user, string $outcome, string $reason, int $profile ): void {
				$this->notified[] = array( $user, $outcome, $reason, $profile );
			},
			new PurgeQueue( $jobs, new Logger( $sink, new PiiCatalogue() ) )
		);
	}

	/**
	 * Pending revisions written during this test (ids of profiles are reused and the table is append-only).
	 *
	 * @param int $id Profile.
	 * @return array<int, array<string, mixed>>
	 */
	private function pending_now( int $id ): array {
		return array_values( array_filter( $this->service()->pending( $id ), fn ( $row ): bool => (int) $row['id'] > $this->floor ) );
	}

	private function propose( int $id, int $manager, string $city = 'Abuja' ): int {
		$result = ( new ProfileEditor() )->edit( $id, array( 'hq_city' => $city ), 'Moved the head office', $manager );
		$this->assertFalse( $result['applied'] );
		return $result['version_id'];
	}

	public function test_REQ_253_claimed_profile_edit_waits_for_approval(): void {
		list( $id, $manager ) = $this->claimed_profile();
		$this->propose( $id, $manager );
		$this->assertSame( 'Lagos', get_post_meta( $id, 'wiw_hq_city', true ), 'nothing public changes before approval' );
		$this->assertCount( 1, $this->pending_now( $id ) );
	}

	public function test_REQ_253_editor_cannot_approve_own_change(): void {
		list( $id, $manager ) = $this->claimed_profile();
		$version              = $this->propose( $id, $manager );
		// Give the author the review capability too: holding it is still not enough.
		get_user_by( 'id', $manager )->add_cap( 'wiw_review_records' );
		try {
			$this->service()->approve( $version, $manager );
			$this->fail( 'expected a refusal' );
		} catch ( StateException $e ) {
			$this->assertStringContainsString( 'cannot approve', $e->getMessage() );
		}
		$this->assertSame( 'Lagos', get_post_meta( $id, 'wiw_hq_city', true ) );
		$this->assertCount( 1, $this->pending_now( $id ) );
	}

	public function test_REQ_253_a_user_without_the_review_capability_cannot_decide(): void {
		list( $id, $manager ) = $this->claimed_profile();
		$version              = $this->propose( $id, $manager );
		$this->expectException( StateException::class );
		$this->service()->approve( $version, $this->user( 'subscriber' ) );
	}

	public function test_REQ_253_approval_publishes_revision_and_purges(): void {
		list( $id, $manager ) = $this->claimed_profile();
		$version              = $this->propose( $id, $manager );
		$reviewer             = $this->user( 'wiw_reviewer' );
		$this->queue->jobs    = array();
		$this->service()->approve( $version, $reviewer );

		$this->assertSame( 'Abuja', get_post_meta( $id, 'wiw_hq_city', true ) );
		$this->assertSame( array(), $this->pending_now( $id ), 'a decided revision leaves the queue' );
		$purges = array_filter( $this->queue->jobs, static fn ( array $j ): bool => PurgeQueue::JOB === $j['name'] );
		$this->assertCount( 1, $purges, 'a public page is queued for purge' );
		$this->assertSame( array( array( $manager, 'approved', '', $id ) ), $this->notified, 'the assigned user is told' );
	}

	public function test_REQ_253_a_decided_revision_cannot_be_decided_twice(): void {
		list( $id, $manager ) = $this->claimed_profile();
		$version              = $this->propose( $id, $manager );
		$reviewer             = $this->user( 'wiw_reviewer' );
		$this->service()->approve( $version, $reviewer );
		$this->expectException( StateException::class );
		$this->service()->reject( $version, 'Too late', $reviewer );
	}

	public function test_REQ_253_rejection_needs_a_reason_and_changes_nothing(): void {
		list( $id, $manager ) = $this->claimed_profile();
		$version              = $this->propose( $id, $manager );
		$reviewer             = $this->user( 'wiw_reviewer' );
		try {
			$this->service()->reject( $version, '  ', $reviewer );
			$this->fail( 'expected a refusal' );
		} catch ( StateException $e ) {
			$this->assertStringContainsString( 'reason', $e->getMessage() );
		}
		$this->service()->reject( $version, 'Not sourced', $reviewer );
		$this->assertSame( 'Lagos', get_post_meta( $id, 'wiw_hq_city', true ) );
		$this->assertSame( array( array( $manager, 'rejected', 'Not sourced', $id ) ), $this->notified );
	}

	public function test_REQ_125_previous_version_is_kept(): void {
		list( $id, $manager ) = $this->claimed_profile();
		$version              = $this->propose( $id, $manager );
		$this->service()->approve( $version, $this->user( 'wiw_reviewer' ) );
		$rows = array_values( array_filter( ( new VersionRepository() )->for_profile( $id ), fn ( $row ): bool => (int) $row['id'] > $this->floor ) );
		$this->assertCount( 2, $rows, 'the proposal and the decision both stay' );
		$decision = $rows[0];
		$proposal = $rows[1];
		$this->assertSame( 'pending', $proposal['approval_status'] );
		$this->assertSame( 'approved', $decision['approval_status'] );
		$this->assertSame( (string) $version, (string) $decision['revision_of'] );
		$this->assertSame( array( 'hq_city' => 'Lagos' ), json_decode( (string) $proposal['old_value'], true ), 'the old value is recorded' );
	}

	public function test_REQ_253_an_edit_on_an_unclaimed_profile_goes_live_at_once_with_a_reason(): void {
		list( $id ) = $this->claimed_profile();
		( new StateRepository() )->update(
			$id,
			array(
				'claimed'          => 0,
				'assigned_user_id' => null,
			)
		);
		$result = ( new ProfileEditor() )->edit( $id, array( 'hq_city' => 'Kano' ), 'Per the registry', $this->user( 'wiw_editor' ) );
		$this->assertTrue( $result['applied'] );
		$this->assertSame( 'Kano', get_post_meta( $id, 'wiw_hq_city', true ) );
	}
}
