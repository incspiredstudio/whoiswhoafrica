<?php
/**
 * One facade for queued and scheduled jobs (purge, email, import chunks, reminders, retention). A job is a named handler that
 * receives an array of ids and cursors. A failing job retries with backoff up to a maximum, then is recorded as failed (ids only)
 * and shown to Administrators. A run stops at its time budget so shared hosting does not time out; the rest runs next time. Each
 * run updates a heartbeat the health check reads.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Jobs;

/**
 * Job runner.
 */
final class JobRunner {

	public const HOOK             = 'wiw_job';
	public const OPTION_HEARTBEAT = 'wiw_jobs_heartbeat';
	public const OPTION_FAILED    = 'wiw_jobs_failed';
	public const MAX_ATTEMPTS     = 4;
	public const DEFAULT_BUDGET   = 20;
	private const MAX_FAILED_KEPT = 50;

	/**
	 * The one runner modules register their handlers on.
	 *
	 * @var JobRunner|null
	 */
	private static ?JobRunner $shared = null;

	/**
	 * Queue.
	 *
	 * @var JobQueue
	 */
	private JobQueue $queue;

	/**
	 * Registered handlers by job name.
	 *
	 * @var array<string, callable>
	 */
	private array $handlers = array();

	/**
	 * Recurring jobs: name => interval in seconds.
	 *
	 * @var array<string, int>
	 */
	private array $recurring = array();

	/**
	 * Clock returning Unix time.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Storage for the heartbeat and failure list: get( key, default ) and set( key, value ).
	 *
	 * @var array{get: callable, set: callable}
	 */
	private array $store;

	/**
	 * Constructor.
	 *
	 * @param JobQueue                                 $queue Queue.
	 * @param callable|null                            $clock Returns Unix time.
	 * @param array{get: callable, set: callable}|null $store Option storage; WordPress options (autoload off) by default.
	 */
	public function __construct( JobQueue $queue, ?callable $clock = null, ?array $store = null ) {
		$this->queue = $queue;
		$this->clock = $clock ?? static fn (): int => time();
		$this->store = $store ?? array(
			'get' => static fn ( string $key, $fallback ) => get_option( $key, $fallback ),
			'set' => static function ( string $key, $value ): void {
				update_option( $key, $value, false );
			},
		);
	}

	/**
	 * Runner for this site, on Action Scheduler.
	 */
	public static function for_site(): self {
		if ( null === self::$shared ) {
			self::$shared = new self( new ActionSchedulerQueue() );
		}
		return self::$shared;
	}

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'execute' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'action_scheduler_init', array( $this, 'ensure_recurring' ) );
		add_filter(
			'action_scheduler_queue_runner_time_limit',
			static fn (): int => self::DEFAULT_BUDGET
		);
	}

	/**
	 * Register the handler for a job name.
	 *
	 * @param string   $name    Job name.
	 * @param callable $handler Receives the args array. Throw to signal failure.
	 */
	public function handle( string $name, callable $handler ): void {
		$this->handlers[ $name ] = $handler;
	}

	/**
	 * Register a recurring job: it re-queues itself after each run.
	 *
	 * @param string   $name     Job name.
	 * @param int      $interval Seconds between runs.
	 * @param callable $handler  Handler.
	 */
	public function recurring( string $name, int $interval, callable $handler ): void {
		$this->handlers[ $name ]  = $handler;
		$this->recurring[ $name ] = max( 60, $interval );
	}

	/**
	 * Queue the first run of every recurring job that is not already waiting.
	 */
	public function ensure_recurring(): void {
		foreach ( $this->recurring as $name => $interval ) {
			if ( ! $this->queue->has_pending( $name ) ) {
				$this->enqueue( $name, array(), false, 0 );
			}
		}
	}

	/**
	 * Queue a job.
	 *
	 * @param string               $name  Job name.
	 * @param array<string, mixed> $args  Arguments: ids and cursors only.
	 * @param bool                 $high  High-priority group (purge, email).
	 * @param int                  $delay Seconds to wait before it may run.
	 * @return int Queue id.
	 */
	public function enqueue( string $name, array $args = array(), bool $high = false, int $delay = 0 ): int {
		if ( ! isset( $args['_ref'] ) ) {
			$args['_ref'] = bin2hex( random_bytes( 6 ) );
		}
		return $this->queue->add( $name, $args, ( $this->clock )() + max( 0, $delay ), $high );
	}

	/**
	 * Run one job. Called by the queue. Never throws: a failure schedules a retry or is recorded.
	 *
	 * @param string               $name Job name.
	 * @param array<string, mixed> $args Arguments, with the internal _ref and _attempt.
	 */
	public function execute( string $name, array $args = array() ): void {
		$attempt = (int) ( $args['_attempt'] ?? 1 );
		if ( ! isset( $this->handlers[ $name ] ) ) {
			$this->failed( $name, $args, self::MAX_ATTEMPTS );
			return;
		}
		try {
			( $this->handlers[ $name ] )( $args );
		} catch ( \Throwable $e ) {
			$this->failed( $name, $args, $attempt );
			return;
		}
		if ( isset( $this->recurring[ $name ] ) ) {
			$this->enqueue( $name, array(), false, $this->recurring[ $name ] );
		}
	}

	/**
	 * Run due jobs until none are left or the time budget is used. Used by the command line and by cron.
	 *
	 * @param int $budget Seconds.
	 * @return array{ran: int, more: bool} How many jobs ran, and whether due jobs remain.
	 */
	public function run_due( int $budget = self::DEFAULT_BUDGET ): array {
		$start = ( $this->clock )();
		$ran   = 0;
		$this->store['set']( self::OPTION_HEARTBEAT, $start );
		while ( true ) {
			$due = $this->queue->due( ( $this->clock )(), 10 );
			if ( array() === $due ) {
				return array(
					'ran'  => $ran,
					'more' => false,
				);
			}
			foreach ( $due as $id ) {
				if ( ( $this->clock )() - $start >= $budget ) {
					return array(
						'ran'  => $ran,
						'more' => true,
					);
				}
				$this->queue->run( $id );
				++$ran;
			}
		}
	}

	/**
	 * Unix time of the last run, or 0.
	 */
	public function heartbeat(): int {
		return (int) $this->store['get']( self::OPTION_HEARTBEAT, 0 );
	}

	/**
	 * Failed jobs: name, reference, attempts, time. No arguments are kept.
	 *
	 * @return array<int, array{job: string, ref: string, attempts: int, at: int}>
	 */
	public function failed_jobs(): array {
		$list = $this->store['get']( self::OPTION_FAILED, array() );
		return is_array( $list ) ? array_values( $list ) : array();
	}

	/**
	 * Clear the failure list (an Administrator acknowledged it).
	 */
	public function clear_failed(): void {
		$this->store['set']( self::OPTION_FAILED, array() );
	}

	/**
	 * Admin notice for Administrators when jobs have failed.
	 */
	public function notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$count = count( $this->failed_jobs() );
		if ( 0 === $count ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of failed background jobs. */
					_n( '%d background job failed after retries.', '%d background jobs failed after retries.', $count, 'whoiswho' ),
					$count
				)
			)
		);
	}

	/**
	 * Retry with backoff, or record the failure.
	 *
	 * @param string               $name    Job name.
	 * @param array<string, mixed> $args    Arguments.
	 * @param int                  $attempt Attempt that just failed.
	 */
	private function failed( string $name, array $args, int $attempt ): void {
		if ( $attempt < self::MAX_ATTEMPTS ) {
			$args['_attempt'] = $attempt + 1;
			$this->enqueue( $name, $args, false, 60 * ( 4 ** ( $attempt - 1 ) ) );
			return;
		}
		$list   = $this->failed_jobs();
		$list[] = array(
			'job'      => $name,
			'ref'      => (string) ( $args['_ref'] ?? '' ),
			'attempts' => $attempt,
			'at'       => ( $this->clock )(),
		);
		$this->store['set']( self::OPTION_FAILED, array_slice( $list, -self::MAX_FAILED_KEPT ) );
		if ( function_exists( 'do_action' ) ) {
			do_action( 'wiw_job_failed', $name, (string) ( $args['_ref'] ?? '' ) );
		}
	}
}
