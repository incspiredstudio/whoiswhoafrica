<?php
/**
 * The sample profiles that ship with the plugin (REQ-208, REQ-237, REQ-056): an export bundle in `whoiswho-core/sample/`, either
 * the folder itself (manifest.json and records.jsonl) or `sample/sample-50.zip`. It is imported through the normal import path,
 * as a batch the acting Administrator confirmed: the Import service stores and checks it and runs the dry run, ChunkApplier
 * applies it a chunk at a time with the usual validation, provenance and states, and the batch gets its usual audit row. The
 * demo then approves each created profile through Review\StateMachine (Published, reviewed, with the Administrator as
 * reviewer), marks it as demo content and audits that. This is the only class that calls the import services for the demo, so
 * a change to those services is adjusted here. The folder is never committed (.gitignore); a release adds it on request.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Setup;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Import\Admin\BundleStore;
use WhoIsWho\Core\Import\Admin\ImportService;
use WhoIsWho\Core\Import\Apply\ApplyJob;
use WhoIsWho\Core\Import\Apply\ChunkApplier;
use WhoIsWho\Core\Import\Apply\ProfileApplier;
use WhoIsWho\Core\Import\Apply\RecordMap;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\BundleRefused;
use WhoIsWho\Core\Import\BundleReader;
use WhoIsWho\Core\Import\DryRun\DryRun;
use WhoIsWho\Core\Import\Result\ImportAudit;
use WhoIsWho\Core\Import\StateAssigner;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Cli\Sample\SampleMarker;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Review\StateException;
use WhoIsWho\Core\Review\StateMachine;

/**
 * Sample bundle.
 */
final class SampleBundle {

	public const ZIP        = 'sample-50.zip';
	public const OPTION     = 'wiw_demo_bundle';
	public const KEY_PREFIX = 'profile:import:';
	public const REFUSED    = 4091;

	/**
	 * Seconds one call may spend applying records.
	 */
	public const BUDGET = 8;

	/**
	 * The sample folder.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Constructor.
	 *
	 * @param string|null $root The sample folder; the plugin's own by default (tests pass a temporary one).
	 */
	public function __construct( ?string $root = null ) {
		$this->root = rtrim( str_replace( '\\', '/', $root ?? self::default_root() ), '/' );
	}

	/**
	 * The plugin's sample folder.
	 */
	public static function default_root(): string {
		return str_replace( '\\', '/', dirname( __DIR__, 3 ) ) . '/sample';
	}

	/**
	 * Where the bundle is, or null when the plugin ships without one.
	 *
	 * @return array{0: string, 1: string}|null The form (dir or zip) and the path.
	 */
	public function source(): ?array {
		if ( is_readable( $this->root . '/manifest.json' ) && is_readable( $this->root . '/records.jsonl' ) ) {
			return array( 'dir', $this->root );
		}
		if ( is_readable( $this->root . '/' . self::ZIP ) ) {
			return array( 'zip', $this->root . '/' . self::ZIP );
		}
		$zips = glob( $this->root . '/*.zip' );
		return is_array( $zips ) && array() !== $zips ? array( 'zip', (string) $zips[0] ) : null;
	}

	/**
	 * Whether the plugin ships sample profiles.
	 */
	public function available(): bool {
		return null !== $this->source();
	}

	/**
	 * How many records the bundle holds (0 when it cannot be read).
	 */
	public function total(): int {
		$batch = $this->batch();
		if ( null !== $batch ) {
			return (int) $batch['record_count'];
		}
		$source = $this->source();
		if ( null === $source ) {
			return 0;
		}
		$json = '';
		if ( 'dir' === $source[0] ) {
			$json = (string) file_get_contents( $source[1] . '/manifest.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file inside the plugin.
		} elseif ( class_exists( \ZipArchive::class ) ) {
			$zip = new \ZipArchive();
			if ( true === $zip->open( $source[1] ) ) {
				$json = (string) $zip->getFromName( 'manifest.json' );
				$zip->close();
			}
		}
		$manifest = json_decode( $json, true );
		return is_array( $manifest ) ? max( 0, (int) ( $manifest['record_count'] ?? 0 ) ) : 0;
	}

	/**
	 * The demo batch row, or null before the first run.
	 *
	 * @return array<string, mixed>|null
	 */
	public function batch(): ?array {
		$id = $this->batch_id();
		return '' === $id ? null : ( new BatchRepository() )->get( $id );
	}

	/**
	 * The demo batch id, or an empty string.
	 */
	public function batch_id(): string {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? (string) ( $stored['batch_id'] ?? '' ) : '';
	}

	/**
	 * Import and approve the next records. Safe to repeat: the batch keeps its cursor, and approving skips what is done.
	 *
	 * @param int $actor Administrator.
	 * @param int $limit Records in this call.
	 * @return int Records handled so far (the batch cursor).
	 * @throws \RuntimeException When the bundle is refused (the message is fit to show).
	 */
	public function run( int $actor, int $limit ): int {
		$batch_id = $this->batch_id();
		$batches  = new BatchRepository();
		if ( '' === $batch_id || null === $batches->get( $batch_id ) ) {
			$batch_id = $this->stage( $actor );
		}
		$started = time();
		$stop    = static fn (): bool => time() - $started >= self::BUDGET;
		$batch   = (array) $batches->get( $batch_id );
		if ( 'checked' === (string) $batch['status'] ) {
			// A large bundle's dry run is stepped, as on the Import screen: it continues on the next call until it is complete.
			( new DryRun( new Suppression( AuditLogger::for_site() ), $batches ) )->step( ( new ImportService() )->bundle( $batch_id ), $batch_id, $stop );
			$batch = (array) $batches->get( $batch_id );
		}
		if ( in_array( (string) $batch['status'], array( 'dry_run', 'applying' ), true ) && ! $stop() ) {
			if ( 'dry_run' === (string) $batch['status'] ) {
				$this->confirm( $batch_id );
			}
			if ( str_starts_with( str_replace( '\\', '/', ApplyJob::bundle_dir( $batch ) ), ( new BundleStore() )->root() . '/' ) ) {
				// The zip was unpacked into the private import folder: the import's own chunk step applies it (images included),
				// writes the audit row and deletes the unpacked copy when the batch is complete.
				ApplyJob::chunk( $batch_id, max( 1, self::BUDGET - ( time() - $started ) ) );
			} else {
				// A sample folder inside the plugin: applied the same way, but the folder itself is never deleted.
				$out = ( new ChunkApplier( new ProfileApplier( new Suppression( AuditLogger::for_site() ) ), $batches ) )->run( ( new ImportService() )->bundle( $batch_id ), $batch_id, $limit, $stop );
				if ( ! empty( $out['finished'] ) ) {
					( new ImportAudit() )->record( (array) $batches->get( $batch_id ) );
				}
			}
		}
		$this->approve( $batch_id, $actor );
		$batch = (array) $batches->get( $batch_id );
		return 'applied' === (string) ( $batch['status'] ?? '' ) ? (int) $batch['record_count'] : (int) ( $batch['progress_cursor'] ?? 0 );
	}

	/**
	 * Store and check the bundle, register its batch and run the dry run, through the Import service.
	 *
	 * @param int $actor Administrator.
	 * @return string Batch id.
	 * @throws \RuntimeException When there is no bundle or it is refused.
	 */
	private function stage( int $actor ): string {
		$source = $this->source();
		if ( null === $source ) {
			throw new \RuntimeException( 'The plugin has no sample profiles.' );
		}
		try {
			if ( 'zip' === $source[0] ) {
				$batch_id = ( new ImportService() )->stage(
					array(
						'name'     => basename( $source[1] ),
						'tmp_name' => $source[1],
						'size'     => (int) filesize( $source[1] ),
						'error'    => UPLOAD_ERR_OK,
					),
					$actor
				);
			} else {
				$batch_id = $this->stage_folder( $source[1], $actor );
			}
		} catch ( StateException | BundleRefused $e ) {
			// The import's own fixed wording (never record values), so the Setup screen can say why.
			throw new \RuntimeException( esc_html( $e->getMessage() ), self::REFUSED ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped here.
		}
		update_option( self::OPTION, array( 'batch_id' => $batch_id ), false );
		return $batch_id;
	}

	/**
	 * Register and preview a bundle folder, as the import command does for a folder.
	 *
	 * @param string $dir   Folder.
	 * @param int    $actor Administrator.
	 * @return string Batch id.
	 * @throws StateException When the actor may not import.
	 */
	private function stage_folder( string $dir, int $actor ): string {
		if ( ! user_can( $actor, ImportService::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw StateException::refuse( 'Not allowed.' );
		}
		$bundle   = BundleReader::open( $dir );
		$batches  = new BatchRepository();
		$batch_id = (string) $bundle->manifest()['batch_id'];
		if ( ! $batches->exists( $batch_id ) ) {
			$batches->register(
				$bundle,
				$actor,
				array(
					StateAssigner::SETTING => StateAssigner::import_all_is_on( SettingsService::for_site() ),
					'bundle_dir'           => $dir,
				),
				'demo: ' . basename( $dir )
			);
		}
		( new DryRun( new Suppression( AuditLogger::for_site() ), $batches ) )->run( $bundle, $batch_id );
		return $batch_id;
	}

	/**
	 * The confirmation row the Import screen writes when a person confirms a batch.
	 *
	 * @param string $batch_id Batch id.
	 */
	private function confirm( string $batch_id ): void {
		AuditLogger::for_site()->log(
			'import.confirm',
			array(
				'type' => 'import_batch',
				'id'   => $batch_id,
			),
			null,
			array( 'status' => 'dry_run' ),
			'Demo content',
			$batch_id
		);
	}

	/**
	 * Approve, mark and fingerprint each profile the batch created that is not done yet.
	 *
	 * @param string $batch_id Batch id.
	 * @param int    $actor    Administrator (the reviewer).
	 */
	private function approve( string $batch_id, int $actor ): void {
		$machine  = new StateMachine();
		$states   = new StateRepository();
		$map      = new RecordMap();
		$approved = 0;
		foreach ( ( new ResultRepository() )->created_profile_ids( $batch_id ) as $id ) {
			if ( '1' === (string) get_post_meta( $id, SampleMarker::META, true ) ) {
				continue;
			}
			$state = (string) $states->pub_state( $id );
			if ( PubState::PUBLISHED_REVIEWED !== $state && StateMachine::can_move( $state, PubState::PUBLISHED_REVIEWED ) ) {
				try {
					$machine->transition( $id, PubState::PUBLISHED_REVIEWED, 'Demo content: approved on import', $actor );
					++$approved;
				} catch ( StateException $e ) {
					unset( $e );
					// A module refused it (for example a missing consent): it stays as imported and is still demo content.
				}
			}
			SampleMarker::mark_post( $id, self::KEY_PREFIX . (string) $map->record_for( $id ) );
			SampleMarker::seal_post( $id );
		}
		if ( $approved > 0 ) {
			AuditLogger::for_site()->log(
				'demo.profiles_approved',
				array(
					'type' => 'import_batch',
					'id'   => $batch_id,
				),
				null,
				array( 'approved' => $approved ),
				'Demo content',
				$batch_id
			);
		}
	}

	/**
	 * Forget the demo batch once none of its profiles is left: its results, its row and its stored copy, so a later demo import
	 * starts afresh. A batch with a profile a person kept is left alone.
	 */
	public function forget(): void {
		$batch_id = $this->batch_id();
		if ( '' === $batch_id ) {
			return;
		}
		$results = new ResultRepository();
		foreach ( $results->created_profile_ids( $batch_id ) as $id ) {
			if ( null !== get_post( $id ) ) {
				return;
			}
		}
		$batch    = ( new BatchRepository() )->get( $batch_id );
		$snapshot = null === $batch ? null : json_decode( (string) $batch['settings_snapshot'], true );
		if ( is_array( $snapshot ) && '' !== (string) ( $snapshot['bundle_dir'] ?? '' ) ) {
			// Only a copy inside the private import folder is deleted; the plugin's own sample folder is never touched.
			( new BundleStore() )->discard( (string) $snapshot['bundle_dir'] );
		}
		global $wpdb;
		$wpdb->delete( ResultRepository::table(), array( 'batch_id' => $batch_id ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- clean-up of the demo batch.
		$wpdb->delete( BatchRepository::table(), array( 'batch_id' => $batch_id ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- as above.
		delete_option( self::OPTION );
		AuditLogger::for_site()->log(
			'demo.batch_removed',
			array(
				'type' => 'import_batch',
				'id'   => $batch_id,
			),
			null,
			null,
			'Demo content removed',
			$batch_id
		);
	}

	/**
	 * The demo profiles this bundle made that are public, oldest first.
	 *
	 * @return int[]
	 */
	public static function public_profile_ids(): array {
		$states = new StateRepository();
		$out    = array();
		foreach ( DemoParts::post_keys( 'profiles' ) as $id => $key ) {
			if ( str_starts_with( $key, self::KEY_PREFIX ) && PubState::is_public( (string) $states->pub_state( $id ) ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}
}
