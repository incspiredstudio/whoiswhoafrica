<?php
/**
 * Applies a bundle in resumable chunks (REQ-237, contract U3). The batch row holds a cursor: the number of record lines already
 * handled. A chunk reads on from there, validates each record, applies it and saves the cursor after every record, so a timeout
 * or a crash part-way resumes at the next unhandled line. The unique key on the results table means a line that was applied but
 * whose cursor was not saved is not recorded twice, and an unchanged record applies as UNCHANGED, so nothing is duplicated.
 * A refused record gets its code and the rest carry on.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Apply;

use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\BundleReader;
use WhoIsWho\Core\Import\Images\ImageLog;
use WhoIsWho\Core\Import\RecordValidator;
use WhoIsWho\Core\Import\StateAssigner;
use WhoIsWho\Core\Platform\Settings\SettingsService;

/**
 * Chunk applier.
 */
final class ChunkApplier {

	public const DEFAULT_CHUNK = 25;
	public const MAX_CHUNK     = 50;

	/**
	 * Batches.
	 *
	 * @var BatchRepository
	 */
	private BatchRepository $batches;

	/**
	 * Results.
	 *
	 * @var ResultRepository
	 */
	private ResultRepository $results;

	/**
	 * Record validator.
	 *
	 * @var RecordValidator
	 */
	private RecordValidator $validator;

	/**
	 * Record applier.
	 *
	 * @var ProfileApplier
	 */
	private ProfileApplier $applier;

	/**
	 * Constructor.
	 *
	 * @param ProfileApplier        $applier   Record applier.
	 * @param BatchRepository|null  $batches   Batches.
	 * @param ResultRepository|null $results   Results.
	 * @param RecordValidator|null  $validator Validator.
	 */
	public function __construct( ProfileApplier $applier, ?BatchRepository $batches = null, ?ResultRepository $results = null, ?RecordValidator $validator = null ) {
		$this->applier   = $applier;
		$this->batches   = $batches ?? new BatchRepository();
		$this->results   = $results ?? new ResultRepository();
		$this->validator = $validator ?? new RecordValidator();
	}

	/**
	 * A time budget for run(): true once the seconds are spent, or when the next record, if it is as slow as the slowest one so
	 * far, would run past them.
	 *
	 * @param int $seconds Budget.
	 * @return callable(float=): bool
	 */
	public static function budget( int $seconds ): callable {
		$started = microtime( true );
		return static fn ( float $next = 0.0 ): bool => microtime( true ) - $started + $next >= $seconds;
	}

	/**
	 * Apply the next chunk of a batch.
	 *
	 * @param BundleReader  $bundle   The opened bundle.
	 * @param string        $batch_id Batch id (registered).
	 * @param int           $size     Records in this chunk (clamped to 1 to 50).
	 * @param callable|null $stop     Returns true when the time budget is spent; checked before each record.
	 * @return array{done: bool, handled: int, counts: array<string, int>, busy: bool, finished?: bool} done is true when every
	 *         line has been handled; finished is true only for the call that completed the batch; busy is true when another
	 *         worker held the batch, so nothing was done.
	 * @throws \RuntimeException For an unknown batch.
	 */
	public function run( BundleReader $bundle, string $batch_id, int $size = self::DEFAULT_CHUNK, ?callable $stop = null ): array {
		$batch = $this->batches->get( $batch_id );
		if ( null === $batch ) {
			throw new \RuntimeException( 'Unknown batch.' );
		}
		if ( in_array( $batch['status'], array( 'applied', 'undone', 'refused' ), true ) ) {
			return $this->summary( $batch_id, true, 0 );
		}
		$previewed = json_decode( (string) $batch['settings_snapshot'], true );
		$previewed = is_array( $previewed ) && isset( $previewed['dry_run']['file_hash'] ) ? (string) $previewed['dry_run']['file_hash'] : (string) $batch['file_hash'];
		if ( $bundle->file_hash() !== (string) $batch['file_hash'] || $bundle->file_hash() !== $previewed ) {
			throw new \RuntimeException( 'The file changed since the dry run.' );
		}
		if ( ! BatchLock::acquire( $batch_id ) ) {
			return $this->summary( $batch_id, false, 0, true );
		}
		try {
			return $this->run_locked( $bundle, $batch, $batch_id, $size, $stop );
		} finally {
			BatchLock::release( $batch_id );
		}
	}

	/**
	 * Apply the next chunk while holding the batch lock.
	 *
	 * @param BundleReader         $bundle   The opened bundle.
	 * @param array<string, mixed> $batch    Batch row.
	 * @param string               $batch_id Batch id.
	 * @param int                  $size     Records in this chunk.
	 * @param callable|null        $stop     Time budget check.
	 * @return array{done: bool, handled: int, counts: array<string, int>, busy: bool, finished?: bool}
	 */
	private function run_locked( BundleReader $bundle, array $batch, string $batch_id, int $size, ?callable $stop ): array {
		$batch = $this->batches->get( $batch_id ) ?? $batch;
		if ( in_array( $batch['status'], array( 'applied', 'undone', 'refused' ), true ) ) {
			return $this->summary( $batch_id, true, 0 );
		}
		$size       = max( 1, min( self::MAX_CHUNK, $size ) );
		$cursor     = (int) $batch['progress_cursor'];
		$importer   = (int) $batch['created_by'];
		$snapshot   = json_decode( (string) $batch['settings_snapshot'], true );
		$import_all = is_array( $snapshot ) && array_key_exists( StateAssigner::SETTING, $snapshot )
			? (bool) $snapshot[ StateAssigner::SETTING ]
			: StateAssigner::import_all_is_on( SettingsService::for_site() );
		if ( 'applying' !== $batch['status'] ) {
			$this->batches->advance( $batch_id, $cursor, 'applying' );
		}
		$this->applier->set_bundle_dir( $bundle->dir() );

		$handled = 0;
		$total   = (int) $batch['record_count'];
		$slowest = 0.0;
		foreach ( $bundle->lines() as $n => $line ) {
			if ( $n <= $cursor ) {
				continue;
			}
			// The budget check is told how long the slowest record of this call took (records with images are slow), so a
			// budget made by self::budget() stops before a record that would run past it.
			if ( $handled >= $size || ( null !== $stop && $stop( $slowest ) ) ) {
				break;
			}
			$began = microtime( true );
			$this->handle_line( $n, $line, $batch_id, $importer, $import_all );
			$slowest = max( $slowest, microtime( true ) - $began );
			$cursor  = $n;
			$this->batches->advance( $batch_id, $cursor );
			++$handled;
		}
		$done = $cursor >= $total;
		if ( $done ) {
			$this->batches->advance( $batch_id, $cursor, 'applied' );
		}
		$out             = $this->summary( $batch_id, $done, $handled );
		$out['finished'] = $done;
		return $out;
	}

	/**
	 * Validate and apply one line, recording its result.
	 *
	 * @param int    $n           Line number.
	 * @param string $line        Record line.
	 * @param string $batch_id    Batch id.
	 * @param int    $importer    Importer.
	 * @param bool   $import_all  Setting.
	 */
	private function handle_line( int $n, string $line, string $batch_id, int $importer, bool $import_all ): void {
		$valid = $this->validator->validate( $line );
		if ( ! $valid['ok'] ) {
			$this->results->record( $batch_id, '' === $valid['record_id'] ? 'line:' . $n : (string) $valid['record_id'], (string) $valid['code'] );
			return;
		}
		$record = (array) $valid['record'];
		if ( $this->results->has( $batch_id, (string) $record['record_id'] ) ) {
			return;
		}
		$result = $this->applier->apply( $record, $batch_id, $importer, $import_all );
		$this->results->record( $batch_id, (string) $record['record_id'], $result['code'], $result['profile_id'] );
		if ( isset( $result['images'] ) ) {
			ImageLog::add( $batch_id, (string) $record['record_id'], $result['images'] );
		}
	}

	/**
	 * Progress summary.
	 *
	 * @param string $batch_id Batch.
	 * @param bool   $done     Done.
	 * @param int    $handled  Handled in this call.
	 * @param bool   $busy     Another worker holds the batch.
	 * @return array{done: bool, handled: int, counts: array<string, int>, busy: bool}
	 */
	private function summary( string $batch_id, bool $done, int $handled, bool $busy = false ): array {
		return array(
			'done'    => $done,
			'handled' => $handled,
			'counts'  => $this->results->counts( $batch_id ),
			'busy'    => $busy,
		);
	}
}
