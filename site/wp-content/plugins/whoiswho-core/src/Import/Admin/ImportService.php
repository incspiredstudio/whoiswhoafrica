<?php
/**
 * What the Import screen does, kept out of the screen (REQ-237, REQ-092, REQ-244): stage an upload (store it privately, check the
 * manifest, register the batch, run the dry run), confirm a previewed batch (queue the chunked apply job, or resume it) and undo a
 * batch. The batch's settings snapshot holds bundle_dir (where the bundle is stored) and the import-everything-as-not-public
 * setting as it was when the batch was registered. Every method checks wiw_run_imports for the actor.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Admin;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Import\Apply\ApplyJob;
use WhoIsWho\Core\Import\Apply\ChunkApplier;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\BundleReader;
use WhoIsWho\Core\Import\DryRun\DryRun;
use WhoIsWho\Core\Import\StateAssigner;
use WhoIsWho\Core\Import\Undo\BatchUndo;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Review\StateException;

/**
 * Import service.
 */
final class ImportService {

	public const CAPABILITY = 'wiw_run_imports';

	/**
	 * Seconds the upload request may spend on the dry run before the screen takes over in steps.
	 */
	public const STAGE_BUDGET = 8;

	/**
	 * Seconds one step of the Import screen may run (a host usually allows 30).
	 */
	public const STEP_BUDGET = 10;

	/**
	 * Batches.
	 *
	 * @var BatchRepository
	 */
	private BatchRepository $batches;

	/**
	 * Bundle store.
	 *
	 * @var BundleStore
	 */
	private BundleStore $store;

	/**
	 * Constructor.
	 *
	 * @param BundleStore|null     $store   Bundle store.
	 * @param BatchRepository|null $batches Batches.
	 */
	public function __construct( ?BundleStore $store = null, ?BatchRepository $batches = null ) {
		$this->store   = $store ?? new BundleStore();
		$this->batches = $batches ?? new BatchRepository();
	}

	/**
	 * Store an upload, register its batch and run the dry run.
	 *
	 * @param array{name: string, tmp_name: string, size: int, error: int}|null $file     The upload.
	 * @param int                                                               $actor_id The Administrator.
	 * @return string The batch id.
	 * @throws StateException When not allowed, or the upload or bundle is refused (the message is fit to show).
	 */
	public function stage( ?array $file, int $actor_id ): string {
		$this->allow( $actor_id );
		try {
			$stored = $this->store->receive( $file );
		} catch ( \Throwable $e ) {
			throw StateException::refuse( $e instanceof \RuntimeException ? $e->getMessage() : 'The bundle was refused.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- the screen escapes the message when it shows it.
		}
		$bundle = $stored['bundle'];
		try {
			$this->batches->register(
				$bundle,
				$actor_id,
				array(
					StateAssigner::SETTING => StateAssigner::import_all_is_on( SettingsService::for_site() ),
					'bundle_dir'           => $stored['dir'],
				),
				$stored['name']
			);
		} catch ( \RuntimeException $e ) {
			$this->store->discard( $stored['dir'] );
			throw StateException::refuse( $e->getMessage() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- the screen escapes the message when it shows it.
		}
		$batch_id = (string) $bundle->manifest()['batch_id'];
		$started  = time();
		( new DryRun( new Suppression( AuditLogger::for_site() ), $this->batches ) )->step( $bundle, $batch_id, static fn (): bool => time() - $started >= self::STAGE_BUDGET );
		return $batch_id;
	}

	/**
	 * Stage a zip that staff put in the server import folder, with the same checks as an upload; the zip is then deleted from
	 * that folder.
	 *
	 * @param string          $name     File name in the folder.
	 * @param int             $actor_id The Administrator.
	 * @param ServerDrop|null $drop     The folder.
	 * @return string The batch id.
	 * @throws StateException When not allowed or refused (the message is fit to show).
	 */
	public function stage_from_server( string $name, int $actor_id, ?ServerDrop $drop = null ): string {
		$this->allow( $actor_id );
		$drop = $drop ?? new ServerDrop();
		try {
			$file = $drop->take( $name );
		} catch ( \RuntimeException $e ) {
			throw StateException::refuse( $e->getMessage() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- the screen escapes the message when it shows it.
		}
		$batch = $this->stage( $file, $actor_id );
		$drop->remove( $name );
		return $batch;
	}

	/**
	 * Receive one piece of a zip uploaded in pieces; when the last piece arrives the whole zip is staged as stage() does.
	 *
	 * @param string $upload_id Upload id ("" for the first piece).
	 * @param int    $offset    Where the piece starts.
	 * @param int    $total     Size of the whole zip.
	 * @param string $name      File name of the zip.
	 * @param string $piece     Path of the uploaded piece.
	 * @param int    $actor_id  The Administrator.
	 * @return array{upload_id: string, received: int, batch: string}
	 * @throws StateException When not allowed or refused (the message is fit to show).
	 */
	public function receive_piece( string $upload_id, int $offset, int $total, string $name, string $piece, int $actor_id ): array {
		$this->allow( $actor_id );
		if ( ! str_ends_with( strtolower( $name ), '.zip' ) ) {
			throw StateException::refuse( 'Upload the bundle as a .zip file holding manifest.json and records.jsonl.' );
		}
		try {
			$got = $this->store->append_piece( $upload_id, $offset, $total, $piece );
		} catch ( \RuntimeException $e ) {
			throw StateException::refuse( $e->getMessage() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- the screen escapes the message when it shows it.
		}
		$out = array(
			'upload_id' => $got['upload_id'],
			'received'  => $got['received'],
			'batch'     => '',
		);
		if ( $got['received'] < $total ) {
			return $out;
		}
		try {
			$out['batch'] = $this->stage(
				array(
					'name'     => $name,
					'tmp_name' => $got['path'],
					'size'     => $total,
					'error'    => UPLOAD_ERR_OK,
				),
				$actor_id
			);
		} finally {
			$this->store->drop_upload( $got['upload_id'] );
		}
		return $out;
	}

	/**
	 * Move a batch on by one step within the time budget, for the Import screen's progress bar: the dry run while the batch is
	 * checked, the apply while it is applying. A large bundle is handled in many short requests, so no request runs long.
	 *
	 * @param string $batch_id Batch id.
	 * @param int    $actor_id The Administrator.
	 * @return array{status: string, phase: string, cursor: int, total: int, done: bool, busy: bool, counts: array<string, int>}
	 * @throws StateException When not allowed or the batch is unusable.
	 */
	public function step( string $batch_id, int $actor_id ): array {
		$this->allow( $actor_id );
		$batch = $this->batches->get( $batch_id );
		if ( null === $batch ) {
			throw StateException::refuse( 'Unknown batch.' );
		}
		$status = (string) $batch['status'];
		$total  = (int) $batch['record_count'];
		try {
			if ( 'checked' === $status ) {
				$out = ( new DryRun( new Suppression( AuditLogger::for_site() ), $this->batches ) )->step( $this->bundle( $batch_id ), $batch_id, self::stop( self::STEP_BUDGET ) );
				return $this->progress( $batch_id, 'dry_run', $out['cursor'], $total, $out['done'], $out['busy'], $out['counts'] );
			}
			if ( 'applying' === $status ) {
				$out    = ApplyJob::chunk( $batch_id, self::STEP_BUDGET );
				$cursor = (int) ( $this->batches->get( $batch_id )['progress_cursor'] ?? 0 );
				return $this->progress( $batch_id, 'apply', $cursor, $total, $out['done'], $out['busy'], $out['counts'] );
			}
			if ( 'applied' === $status && BatchUndo::in_progress( $batch_id ) ) {
				$out = $this->undo( $batch_id, $actor_id );
				return $this->progress( $batch_id, 'undo', $out['removed'] + $out['reverted'] + $out['kept'], $total, $out['done'], false, array() );
			}
		} catch ( StateException $e ) {
			throw $e;
		} catch ( \Throwable $e ) {
			unset( $e );
			throw StateException::refuse( 'The import could not continue. Try again; it resumes where it stopped.' );
		}
		$counts = 'dry_run' === $status ? DryRun::stored( $batch_id )['counts'] : ( new ResultRepository() )->counts( $batch_id );
		$cursor = 'dry_run' === $status ? DryRun::stored( $batch_id )['cursor'] : (int) $batch['progress_cursor'];
		return $this->progress( $batch_id, 'dry_run' === $status ? 'dry_run' : 'done', $cursor, $total, true, false, $counts );
	}

	/**
	 * Progress for the screen.
	 *
	 * @param string             $batch_id Batch.
	 * @param string             $phase    dry_run, apply or done.
	 * @param int                $cursor   Records handled.
	 * @param int                $total    Records in the bundle.
	 * @param bool               $done     Phase finished.
	 * @param bool               $busy     Another worker holds the batch.
	 * @param array<string, int> $counts   Counts by code.
	 * @return array{status: string, phase: string, cursor: int, total: int, done: bool, busy: bool, counts: array<string, int>}
	 */
	private function progress( string $batch_id, string $phase, int $cursor, int $total, bool $done, bool $busy, array $counts ): array {
		return array(
			'status' => (string) ( $this->batches->get( $batch_id )['status'] ?? '' ),
			'phase'  => $phase,
			'cursor' => $cursor,
			'total'  => $total,
			'done'   => $done,
			'busy'   => $busy,
			'counts' => $counts,
		);
	}

	/**
	 * A time-budget check that starts now.
	 *
	 * @param int $seconds Budget.
	 */
	private static function stop( int $seconds ): callable {
		return ChunkApplier::budget( $seconds );
	}

	/**
	 * Run the dry run again from the start (after a mapping was saved): the stored preview is dropped and the batch goes back to
	 * checked, so the screen steps through it again.
	 *
	 * @param string $batch_id Batch id.
	 * @param int    $actor_id The Administrator.
	 * @throws StateException When not allowed.
	 */
	public function restart_dry_run( string $batch_id, int $actor_id ): void {
		$this->allow( $actor_id );
		$batch = $this->batches->get( $batch_id );
		if ( null === $batch || ! in_array( (string) $batch['status'], array( 'checked', 'dry_run' ), true ) ) {
			return;
		}
		DryRun::reset( $batch_id );
		$this->batches->advance( $batch_id, 0, 'checked' );
	}

	/**
	 * Open the stored bundle of a batch.
	 *
	 * @param string $batch_id Batch id.
	 * @throws StateException For an unknown batch or a bundle that is gone.
	 */
	public function bundle( string $batch_id ): BundleReader {
		$batch    = $this->batches->get( $batch_id );
		$snapshot = null === $batch ? null : json_decode( (string) $batch['settings_snapshot'], true );
		$dir      = is_array( $snapshot ) ? (string) ( $snapshot['bundle_dir'] ?? '' ) : '';
		if ( '' === $dir ) {
			throw StateException::refuse( 'That batch has no stored bundle.' );
		}
		try {
			return BundleReader::reopen( $dir, (string) $batch['file_hash'] );
		} catch ( \Throwable $e ) {
			unset( $e );
			throw StateException::refuse( 'The stored bundle can no longer be read.' );
		}
	}

	/**
	 * Run the dry run again for a batch (reads only) and return it.
	 *
	 * @param string $batch_id Batch id.
	 * @param int    $actor_id The Administrator.
	 * @return array{file_hash: string, counts: array<string, int>, records: array<int, array<string, mixed>>}
	 * @throws StateException When not allowed or the batch is unusable.
	 */
	public function preview( string $batch_id, int $actor_id ): array {
		$this->allow( $actor_id );
		$bundle = $this->bundle( $batch_id );
		return ( new DryRun( new Suppression( AuditLogger::for_site() ), $this->batches ) )->run( $bundle, $batch_id );
	}

	/**
	 * Confirm a previewed batch: queue the chunked apply job. A batch already applying is queued again (resume): the cursor
	 * continues where it stopped.
	 *
	 * @param string $batch_id Batch id.
	 * @param int    $actor_id The Administrator.
	 * @throws StateException When not allowed or the batch is not previewed.
	 */
	public function confirm( string $batch_id, int $actor_id ): void {
		$this->allow( $actor_id );
		$batch = $this->batches->get( $batch_id );
		if ( null === $batch || ! in_array( (string) $batch['status'], array( 'dry_run', 'applying' ), true ) ) {
			throw StateException::refuse( 'Run the dry run first: only a previewed batch can be confirmed.' );
		}
		AuditLogger::for_site()->log(
			'import.confirm',
			array(
				'type' => 'import_batch',
				'id'   => $batch_id,
			),
			null,
			array( 'status' => (string) $batch['status'] ),
			'',
			$batch_id
		);
		// The batch shows as applying at once, so the screen draws the progress; the job and the screen's steps share the work.
		if ( 'dry_run' === (string) $batch['status'] ) {
			$this->batches->advance( $batch_id, (int) $batch['progress_cursor'], 'applying' );
		}
		( new ApplyJob() )->start( $batch_id );
	}

	/**
	 * Undo an applied batch.
	 *
	 * @param string $batch_id Batch id.
	 * @param int    $actor_id The Administrator.
	 * @return array{removed: int, reverted: int, kept: int, kept_record_ids: string[], media_removed: int, done: bool}
	 * @throws StateException When refused.
	 */
	public function undo( string $batch_id, int $actor_id ): array {
		return ( new BatchUndo() )->undo( $batch_id, $actor_id, self::stop( self::STEP_BUDGET ) );
	}

	/**
	 * Refuse an actor without the capability.
	 *
	 * @param int $actor_id Actor.
	 * @throws StateException When not allowed.
	 */
	private function allow( int $actor_id ): void {
		if ( ! user_can( $actor_id, self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw StateException::refuse( 'Not allowed.' );
		}
	}
}
