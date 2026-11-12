<?php
/**
 * Queues cache purges when a profile's state changes (REQ-209, REQ-217). A change that touches a public state queues one
 * high-priority job per profile. During a batch (bulk unpublish, import undo) the ids are collected and, above the threshold,
 * one broad purge is queued instead of many. A purge-log line is written per queued purge and per unpublish event. With no CDN
 * yet the job only announces the URLs on `wiw_purge_urls`; the local page cache listens there.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Purge;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Log\Logger;

/**
 * Purge queue.
 */
final class PurgeQueue {

	public const JOB       = 'cache.purge';
	public const THRESHOLD = 25;

	/**
	 * Job runner.
	 *
	 * @var JobRunner
	 */
	private JobRunner $jobs;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $log;

	/**
	 * Ids collected during a batch, or null when not batching.
	 *
	 * @var int[]|null
	 */
	private ?array $batch = null;

	/**
	 * Constructor.
	 *
	 * @param JobRunner|null $jobs Runner.
	 * @param Logger|null    $log  Logger.
	 */
	public function __construct( ?JobRunner $jobs = null, ?Logger $log = null ) {
		$this->jobs = $jobs ?? JobRunner::for_site();
		$this->log  = $log ?? Logger::for_site();
	}

	/**
	 * Queue for this site.
	 */
	public static function for_site(): self {
		static $shared = null;
		if ( null === $shared ) {
			$shared = new self();
		}
		return $shared;
	}

	/**
	 * Attach hooks and the job handler.
	 */
	public function register(): void {
		add_action( 'wiw_profile_state_changed', array( $this, 'on_state_changed' ), 10, 3 );
		$this->jobs->handle( self::JOB, array( $this, 'run' ) );
	}

	/**
	 * The state-changed listener: queue a purge when the change touches a public state.
	 *
	 * @param int         $profile_id Profile.
	 * @param string|null $from       Previous state.
	 * @param string      $to         New state.
	 */
	public function on_state_changed( int $profile_id, ?string $from, string $to ): void {
		$was_public = null !== $from && PubState::is_public( $from );
		if ( ! $was_public && ! PubState::is_public( $to ) ) {
			return;
		}
		if ( null !== $this->batch ) {
			$this->batch[] = $profile_id;
		} else {
			$this->queue( array( $profile_id ), $to );
		}
		if ( PubState::UNPUBLISHED === $to || PubState::REMOVED_SUPPRESSED === $to ) {
			$this->log->log(
				'info',
				'unpublish_event',
				array(
					'event' => $to,
					'id'    => $profile_id,
				)
			);
		}
	}

	/**
	 * Start collecting ids instead of queueing one job per profile.
	 */
	public function begin_batch(): void {
		$this->batch = array();
	}

	/**
	 * Stop collecting and queue the purge: per-profile jobs up to the threshold, one broad purge above it.
	 *
	 * @return int Number of jobs queued.
	 */
	public function end_batch(): int {
		$ids         = array_values( array_unique( $this->batch ?? array() ) );
		$this->batch = null;
		if ( array() === $ids ) {
			return 0;
		}
		return $this->queue( $ids, 'batch' );
	}

	/**
	 * Queue purge jobs for profile ids. Above the threshold one broad purge is queued.
	 *
	 * @param int[]  $ids    Profile ids.
	 * @param string $reason Event name for the log.
	 * @return int Jobs queued.
	 */
	public function queue( array $ids, string $reason ): int {
		/**
		 * Number of profiles above which one broad purge replaces per-profile purges.
		 *
		 * @param int $threshold Threshold.
		 */
		$threshold = max( 1, (int) apply_filters( 'wiw_purge_threshold', self::THRESHOLD ) );
		if ( count( $ids ) > $threshold ) {
			$this->jobs->enqueue(
				self::JOB,
				array(
					'broad' => true,
					'count' => count( $ids ),
				),
				true
			);
			$this->log->log(
				'info',
				'purge_queued',
				array(
					'event' => 'broad',
					'count' => count( $ids ),
				)
			);
			return 1;
		}
		foreach ( $ids as $id ) {
			$this->jobs->enqueue( self::JOB, array( 'profile_id' => $id ), true );
			$this->log->log(
				'info',
				'purge_queued',
				array(
					'event' => $reason,
					'id'    => $id,
				)
			);
		}
		return count( $ids );
	}

	/**
	 * The job: announce the URLs to purge. A broad purge announces an empty list with the broad flag.
	 *
	 * @param array<string, mixed> $args Job arguments: profile_id, or broad.
	 */
	public function run( array $args ): void {
		$broad = ! empty( $args['broad'] );
		$urls  = $broad ? array() : ( new UrlSet() )->for_profile( (int) ( $args['profile_id'] ?? 0 ) );
		/**
		 * Purge these URLs from the page cache (and the CDN once there is one). A broad purge clears everything.
		 *
		 * @param string[] $urls  URLs; empty for a broad purge.
		 * @param bool     $broad Whether to clear everything.
		 */
		do_action( 'wiw_purge_urls', $urls, $broad );
		$this->log->log(
			'info',
			'purge_run',
			array(
				'event' => $broad ? 'broad' : 'profile',
				'count' => count( $urls ),
			)
		);
	}
}
