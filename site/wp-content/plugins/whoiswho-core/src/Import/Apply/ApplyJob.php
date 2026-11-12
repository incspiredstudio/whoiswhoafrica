<?php
/**
 * The background job that applies a confirmed batch (REQ-237). Each run applies one chunk within a time budget and queues the
 * next run until the cursor reaches the end, so a host timeout only costs the current chunk. The folder of the bundle is kept in
 * the batch's settings snapshot (key bundle_dir). Finishing writes one audit row with the counts by code.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Apply;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\BundleReader;
use WhoIsWho\Core\Import\Result\ImportAudit;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Jobs\JobRunner;

/**
 * Apply job.
 */
final class ApplyJob {

	public const JOB = 'import_apply';

	/**
	 * Seconds one run may spend before it hands over to the next.
	 */
	public const BUDGET = 15;

	/**
	 * Job runner.
	 *
	 * @var JobRunner
	 */
	private JobRunner $jobs;

	/**
	 * Constructor.
	 *
	 * @param JobRunner|null $jobs Runner.
	 */
	public function __construct( ?JobRunner $jobs = null ) {
		$this->jobs = $jobs ?? JobRunner::for_site();
	}

	/**
	 * Register the job handler.
	 */
	public function register(): void {
		$this->jobs->handle( self::JOB, array( $this, 'run' ) );
	}

	/**
	 * Queue the application of a registered batch.
	 *
	 * @param string $batch_id Batch id.
	 */
	public function start( string $batch_id ): void {
		$this->jobs->enqueue( self::JOB, array( 'batch_id' => $batch_id ), true );
	}

	/**
	 * Run one chunk, then queue the next when records remain.
	 *
	 * @param array<string, mixed> $args Job arguments: batch_id.
	 * @throws \RuntimeException When the batch or its bundle cannot be found (the runner retries, then reports the failure).
	 */
	public function run( array $args ): void {
		$batch_id = (string) ( $args['batch_id'] ?? '' );
		$batches  = new BatchRepository();
		$batch    = $batches->get( $batch_id );
		if ( null === $batch ) {
			throw new \RuntimeException( 'Unknown batch.' );
		}
		$snapshot = json_decode( (string) $batch['settings_snapshot'], true );
		$dir      = is_array( $snapshot ) ? (string) ( $snapshot['bundle_dir'] ?? '' ) : '';
		$bundle   = BundleReader::open( $dir );
		$started  = time();
		$applier  = new ChunkApplier( new ProfileApplier( new Suppression( AuditLogger::for_site() ) ), $batches );
		$out      = $applier->run( $bundle, $batch_id, ChunkApplier::DEFAULT_CHUNK, static fn (): bool => time() - $started >= self::BUDGET );
		if ( ! $out['done'] ) {
			$this->jobs->enqueue( self::JOB, array( 'batch_id' => $batch_id ), true );
			return;
		}
		( new ImportAudit() )->record( $batch );
	}
}
