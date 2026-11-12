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
	 * Apply the next chunk of a batch.
	 *
	 * @param BundleReader  $bundle   The opened bundle.
	 * @param string        $batch_id Batch id (registered).
	 * @param int           $size     Records in this chunk (clamped to 1 to 50).
	 * @param callable|null $stop     Returns true when the time budget is spent; checked before each record.
	 * @return array{done: bool, handled: int, counts: array<string, int>} done is true when every line has been handled.
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

		$handled = 0;
		$total   = (int) $batch['record_count'];
		foreach ( $bundle->lines() as $n => $line ) {
			if ( $n <= $cursor ) {
				continue;
			}
			if ( $handled >= $size || ( null !== $stop && $stop() ) ) {
				break;
			}
			$this->handle_line( $n, $line, $batch_id, $importer, $import_all );
			$cursor = $n;
			$this->batches->advance( $batch_id, $cursor );
			++$handled;
		}
		$done = $cursor >= $total;
		if ( $done ) {
			$this->batches->advance( $batch_id, $cursor, 'applied' );
		}
		return $this->summary( $batch_id, $done, $handled );
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
	}

	/**
	 * Progress summary.
	 *
	 * @param string $batch_id Batch.
	 * @param bool   $done     Done.
	 * @param int    $handled  Handled in this call.
	 * @return array{done: bool, handled: int, counts: array<string, int>}
	 */
	private function summary( string $batch_id, bool $done, int $handled ): array {
		return array(
			'done'    => $done,
			'handled' => $handled,
			'counts'  => $this->results->counts( $batch_id ),
		);
	}
}
