<?php
/**
 * M1-IMP-16 (REQ-237): a 1,000-record fictional bundle goes through dry run and chunked apply under a constrained time budget per
 * request and a memory ceiling that match the shared-host profile (docs/architecture.md section 8: 25 to 50 records a step, resume
 * after a time-out). The test generates the bundle, runs the dry run, applies it in requests that each stop after a small time
 * budget (so the run resumes many times), checks that every record was created exactly once and that peak memory stayed under the
 * ceiling, then re-applies the same bundle and expects UNCHANGED. It prints time per chunk and the total, and writes them to a JSON
 * file in the system temp folder. Plain TestCase: it needs the real tables and removes its rows afterwards.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Performance;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Import\Apply\ChunkApplier;
use WhoIsWho\Core\Import\Apply\ProfileApplier;
use WhoIsWho\Core\Import\Apply\RecordMap;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\BundleReader;
use WhoIsWho\Core\Import\ContentHasher;
use WhoIsWho\Core\Import\DryRun\DryRun;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

final class ImportThousandRecordsTest extends TestCase {

	private const RECORDS = 1000;

	/**
	 * Records applied in one step (docs/architecture.md section 8 says 25 to 50).
	 */
	private const CHUNK = 50;

	/**
	 * Each simulated request stops after this many seconds, so the run resumes across many "time-outs".
	 */
	private const REQUEST_BUDGET = 1.0;

	/**
	 * Peak memory ceiling in bytes: a shared host's usual 128M WordPress limit.
	 */
	private const MEMORY_CEILING = 134217728;

	/**
	 * Whole-run ceiling in seconds on a shared CI runner, cold caches.
	 */
	private const TOTAL_CEILING = 600.0;

	/**
	 * Bundle folder to remove.
	 */
	private string $dir = '';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! taxonomy_exists( Taxonomies::ORG_TYPE ) ) {
			( new Taxonomies() )->register();
		}
		if ( ! post_type_exists( ProfileType::POST_TYPE ) ) {
			( new ProfileType() )->register();
		}
		if ( ! term_exists( 'commercial-bank', Taxonomies::ORG_TYPE ) ) {
			wp_insert_term( 'Commercial Bank', Taxonomies::ORG_TYPE, array( 'slug' => 'commercial-bank' ) );
		}
		$this->clean();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		$this->clean();
		if ( '' !== $this->dir && is_dir( $this->dir ) ) {
			foreach ( glob( $this->dir . '/*' ) ?: array() as $file ) {
				unlink( $file );
			}
			rmdir( $this->dir );
		}
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Remove every profile, map row, result and batch the run created.
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
	}

	/**
	 * Generate the fictional bundle and register its batch.
	 *
	 * @param string $batch_id Batch id.
	 */
	private function bundle( string $batch_id ): BundleReader {
		$template = json_decode( (string) file_get_contents( __DIR__ . '/../fixtures/import/worked-example.json' ), false );
		$lines    = array();
		for ( $i = 1; $i <= self::RECORDS; $i++ ) {
			$record                      = clone $template;
			$record->fields              = json_decode( (string) json_encode( $template->fields ), false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test.
			$record->record_id           = sprintf( 'rec_perf_%04d', $i );
			$record->batch_id            = $batch_id;
			$record->external_ids        = new \stdClass();
			$record->fields->name->value = sprintf( 'Perf Fictional Organisation Qwv %04d', $i );
			$record->content_hash        = ContentHasher::hash_record( $record );
			$lines[]                     = (string) json_encode( $record ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test.
		}
		$this->dir = sys_get_temp_dir() . '/wiw-perf-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->dir );
		$body = implode( "\n", $lines ) . "\n";
		file_put_contents( $this->dir . '/records.jsonl', $body );
		file_put_contents(
			$this->dir . '/manifest.json',
			wp_json_encode(
				array(
					'contract_version'        => '1.0',
					'run_id'                  => 'run_perf',
					'batch_id'                => $batch_id,
					'generated_at'            => '2026-11-10T09:00:00Z',
					'pipeline_version'        => 'p1',
					'source_list_version'     => 's1',
					'sources'                 => array(),
					'suppression_snapshot_id' => 'snap_1',
					'record_count'            => self::RECORDS,
					'counts'                  => array( 'organisation' => self::RECORDS ),
					'records_sha256'          => 'sha256:' . hash( 'sha256', $body ),
				)
			)
		);
		$bundle = BundleReader::open( $this->dir );
		( new BatchRepository() )->register( $bundle, 7, array( 'import_all_not_public' => false ), 'bundle' );
		return $bundle;
	}

	/**
	 * Count profile posts.
	 */
	private function profile_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", ProfileType::POST_TYPE ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Apply a bundle in simulated requests, each cut off by the request budget, until it is done.
	 *
	 * @param BundleReader $bundle   Bundle.
	 * @param string       $batch_id Batch id.
	 * @return array{requests: int, chunks: float[], counts: array<string, int>}
	 */
	private function apply_in_requests( BundleReader $bundle, string $batch_id ): array {
		$applier  = new ChunkApplier( new ProfileApplier( new Suppression( AuditLogger::for_site() ) ) );
		$requests = 0;
		$chunks   = array();
		$counts   = array();
		do {
			++$requests;
			$started  = microtime( true );
			$stop     = static function () use ( $started ): bool {
				return ( microtime( true ) - $started ) > self::REQUEST_BUDGET;
			};
			$out      = $applier->run( $bundle, $batch_id, self::CHUNK, $stop );
			$chunks[] = microtime( true ) - $started;
			$counts   = $out['counts']; // The summary is cumulative for the batch.
		} while ( ! $out['done'] && $requests < 200 );
		$this->assertTrue( $out['done'], 'the batch did not finish within 200 simulated requests' );
		return array(
			'requests' => $requests,
			'chunks'   => $chunks,
			'counts'   => $counts,
		);
	}

	public function test_REQ_237_thousand_records_import_within_limits(): void {
		$bundle = $this->bundle( 'B-perf' );
		// The ceiling is for this import, not for the whole PHPUnit process: measure from the memory in use now.
		$memory_base = memory_get_usage( true );
		if ( function_exists( 'memory_reset_peak_usage' ) ) {
			memory_reset_peak_usage();
		}
		$t0 = microtime( true );

		$dry = ( new DryRun( new Suppression( AuditLogger::for_site() ) ) )->run( $bundle, 'B-perf' );
		$this->assertIsArray( $dry );
		$dry_time = microtime( true ) - $t0;

		$apply_start = microtime( true );
		$first       = $this->apply_in_requests( $bundle, 'B-perf' );
		$apply_time  = microtime( true ) - $apply_start;
		$total       = microtime( true ) - $t0;

		$this->assertSame( self::RECORDS, $this->profile_count(), 'one profile per record, none twice' );
		$this->assertSame( array( 'CREATED' => self::RECORDS ), $first['counts'] );
		$this->assertGreaterThan( self::RECORDS / self::CHUNK, $first['requests'], 'at least one request was cut off and resumed' );
		$this->assertLessThan( self::MEMORY_CEILING, memory_get_peak_usage( true ) - ( function_exists( 'memory_reset_peak_usage' ) ? $memory_base : 0 ), 'peak memory over the host ceiling' );
		$this->assertLessThan( self::TOTAL_CEILING, $total, 'the whole import took too long' );

		$second_batch = $this->bundle( 'B-perf-2' );
		( new DryRun( new Suppression( AuditLogger::for_site() ) ) )->run( $second_batch, 'B-perf-2' );
		$second = $this->apply_in_requests( $second_batch, 'B-perf-2' );
		$this->assertSame( array( 'UNCHANGED' => self::RECORDS ), $second['counts'], 'a second import of the same records changes nothing' );
		$this->assertSame( self::RECORDS, $this->profile_count() );

		$report = array(
			'records'             => self::RECORDS,
			'chunk_size'          => self::CHUNK,
			'request_budget_s'    => self::REQUEST_BUDGET,
			'dry_run_s'           => round( $dry_time, 2 ),
			'apply_s'             => round( $apply_time, 2 ),
			'total_s'             => round( $total, 2 ),
			'requests_to_finish'  => $first['requests'],
			'seconds_per_request' => array_map( static fn( float $s ): float => round( $s, 2 ), $first['chunks'] ),
			'peak_memory_mb'      => round( memory_get_peak_usage( true ) / 1048576, 1 ),
		);
		file_put_contents( sys_get_temp_dir() . '/wiw-import-1000.json', (string) wp_json_encode( $report, JSON_PRETTY_PRINT ) );
		fwrite( STDERR, "\nimport 1000: " . wp_json_encode( $report ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}
