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
		( new DryRun( new Suppression( AuditLogger::for_site() ), $this->batches ) )->run( $bundle, $batch_id );
		return $batch_id;
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
			return BundleReader::open( $dir );
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
		( new ApplyJob() )->start( $batch_id );
	}

	/**
	 * Undo an applied batch.
	 *
	 * @param string $batch_id Batch id.
	 * @param int    $actor_id The Administrator.
	 * @return array{removed: int, reverted: int, kept: int, kept_record_ids: string[]}
	 * @throws StateException When refused.
	 */
	public function undo( string $batch_id, int $actor_id ): array {
		return ( new BatchUndo() )->undo( $batch_id, $actor_id );
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
