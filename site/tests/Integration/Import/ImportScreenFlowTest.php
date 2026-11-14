<?php
/**
 * The Import screen flow: upload, dry run, confirm, undo (REQ-237, REQ-092). Needs the WordPress test suite and
 * the real tables, so it is a plain TestCase that cleans up after itself.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Import;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Import\Apply\ChunkApplier;
use WhoIsWho\Core\Import\Apply\ProfileApplier;
use WhoIsWho\Core\Import\Apply\RecordMap;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\Admin\BundleStore;
use WhoIsWho\Core\Import\Admin\ImportService;
use WhoIsWho\Core\Import\Apply\ApplyJob;
use WhoIsWho\Core\Import\ContentHasher;
use WhoIsWho\Core\Import\Scan\ScanBlock;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Review\ReviewService;
use WhoIsWho\Core\Review\StateException;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ImportScreenFlowTest extends TestCase {

	/**
	 * Bundle folders to remove.
	 *
	 * @var string[]
	 */
	private array $dirs = array();

	/**
	 * Private roots to remove.
	 *
	 * @var string[]
	 */
	private array $roots = array();

	/**
	 * Users to remove.
	 *
	 * @var int[]
	 */
	private array $users = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! taxonomy_exists( Taxonomies::ORG_TYPE ) ) {
			( new Taxonomies() )->register();
		}
		if ( ! post_type_exists( ProfileType::POST_TYPE ) ) {
			( new ProfileType() )->register();
		}
		$this->clean();
		if ( ! term_exists( 'commercial-bank', Taxonomies::ORG_TYPE ) ) {
			wp_insert_term( 'Commercial Bank', Taxonomies::ORG_TYPE, array( 'slug' => 'commercial-bank' ) );
		}
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		$this->clean();
		foreach ( $this->dirs as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		$this->dirs = array();
		foreach ( $this->roots as $root ) {
			foreach ( glob( $root . '/{,.}[!.]*', GLOB_BRACE ) ?: array() as $folder ) {
				if ( is_file( $folder ) ) {
					// The guard files of the private folder.
					unlink( $folder );
					continue;
				}
				foreach ( glob( $folder . '/*' ) ?: array() as $file ) {
					unlink( $file );
				}
				rmdir( $folder );
			}
			if ( is_dir( $root ) ) {
				rmdir( $root );
			}
		}
		$this->roots = array();
		foreach ( $this->users as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Remove every profile, map row, result and batch this test created.
	 */
	private function clean(): void {
		global $wpdb;
		$ids = (array) $wpdb->get_col( 'SELECT profile_id FROM ' . RecordMap::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( array( RecordMap::table(), ResultRepository::table(), BatchRepository::table(), Suppression::table() ) as $table ) {
			$wpdb->query( 'DELETE FROM ' . $table ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table names.
		}
		delete_option( SettingsService::OPTION_PREFIX . 'import_all_not_public' );
	}

	/**
	 * One record line for the given id and name, with a correct content hash.
	 *
	 * @param string $id   Record id.
	 * @param string $name Organisation name.
	 * @param string $city Optional regulator text, to change the hash.
	 * @param string $summary Optional free-text summary.
	 */
	private function line( string $id, string $name, string $city = '', string $summary = '' ): string {
		$record                      = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/import/worked-example.json' ), false );
		$record->record_id           = $id;
		$record->batch_id            = 'B-apply';
		$record->external_ids        = new \stdClass();
		$record->fields->name->value = $name;
		if ( '' !== $city ) {
			$record->fields->regulator->value = $city;
		}
		if ( '' !== $summary ) {
			$record->fields->summary = (object) array(
				'value'      => $summary,
				'provenance' => $record->fields->name->provenance,
			);
		}
		$record->content_hash = ContentHasher::hash_record( $record );
		return (string) json_encode( $record ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test.
	}

	private function user(): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'screen_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	private function profile_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", ProfileType::POST_TYPE ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * A zip of a bundle holding the lines, written to the test temp folder.
	 *
	 * @param string[] $lines    Record lines.
	 * @param string   $batch_id Batch id.
	 * @return array{name: string, tmp_name: string, size: int, error: int}
	 */
	private function upload( array $lines, string $batch_id ): array {
		$body = implode(
			'
',
			$lines
		) . '
';
		$meta = wp_json_encode(
			array(
				'contract_version'        => '1.0',
				'run_id'                  => 'run_1',
				'batch_id'                => $batch_id,
				'generated_at'            => '2026-11-10T09:00:00Z',
				'pipeline_version'        => 'p1',
				'source_list_version'     => 's1',
				'sources'                 => array(),
				'suppression_snapshot_id' => 'snap_1',
				'record_count'            => count( $lines ),
				'counts'                  => array( 'organisation' => count( $lines ) ),
				'records_sha256'          => 'sha256:' . hash( 'sha256', $body ),
			)
		);
		$path = sys_get_temp_dir() . '/wiw-up-' . bin2hex( random_bytes( 4 ) ) . '.zip';
		$zip  = new \ZipArchive();
		$zip->open( $path, \ZipArchive::CREATE );
		$zip->addFromString( 'manifest.json', (string) $meta );
		$zip->addFromString( 'records.jsonl', $body );
		$zip->close();
		$this->dirs[] = $path;
		return array(
			'name'     => 'bundle.zip',
			'tmp_name' => $path,
			'size'     => (int) filesize( $path ),
			'error'    => UPLOAD_ERR_OK,
		);
	}

	private function service(): ImportService {
		$root          = sys_get_temp_dir() . '/wiw-private-' . bin2hex( random_bytes( 4 ) );
		$this->roots[] = $root;
		return new ImportService( new BundleStore( $root ) );
	}

	/**
	 * An Administrator with the import capability.
	 */
	private function admin(): int {
		$id = $this->user();
		$u  = get_userdata( $id );
		$u->add_cap( 'wiw_run_imports' );
		return $id;
	}

	public function test_REQ_092_upload_stored_outside_web_root(): void {
		$service = $this->service();
		$batch   = $service->stage( $this->upload( array( $this->line( 'rec_a', 'Screen Alpha Qwv' ) ), 'B-up' ), $this->admin() );
		$row     = ( new BatchRepository() )->get( $batch );
		$dir     = (string) json_decode( (string) $row['settings_snapshot'], true )['bundle_dir'];
		$this->assertFileExists( $dir . '/records.jsonl' );
		$this->assertFalse( BundleStore::inside_web_root( $dir ) );
		$this->assertFalse( str_starts_with( $dir, rtrim( wp_normalize_path( ABSPATH ), '/' ) . '/' ) );
		// A root inside the web root is refused and nothing is stored.
		$bad = new ImportService( new BundleStore( ABSPATH . 'wiw-import-test' ) );
		try {
			$bad->stage( $this->upload( array( $this->line( 'rec_b', 'Screen Beta Qwv' ) ), 'B-bad' ), $this->admin() );
			$this->fail( 'expected a refusal' );
		} catch ( StateException $e ) {
			$this->assertStringContainsString( 'web root', $e->getMessage() );
		} finally {
			if ( is_dir( ABSPATH . 'wiw-import-test' ) ) {
				rmdir( ABSPATH . 'wiw-import-test' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test clean-up.
			}
		}
	}

	public function test_REQ_237_import_screen_runs_dry_run_before_apply(): void {
		$service = $this->service();
		$admin   = $this->admin();
		$batch   = $service->stage( $this->upload( array( $this->line( 'rec_a', 'Screen Alpha Qwv' ) ), 'B-flow' ), $admin );
		$this->assertSame( 'dry_run', ( new BatchRepository() )->get( $batch )['status'] );
		$this->assertSame( 0, $this->profile_count() );

		// Confirming the previewed batch queues the apply job, which then applies exactly the stored bundle.
		$service->confirm( $batch, $admin );
		( new ApplyJob() )->run( array( 'batch_id' => $batch ) );
		$this->assertSame( 'applied', ( new BatchRepository() )->get( $batch )['status'] );
		$this->assertSame( 1, $this->profile_count() );
		$out = $service->undo( $batch, $admin );
		$this->assertSame( 1, $out['removed'] );
	}

	public function test_REQ_237_an_unpreviewed_batch_cannot_be_confirmed(): void {
		$this->expectException( StateException::class );
		$this->service()->confirm( 'B-unknown', $this->admin() );
	}

	public function test_REQ_237_import_screen_refuses_without_capability(): void {
		$service = $this->service();
		$this->expectException( StateException::class );
		$service->stage( $this->upload( array( $this->line( 'rec_a', 'Screen Alpha Qwv' ) ), 'B-deny' ), $this->user() );
	}
}
