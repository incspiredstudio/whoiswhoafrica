<?php
/**
 * Bulk unpublish and its restore (REQ-193, REQ-209, REQ-244). A staff member with wiw_bulk_unpublish gives a filter, a reason and
 * the match count they confirmed; the service records one batch row and one audit row, then chunked jobs move each published match
 * to Unpublished through the state machine (410, noindex, out of search and the sitemap, one audit row per profile, a purge
 * queued). Restore reverses one batch, and only the profiles still unpublished by that batch.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Bulk;

use WhoIsWho\Core\Directory\Purge\PurgeQueue;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Review\StateException;
use WhoIsWho\Core\Review\StateMachine;

/**
 * Bulk unpublish service.
 */
final class BulkUnpublish {

	public const JOB_UNPUBLISH = 'bulk.unpublish';
	public const JOB_RESTORE   = 'bulk.restore';
	public const CHUNK         = 200;
	public const CAPABILITY    = 'wiw_bulk_unpublish';
	public const BATCH_META    = '_wiw_bulk_batch';

	/**
	 * Depth of bulk-job transitions in progress; the state listener leaves the batch tag alone while it is above zero.
	 *
	 * @var int
	 */
	private static int $active = 0;

	/**
	 * Batches.
	 *
	 * @var BulkRepository
	 */
	private BulkRepository $batches;

	/**
	 * Filter.
	 *
	 * @var BulkFilter
	 */
	private BulkFilter $filter;

	/**
	 * Jobs.
	 *
	 * @var JobRunner
	 */
	private JobRunner $jobs;

	/**
	 * Purge queue.
	 *
	 * @var PurgeQueue
	 */
	private PurgeQueue $purge;

	/**
	 * Chunk size.
	 *
	 * @var int
	 */
	private int $chunk;

	/**
	 * Constructor.
	 *
	 * @param JobRunner|null      $jobs    Job runner.
	 * @param PurgeQueue|null     $purge   Purge queue.
	 * @param BulkRepository|null $batches Batches.
	 * @param int                 $chunk   Profiles per job.
	 */
	public function __construct( ?JobRunner $jobs = null, ?PurgeQueue $purge = null, ?BulkRepository $batches = null, int $chunk = self::CHUNK ) {
		$this->jobs    = $jobs ?? JobRunner::for_site();
		$this->purge   = $purge ?? PurgeQueue::for_site();
		$this->batches = $batches ?? new BulkRepository();
		$this->filter  = new BulkFilter();
		$this->chunk   = max( 1, $chunk );
	}

	/**
	 * Service for this site.
	 */
	public static function for_site(): self {
		static $shared = null;
		if ( null === $shared ) {
			$shared = new self();
		}
		return $shared;
	}

	/**
	 * Register the job handlers.
	 */
	public function register(): void {
		add_action( 'wiw_profile_state_changed', array( $this, 'on_state_changed' ), 5 );
		$this->jobs->handle(
			self::JOB_UNPUBLISH,
			function ( array $args ): void {
				$ref = (string) ( $args['batch'] ?? '' );
				$out = $this->run_unpublish_chunk( $ref, (int) ( $args['after'] ?? 0 ) );
				if ( $out['more'] ) {
					$this->jobs->enqueue(
						self::JOB_UNPUBLISH,
						array(
							'batch' => $ref,
							'after' => $out['after'],
						)
					);
				}
			}
		);
		$this->jobs->handle(
			self::JOB_RESTORE,
			function ( array $args ): void {
				$ref   = (string) ( $args['batch'] ?? '' );
				$actor = (int) ( $args['actor'] ?? 0 );
				$out   = $this->run_restore_chunk( $ref, (int) ( $args['after'] ?? 0 ), $actor );
				if ( $out['more'] ) {
					$this->jobs->enqueue(
						self::JOB_RESTORE,
						array(
							'batch' => $ref,
							'after' => $out['after'],
							'actor' => $actor,
						)
					);
				}
			}
		);
	}

	/**
	 * How many published profiles match.
	 *
	 * @param array<string, mixed> $raw_filter Filter.
	 */
	public function count( array $raw_filter ): int {
		return $this->filter->count( BulkFilter::clean( $raw_filter ) );
	}

	/**
	 * Names of the first matches, for the confirmation.
	 *
	 * @param array<string, mixed> $raw_filter Filter.
	 * @param int                  $limit      How many.
	 * @return string[]
	 */
	public function first_names( array $raw_filter, int $limit = 5 ): array {
		return $this->filter->first_names( BulkFilter::clean( $raw_filter ), $limit );
	}

	/**
	 * Start a bulk unpublish.
	 *
	 * @param array<string, mixed> $raw_filter Filter.
	 * @param string               $reason     Why; required.
	 * @param int                  $confirmed  The count the person saw and confirmed; must equal the current count.
	 * @param int                  $actor_id   Acting user; needs wiw_bulk_unpublish.
	 * @return string The batch reference.
	 * @throws StateException When refused.
	 */
	public function start( array $raw_filter, string $reason, int $confirmed, int $actor_id ): string {
		$this->require_capability( $actor_id );
		if ( '' === trim( $reason ) ) {
			throw StateException::refuse( 'A reason is required.' );
		}
		$filter = BulkFilter::clean( $raw_filter );
		$count  = $this->filter->count( $filter );
		if ( 0 === $count ) {
			throw StateException::refuse( 'Nothing matches the filter.' );
		}
		if ( $count !== $confirmed ) {
			throw StateException::refuse( 'The number of matches changed; confirm the new count.' );
		}
		$ref = bin2hex( random_bytes( 8 ) );
		$this->batches->create( $ref, $filter, $reason, $count, $actor_id );
		$this->audit_for( $actor_id )->log(
			'bulk_unpublish.batch',
			array(
				'type' => 'batch',
				'id'   => $ref,
			),
			null,
			array(
				'count'  => $count,
				'filter' => $filter,
			),
			$reason,
			$ref
		);
		$this->jobs->enqueue(
			self::JOB_UNPUBLISH,
			array(
				'batch' => $ref,
				'after' => 0,
			)
		);
		return $ref;
	}

	/**
	 * Move one chunk of matches to Unpublished.
	 *
	 * @param string $ref   Batch reference.
	 * @param int    $after Last profile id handled.
	 * @return array{after: int, more: bool}
	 */
	public function run_unpublish_chunk( string $ref, int $after ): array {
		$row = $this->batches->get( $ref );
		if ( null === $row || BulkRepository::RESTORED === $row['status'] ) {
			return array(
				'after' => $after,
				'more'  => false,
			);
		}
		$filter  = json_decode( (string) $row['filter_json'], true );
		$filter  = is_array( $filter ) ? BulkFilter::clean( $filter ) : array();
		$ids     = $this->filter->ids( $filter, $after, $this->chunk );
		$machine = $this->machine_for( (int) $row['actor_id'] );
		$done    = (int) $row['done_count'];

		$this->purge->begin_batch();
		try {
			foreach ( $ids as $id ) {
				$after = $id;
				++self::$active;
				try {
					$machine->transition( $id, PubState::UNPUBLISHED, (string) $row['reason'], null, $ref );
					update_post_meta( $id, self::BATCH_META, $ref );
					++$done;
				} catch ( StateException $e ) {
					continue;
				} finally {
					--self::$active;
				}
			}
		} finally {
			$collected = $this->purge->collect_batch();
		}

		$total = (int) $row['match_count'];
		$last  = count( $ids ) < $this->chunk;
		if ( $total <= PurgeQueue::threshold() ) {
			$this->purge->queue( $collected, 'bulk' );
		} elseif ( $last ) {
			$this->purge->queue_broad( $total );
		}
		$values = array(
			'done_count' => $done,
			'status'     => $last ? BulkRepository::DONE : BulkRepository::RUNNING,
		);
		if ( $last ) {
			$values['completed_at'] = gmdate( 'Y-m-d H:i:s' );
		}
		$this->batches->update( $ref, $values );
		return array(
			'after' => $after,
			'more'  => ! $last,
		);
	}

	/**
	 * Start the restore of one batch.
	 *
	 * @param string $ref      Batch reference.
	 * @param int    $actor_id Acting user; needs wiw_bulk_unpublish.
	 * @throws StateException When refused.
	 */
	public function restore( string $ref, int $actor_id ): void {
		$this->require_capability( $actor_id );
		$row = $this->batches->get( $ref );
		if ( null === $row ) {
			throw StateException::refuse( 'Unknown batch.' );
		}
		if ( ! in_array( $row['status'], array( BulkRepository::DONE, BulkRepository::RUNNING ), true ) ) {
			throw StateException::refuse( 'This batch cannot be restored.' );
		}
		$this->audit_for( $actor_id )->log(
			'bulk_unpublish.restore',
			array(
				'type' => 'batch',
				'id'   => $ref,
			),
			null,
			null,
			'Restore of bulk batch ' . $ref,
			self::restore_ref( $ref )
		);
		$this->batches->update( $ref, array( 'restored_count' => 0 ) );
		$this->jobs->enqueue(
			self::JOB_RESTORE,
			array(
				'batch' => $ref,
				'after' => 0,
				'actor' => $actor_id,
			)
		);
	}

	/**
	 * Restore one chunk: profiles still unpublished by this batch go back to the state they had.
	 *
	 * @param string $ref   Batch reference.
	 * @param int    $after Last profile id handled.
	 * @param int    $actor Who restores; the batch's own actor when 0.
	 * @return array{after: int, more: bool}
	 */
	public function run_restore_chunk( string $ref, int $after, int $actor = 0 ): array {
		global $wpdb;
		$row = $this->batches->get( $ref );
		if ( null === $row ) {
			return array(
				'after' => $after,
				'more'  => false,
			);
		}
		// Profiles still tagged with this batch: the tag is removed by any other state change.
		$ids     = array_map(
			'intval',
			(array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s AND post_id > %d ORDER BY post_id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- core table name.
					self::BATCH_META,
					$ref,
					$after,
					$this->chunk
				)
			)
		);
		$machine = $this->machine_for( $actor > 0 ? $actor : (int) $row['actor_id'] );
		$states  = new StateRepository();
		$count   = (int) $row['restored_count'];
		$this->purge->begin_batch();
		try {
			foreach ( $ids as $id ) {
				$after = $id;
				$state = $states->get( $id );
				if ( null === $state || PubState::UNPUBLISHED !== $states->pub_state( $id ) ) {
					delete_post_meta( $id, self::BATCH_META );
					continue;
				}
				$to = ! empty( $state['reviewed_at'] ) && ! empty( $state['reviewer_id'] ) ? PubState::PUBLISHED_REVIEWED : PubState::PUBLISHED_PENDING;
				++self::$active;
				try {
					$machine->transition( $id, $to, 'Restore of bulk batch ' . $ref, null, self::restore_ref( $ref ) );
					delete_post_meta( $id, self::BATCH_META );
					++$count;
				} catch ( StateException $e ) {
					continue;
				} finally {
					--self::$active;
				}
			}
		} finally {
			$collected = $this->purge->collect_batch();
		}//end try
		$total = (int) $row['match_count'];
		$last  = count( $ids ) < $this->chunk;
		if ( $total <= PurgeQueue::threshold() ) {
			$this->purge->queue( $collected, 'restore' );
		} elseif ( $last ) {
			$this->purge->queue_broad( $total );
		}
		$values = array( 'restored_count' => $count );
		if ( $last ) {
			$values['status']      = BulkRepository::RESTORED;
			$values['restored_at'] = gmdate( 'Y-m-d H:i:s' );
		}
		$this->batches->update( $ref, $values );
		return array(
			'after' => $after,
			'more'  => ! $last,
		);
	}

	/**
	 * The batch reference restore rows carry.
	 *
	 * @param string $ref Batch reference.
	 */
	public static function restore_ref( string $ref ): string {
		return 'r-' . $ref;
	}

	/**
	 * A state change made by anything other than a bulk job ends the profile's membership of its batch, so a restore leaves it
	 * alone.
	 *
	 * @param int $profile_id Profile.
	 */
	public function on_state_changed( int $profile_id ): void {
		if ( 0 === self::$active ) {
			delete_post_meta( $profile_id, self::BATCH_META );
		}
	}

	/**
	 * A state machine whose audit rows name the acting user, even when a job runs it.
	 *
	 * @param int $actor_id Actor.
	 */
	private function machine_for( int $actor_id ): StateMachine {
		return new StateMachine( null, $this->audit_for( $actor_id ) );
	}

	/**
	 * An audit logger that records the given actor.
	 *
	 * @param int $actor_id Actor.
	 */
	private function audit_for( int $actor_id ): AuditLogger {
		return new AuditLogger( new WpdbConnection(), null, static fn (): int => $actor_id );
	}

	/**
	 * Refuse a user without the bulk capability.
	 *
	 * @param int $actor_id Actor.
	 * @throws StateException When missing.
	 */
	private function require_capability( int $actor_id ): void {
		if ( ! user_can( $actor_id, self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw StateException::refuse( 'Not allowed.' );
		}
	}
}
