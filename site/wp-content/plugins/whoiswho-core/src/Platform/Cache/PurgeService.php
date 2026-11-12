<?php
/**
 * The purge hook that unpublish flows call (REQ-209, ADR-006). Firing wiw_content_unpublished( subject_type, subject_id, urls )
 * queues a high-priority job; the job runs after the unpublish transaction has committed, asks the client to purge the URLs
 * and logs the attempt. A failing client logs a failure and the job retries with backoff. The caller supplies the URL set.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cache;

use WhoIsWho\Core\Platform\Jobs\JobRunner;

/**
 * Purge service.
 */
final class PurgeService {

	public const HOOK = 'wiw_content_unpublished';
	public const JOB  = 'cache.edge_purge';

	/**
	 * Runner.
	 *
	 * @var JobRunner
	 */
	private JobRunner $runner;

	/**
	 * Client.
	 *
	 * @var PurgeClient
	 */
	private PurgeClient $client;

	/**
	 * Log.
	 *
	 * @var PurgeLog
	 */
	private PurgeLog $log;

	/**
	 * Constructor.
	 *
	 * @param JobRunner   $runner Job runner.
	 * @param PurgeClient $client Edge client.
	 * @param PurgeLog    $log    Purge log.
	 */
	public function __construct( JobRunner $runner, PurgeClient $client, PurgeLog $log ) {
		$this->runner = $runner;
		$this->client = $client;
		$this->log    = $log;
	}

	/**
	 * Service for this site.
	 */
	public static function for_site(): self {
		$client = apply_filters( 'wiw_purge_client', new NullClient() );
		return new self( JobRunner::for_site(), $client instanceof PurgeClient ? $client : new NullClient(), new WpdbPurgeLog() );
	}

	/**
	 * Attach the hook and the job handler.
	 */
	public function register(): void {
		$this->register_handler();
		add_action( self::HOOK, array( $this, 'on_unpublished' ), 10, 4 );
	}

	/**
	 * Register the job handler.
	 */
	public function register_handler(): void {
		$this->runner->handle( self::JOB, array( $this, 'run' ) );
	}

	/**
	 * Queue a high-priority purge.
	 *
	 * @param string   $subject_type Subject type.
	 * @param int      $subject_id   Subject id.
	 * @param string[] $urls         URLs to purge, supplied by the caller.
	 * @param string   $reason       Short reason (unpublished, removed, declined), never personal data.
	 */
	public function on_unpublished( string $subject_type, int $subject_id, array $urls, string $reason = '' ): void {
		$this->runner->enqueue(
			self::JOB,
			array(
				'subject_type' => $subject_type,
				'subject_id'   => $subject_id,
				'urls'         => self::clean( $urls ),
				'reason'       => substr( $reason, 0, 120 ),
			),
			true
		);
	}

	/**
	 * The job: purge and log.
	 *
	 * @param array<string, mixed> $args Job arguments.
	 * @throws \RuntimeException When the client fails, so the job retries.
	 */
	public function run( array $args ): void {
		$type = (string) ( $args['subject_type'] ?? '' );
		$id   = (int) ( $args['subject_id'] ?? 0 );
		$urls = self::clean( (array) ( $args['urls'] ?? array() ) );
		$why  = (string) ( $args['reason'] ?? '' );
		try {
			$result = $this->client->purge( $urls );
		} catch ( \Throwable $failure ) {
			unset( $failure );
			$this->log->add( $type, $id, count( $urls ), 'failed', $why );
			throw new \RuntimeException( 'Cache purge failed.' );
		}
		$this->log->add( $type, $id, count( $urls ), 'ok' === $result ? 'ok' : 'skipped', $why );
	}

	/**
	 * Strings only, no blanks, no repeats, order kept.
	 *
	 * @param array<int|string, mixed> $urls URLs.
	 * @return string[]
	 */
	private static function clean( array $urls ): array {
		$out = array();
		foreach ( $urls as $url ) {
			if ( is_string( $url ) && '' !== $url && ! in_array( $url, $out, true ) ) {
				$out[] = $url;
			}
		}
		return $out;
	}
}
