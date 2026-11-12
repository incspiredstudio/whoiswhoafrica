<?php
/**
 * Bulk unpublish and restore (REQ-193, REQ-209, REQ-244). Needs the WordPress test suite. Plain TestCase: real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Review;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Purge\PurgeQueue;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Log\LogSink;
use WhoIsWho\Core\Platform\Log\Logger;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Core\Review\Bulk\BulkRepository;
use WhoIsWho\Core\Review\Bulk\BulkUnpublish;
use WhoIsWho\Core\Review\ReviewService;
use WhoIsWho\Core\Review\StateException;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Core\Review\StatePolicy;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class BulkUnpublishTest extends TestCase {

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

	private MemoryQueue $queue;

	private BulkUnpublish $bulk;

	private int $seq = 0;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
		$this->queue = new MemoryQueue();
		$store       = new \ArrayObject();
		$runner      = new JobRunner(
			$this->queue,
			null,
			array(
				'get' => static fn ( string $key, $fallback ) => $store[ $key ] ?? $fallback,
				'set' => static function ( string $key, $value ) use ( $store ): void {
					$store[ $key ] = $value;
				},
			)
		);
		$sink        = new class() implements LogSink {
			/** @param string $line Line. */
			public function write( string $line ): void {
				unset( $line );
			}
		};
		$purge       = new PurgeQueue( $runner, new Logger( $sink, new PiiCatalogue() ) );
		$purge->register();
		$this->bulk = new BulkUnpublish( $runner, $purge, new BulkRepository(), 2 );
		$this->bulk->register();
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
		remove_all_actions( 'wiw_profile_state_changed' );
		remove_all_filters( 'wiw_purge_threshold' );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'user_email' => wp_generate_password( 8, false ) . '@example.test',
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	private function profile( string $source, string $state = PubState::PUBLISHED_PENDING ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => 'Bulk test ' . ( ++$this->seq ),
				'post_status' => 'draft',
			)
		);
		$this->assertIsInt( $id );
		$this->posts[] = $id;
		$enter         = in_array( $state, StateMachine::INITIAL, true ) ? $state : PubState::IMPORTED_NOT_PUBLIC;
		( new StateMachine() )->enter(
			$id,
			$enter,
			array(
				'entity_type' => 'organisation',
				'source'      => $source,
			)
		);
		if ( $enter !== $state ) {
			( new StateMachine() )->transition( $id, $state, 'Setup' );
		}
		return $id;
	}

	private function state( int $id ): ?string {
		return ( new StateRepository() )->pub_state( $id );
	}

	/**
	 * Run every chunk of a batch.
	 *
	 * @param string $ref Batch.
	 */
	private function run_unpublish( string $ref ): void {
		$after = 0;
		do {
			$out   = $this->bulk->run_unpublish_chunk( $ref, $after );
			$after = $out['after'];
		} while ( $out['more'] );
	}

	private function run_restore( string $ref ): void {
		$after = 0;
		do {
			$out   = $this->bulk->run_restore_chunk( $ref, $after );
			$after = $out['after'];
		} while ( $out['more'] );
	}

	public function test_REQ_209_bulk_unpublish_returns_410_with_noindex(): void {
		$reviewer = $this->user( 'wiw_reviewer' );
		$src      = 'src-' . wp_generate_password( 6, false );
		$ids      = array( $this->profile( $src ), $this->profile( $src ), $this->profile( $src ) );
		$ref      = $this->bulk->start( array( 'source' => $src ), 'Source withdrawn', 3, $reviewer );
		$this->run_unpublish( $ref );
		foreach ( $ids as $id ) {
			$policy = StatePolicy::for_state( $this->state( $id ) );
			$this->assertSame( 410, $policy->status );
			$this->assertTrue( $policy->noindex );
			$this->assertSame( ProfileType::STATUS_HIDDEN, get_post_status( $id ) );
		}
		$row = ( new BulkRepository() )->get( $ref );
		$this->assertSame( BulkRepository::DONE, $row['status'] );
		$this->assertSame( '3', (string) $row['done_count'] );
	}

	public function test_REQ_193_bulk_unpublish_writes_one_audit_row_per_profile(): void {
		global $wpdb;
		$reviewer = $this->user( 'wiw_reviewer' );
		$src      = 'src-' . wp_generate_password( 6, false );
		$ids      = array( $this->profile( $src ), $this->profile( $src ), $this->profile( $src ) );
		$ref      = $this->bulk->start( array( 'source' => $src ), 'Source withdrawn', 3, $reviewer );
		$this->run_unpublish( $ref );
		$table = $wpdb->prefix . 'wiw_audit_log';
		$rows  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE batch_ref = %s", $ref ) ); // phpcs:ignore WordPress.DB
		$this->assertSame( count( $ids ) + 1, $rows, 'One row per profile and one for the batch.' );
		$actor = (int) $wpdb->get_var( $wpdb->prepare( "SELECT actor_id FROM {$table} WHERE batch_ref = %s AND action = 'profile.state_change' LIMIT 1", $ref ) ); // phpcs:ignore WordPress.DB
		$this->assertSame( $reviewer, $actor, 'Job rows name the person who started the batch.' );
	}

	public function test_REQ_193_restore_batch_only_restores_profiles_unpublished_by_it(): void {
		$reviewer = $this->user( 'wiw_reviewer' );
		$editor   = $this->user( 'wiw_editor' );
		$src      = 'src-' . wp_generate_password( 6, false );
		$a        = $this->profile( $src );
		$b        = $this->profile( $src );
		$c        = $this->profile( $src );
		$ref      = $this->bulk->start( array( 'source' => $src ), 'Source withdrawn', 3, $reviewer );
		$this->run_unpublish( $ref );
		// B is restored by hand and unpublished again for another reason, so it is no longer unpublished by this batch.
		( new ReviewService() )->restore( $b, 'Wrong', $editor );
		( new ReviewService() )->unpublish( $b, 'Separate dispute', $editor );

		$this->bulk->restore( $ref, $reviewer );
		$this->run_restore( $ref );

		$this->assertSame( PubState::PUBLISHED_PENDING, $this->state( $a ) );
		$this->assertSame( PubState::PUBLISHED_PENDING, $this->state( $c ) );
		$this->assertSame( 200, StatePolicy::for_state( $this->state( $a ) )->status );
		$this->assertSame( PubState::UNPUBLISHED, $this->state( $b ), 'A profile unpublished separately stays unpublished.' );
		$row = ( new BulkRepository() )->get( $ref );
		$this->assertSame( BulkRepository::RESTORED, $row['status'] );
		$this->assertSame( '2', (string) $row['restored_count'] );
	}

	public function test_REQ_193_restore_leaves_a_profile_unpublished_by_another_batch(): void {
		$reviewer = $this->user( 'wiw_reviewer' );
		$src      = 'src-' . wp_generate_password( 6, false );
		$a        = $this->profile( $src );
		$first    = $this->bulk->start( array( 'source' => $src ), 'First', 1, $reviewer );
		$this->run_unpublish( $first );
		$this->bulk->restore( $first, $reviewer );
		$this->run_restore( $first );
		$second = $this->bulk->start( array( 'source' => $src ), 'Second', 1, $reviewer );
		$this->run_unpublish( $second );
		$this->assertSame( PubState::UNPUBLISHED, $this->state( $a ) );
		$this->expectException( StateException::class );
		$this->bulk->restore( $first, $reviewer );
	}

	public function test_REQ_193_bulk_unpublish_includes_published_profiles_only(): void {
		$reviewer = $this->user( 'wiw_reviewer' );
		$src      = 'src-' . wp_generate_password( 6, false );
		$live     = $this->profile( $src );
		$hidden   = $this->profile( $src, PubState::IMPORTED_NOT_PUBLIC );
		$rejected = $this->profile( $src, PubState::REJECTED );
		$this->assertSame( 1, $this->bulk->count( array( 'source' => $src ) ) );
		$ref = $this->bulk->start( array( 'source' => $src ), 'Clean-up', 1, $reviewer );
		$this->run_unpublish( $ref );
		$this->assertSame( PubState::UNPUBLISHED, $this->state( $live ) );
		$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, $this->state( $hidden ) );
		$this->assertSame( PubState::REJECTED, $this->state( $rejected ) );
	}

	public function test_REQ_193_editor_cannot_bulk_unpublish_without_capability(): void {
		$editor = $this->user( 'wiw_editor' );
		$src    = 'src-' . wp_generate_password( 6, false );
		$id     = $this->profile( $src );
		try {
			$this->bulk->start( array( 'source' => $src ), 'Nope', 1, $editor );
			$this->fail( 'An Editor without the capability is refused.' );
		} catch ( StateException $e ) {
			$this->assertSame( PubState::PUBLISHED_PENDING, $this->state( $id ) );
		}
	}

	public function test_REQ_193_needs_a_reason_and_the_confirmed_count(): void {
		$reviewer = $this->user( 'wiw_reviewer' );
		$src      = 'src-' . wp_generate_password( 6, false );
		$this->profile( $src );
		$this->profile( $src );
		try {
			$this->bulk->start( array( 'source' => $src ), ' ', 2, $reviewer );
			$this->fail( 'A reason is required.' );
		} catch ( StateException $e ) {
			$this->assertSame( 2, $this->bulk->count( array( 'source' => $src ) ) );
		}
		$this->expectException( StateException::class );
		$this->bulk->start( array( 'source' => $src ), 'Reason', 5, $reviewer );
	}

	public function test_REQ_193_name_filter_and_first_names_for_confirmation(): void {
		$src = 'src-' . wp_generate_password( 6, false );
		$a   = $this->profile( $src );
		$this->profile( $src );
		$this->assertSame( 1, $this->bulk->count( array( 'name' => get_the_title( $a ) ) ) );
		$this->assertSame( array( get_the_title( $a ) ), $this->bulk->first_names( array( 'name' => get_the_title( $a ) ) ) );
	}

	public function test_REQ_209_bulk_above_threshold_queues_one_broad_purge_across_chunks(): void {
		add_filter( 'wiw_purge_threshold', static fn (): int => 2 );
		$reviewer = $this->user( 'wiw_reviewer' );
		$src      = 'src-' . wp_generate_password( 6, false );
		for ( $i = 0; $i < 5; $i++ ) {
			$this->profile( $src );
		}
		$this->queue->jobs = array();
		$ref               = $this->bulk->start( array( 'source' => $src ), 'Big clean-up', 5, $reviewer );
		$this->queue->jobs = array_filter( $this->queue->jobs, static fn ( array $j ): bool => PurgeQueue::JOB === $j['name'] );
		$this->run_unpublish( $ref );
		$purges = array_values( array_filter( $this->queue->jobs, static fn ( array $j ): bool => PurgeQueue::JOB === $j['name'] ) );
		$this->assertCount( 1, $purges );
		$this->assertTrue( $purges[0]['args']['broad'] );
	}
}
