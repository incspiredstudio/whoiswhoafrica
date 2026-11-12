<?php
/**
 * The import command (REQ-237): php bin/wiw.php import <dry-run|apply|undo|status> --user=<login or id> [--bundle=<folder>]
 * [--batch=<batch id>]. It runs the same dry run, apply and undo code as the Import screen, so the result codes are the same.
 * It needs a named user who holds wiw_run_imports, and reads bundles only from the private import directory. It prints batch ids,
 * codes and counts, never record values.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Cli;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Import\Apply\ChunkApplier;
use WhoIsWho\Core\Import\Apply\ProfileApplier;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\BundleReader;
use WhoIsWho\Core\Import\DryRun\DryRun;
use WhoIsWho\Core\Import\Result\ImportAudit;
use WhoIsWho\Core\Import\StateAssigner;
use WhoIsWho\Core\Import\Undo\BatchUndo;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Cli\Command;
use WhoIsWho\Core\Platform\Cli\Output;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Review\StateException;

/**
 * Import command.
 */
final class ImportCommand implements Command {

	public const CAPABILITY  = 'wiw_run_imports';
	public const SUBCOMMANDS = array( 'dry-run', 'apply', 'undo', 'status' );

	/**
	 * Private import directory.
	 *
	 * @var PrivateImportDir
	 */
	private PrivateImportDir $dir;

	/**
	 * Constructor.
	 *
	 * @param PrivateImportDir|null $dir Directory; the site's private import directory by default.
	 */
	public function __construct( ?PrivateImportDir $dir = null ) {
		$this->dir = $dir ?? new PrivateImportDir();
	}

	/**
	 * Name.
	 */
	public function name(): string {
		return 'import';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Import a bundle: dry-run, apply, undo or status (needs --user).';
	}

	/**
	 * Run.
	 *
	 * @param string[] $args   Arguments: subcommand and --flags.
	 * @param Output   $output Output.
	 * @return int Exit code: 0 done, 1 failed, 2 usage.
	 */
	public function run( array $args, Output $output ): int {
		$sub   = (string) ( $args[0] ?? '' );
		$flags = self::flags( array_slice( $args, 1 ) );
		if ( ! in_array( $sub, self::SUBCOMMANDS, true ) ) {
			$output->line( 'Usage: import <dry-run|apply|undo|status> --user=<login or id> [--bundle=<folder>] [--batch=<batch id>]' );
			return 2;
		}
		$user = $this->user( $flags['user'] ?? '' );
		if ( null === $user ) {
			$output->line( 'Refused: --user must name an existing user who holds ' . self::CAPABILITY . '.' );
			return 2;
		}
		wp_set_current_user( $user );
		// The audit rows name this person.
		try {
			switch ( $sub ) {
				case 'dry-run':
					return $this->dry_run( $flags['bundle'] ?? '', $user, $output );
				case 'apply':
					return $this->apply( $flags['batch'] ?? '', $output );
				case 'undo':
					return $this->undo( $flags['batch'] ?? '', $user, $output );
				default:
					return $this->status( $flags['batch'] ?? '', $output );
			}
		} catch ( StateException | \RuntimeException $e ) {
			$output->line( 'Refused: ' . $e->getMessage() );
			return 1;
		}
	}

	/**
	 * Parse --name=value flags.
	 *
	 * @param string[] $args Arguments.
	 * @return array<string, string>
	 */
	public static function flags( array $args ): array {
		$out = array();
		foreach ( $args as $arg ) {
			if ( 1 === preg_match( '/^--([a-z-]+)=(.*)$/s', $arg, $m ) ) {
				$out[ $m[1] ] = $m[2];
			}
		}
		return $out;
	}

	/**
	 * The named user, when they exist and hold the capability.
	 *
	 * @param string $who Login or id.
	 * @return int|null User id.
	 */
	private function user( string $who ): ?int {
		if ( '' === trim( $who ) ) {
			return null;
		}
		$user = ctype_digit( $who ) ? get_user_by( 'id', (int) $who ) : get_user_by( 'login', $who );
		if ( ! $user instanceof \WP_User || ! user_can( $user, self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return null;
		}
		return (int) $user->ID;
	}

	/**
	 * Register the bundle (when new) and preview it.
	 *
	 * @param string $name    Bundle folder name.
	 * @param int    $user_id User.
	 * @param Output $output  Output.
	 */
	private function dry_run( string $name, int $user_id, Output $output ): int {
		$dir     = $this->dir->bundle( $name );
		$bundle  = BundleReader::open( $dir );
		$batches = new BatchRepository();
		$batch   = (string) $bundle->manifest()['batch_id'];
		if ( ! $batches->exists( $batch ) ) {
			$batches->register(
				$bundle,
				$user_id,
				array(
					StateAssigner::SETTING => StateAssigner::import_all_is_on( SettingsService::for_site() ),
					'bundle_dir'           => $dir,
				),
				$name
			);
		}
		$out = ( new DryRun( new Suppression( AuditLogger::for_site() ), $batches ) )->run( $bundle, $batch );
		$output->line( 'Batch: ' . $batch );
		foreach ( $out['counts'] as $code => $n ) {
			$output->line( $code . ': ' . $n );
		}
		return 0;
	}

	/**
	 * Apply a previewed batch to the end, then record the audit row, as the screen's job does.
	 *
	 * @param string $batch_id Batch id.
	 * @param Output $output   Output.
	 * @throws StateException When the batch has not been previewed.
	 */
	private function apply( string $batch_id, Output $output ): int {
		$batches = new BatchRepository();
		$batch   = $batches->get( $batch_id );
		if ( null === $batch || ! in_array( (string) $batch['status'], array( 'dry_run', 'applying' ), true ) ) {
			throw StateException::refuse( 'Run the dry run first: only a previewed batch can be applied.' );
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
		$bundle  = $this->stored_bundle( $batch );
		$applier = new ChunkApplier( new ProfileApplier( new Suppression( AuditLogger::for_site() ) ), $batches );
		do {
			$step = $applier->run( $bundle, $batch_id );
		} while ( ! $step['done'] );
		( new ImportAudit() )->record( (array) $batches->get( $batch_id ) );
		$output->line( 'Batch: ' . $batch_id );
		foreach ( ( new ResultRepository() )->counts( $batch_id ) as $code => $n ) {
			$output->line( $code . ': ' . $n );
		}
		return 0;
	}

	/**
	 * Undo an applied batch.
	 *
	 * @param string $batch_id Batch id.
	 * @param int    $user_id  User.
	 * @param Output $output   Output.
	 */
	private function undo( string $batch_id, int $user_id, Output $output ): int {
		$done = ( new BatchUndo() )->undo( $batch_id, $user_id );
		$output->line( 'Batch: ' . $batch_id );
		$output->line( 'removed: ' . $done['removed'] );
		$output->line( 'reverted: ' . $done['reverted'] );
		$output->line( 'kept: ' . $done['kept'] );
		return 0;
	}

	/**
	 * Print a batch's status and result counts.
	 *
	 * @param string $batch_id Batch id.
	 * @param Output $output   Output.
	 * @throws StateException For an unknown batch.
	 */
	private function status( string $batch_id, Output $output ): int {
		$batch = ( new BatchRepository() )->get( $batch_id );
		if ( null === $batch ) {
			throw StateException::refuse( 'Unknown batch.' );
		}
		$output->line( 'Batch: ' . $batch_id );
		$output->line( 'status: ' . (string) $batch['status'] );
		$output->line( 'progress: ' . (int) $batch['progress_cursor'] . '/' . (int) $batch['record_count'] );
		foreach ( ( new ResultRepository() )->counts( $batch_id ) as $code => $n ) {
			$output->line( $code . ': ' . $n );
		}
		return 0;
	}

	/**
	 * The stored bundle of a batch, read only from the private import directory.
	 *
	 * @param array<string, mixed> $batch Batch row.
	 * @throws StateException When the bundle is not in the private import directory.
	 */
	private function stored_bundle( array $batch ): BundleReader {
		$snapshot = json_decode( (string) $batch['settings_snapshot'], true );
		$stored   = is_array( $snapshot ) ? (string) ( $snapshot['bundle_dir'] ?? '' ) : '';
		try {
			return BundleReader::open( $this->dir->bundle( basename( $stored ) ) );
		} catch ( \RuntimeException $e ) {
			throw StateException::refuse( 'The bundle is not in the private import directory.' );
		}
	}
}
