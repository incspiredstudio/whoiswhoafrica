<?php
/**
 * The import dry run (REQ-237, REQ-141, REQ-207). Needs the WordPress test suite and
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
use WhoIsWho\Core\Import\BundleReader;
use WhoIsWho\Core\Import\DryRun\DryRun;
use WhoIsWho\Core\Import\ContentHasher;
use WhoIsWho\Core\Import\Scan\ScanBlock;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Review\ReviewService;
use WhoIsWho\Core\Review\StateException;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class DryRunTest extends TestCase {

	/**
	 * Bundle folders to remove.
	 *
	 * @var string[]
	 */
	private array $dirs = array();

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
		foreach ( $this->dirs as $dir ) {
			foreach ( glob( $dir . '/*' ) ?: array() as $file ) {
				unlink( $file );
			}
			rmdir( $dir );
		}
		$this->dirs = array();
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

	/**
	 * Write a bundle and register its batch.
	 *
	 * @param string[] $lines    Record lines.
	 * @param string   $batch_id Batch id.
	 * @return BundleReader
	 */
	private function bundle( array $lines, string $batch_id ): BundleReader {
		$dir = sys_get_temp_dir() . '/wiw-apply-' . bin2hex( random_bytes( 4 ) );
		mkdir( $dir );
		$this->dirs[] = $dir;
		$body         = implode( "\n", $lines ) . "\n";
		file_put_contents( $dir . '/records.jsonl', $body );
		file_put_contents(
			$dir . '/manifest.json',
			wp_json_encode(
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
			)
		);
		$bundle = BundleReader::open( $dir );
		( new BatchRepository() )->register( $bundle, 7, array( 'import_all_not_public' => false ), 'bundle' );
		return $bundle;
	}

	/**
	 * A chunk applier.
	 *
	 * @param Suppression|null $suppression Suppression list.
	 */
	private function applier( ?Suppression $suppression = null ): ChunkApplier {
		return new ChunkApplier( new ProfileApplier( $suppression ?? new Suppression( AuditLogger::for_site() ) ) );
	}

	/**
	 * A new user.
	 */
	private function user(): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'apply_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	/**
	 * Count profile posts.
	 */
	private function profile_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", ProfileType::POST_TYPE ) ); // phpcs:ignore WordPress.DB
	}

	private function dry( BundleReader $bundle, string $batch, ?Suppression $suppression = null ): array {
		return ( new DryRun( $suppression ?? new Suppression( AuditLogger::for_site() ) ) )->run( $bundle, $batch );
	}

	private function table_counts(): array {
		global $wpdb;
		$out = array();
		foreach ( array( $wpdb->posts, RecordMap::table(), ResultRepository::table(), StateRepository::table(), ProvenanceRepository::table(), VersionRepository::table() ) as $table ) {
			$out[ $table ] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table names.
		}
		return $out;
	}

	public function test_REQ_237_dry_run_writes_nothing(): void {
		$bundle = $this->bundle( array( $this->line( 'rec_a', 'Dry Alpha Qwv' ), $this->line( 'rec_b', 'Dry Beta Qwv' ) ), 'B-dry' );
		$before = $this->table_counts();
		$out    = $this->dry( $bundle, 'B-dry' );
		$this->assertSame( $before, $this->table_counts() );
		$this->assertSame( array( 'CREATED' => 2 ), $out['counts'] );
		$this->assertSame( 0, $this->profile_count() );
		$batch = ( new BatchRepository() )->get( 'B-dry' );
		$this->assertSame( 'dry_run', $batch['status'] );
		$this->assertSame( 0, (int) $batch['progress_cursor'] );
	}

	public function test_REQ_237_dry_run_shows_code_and_field_diff_per_record(): void {
		$first = $this->bundle( array( $this->line( 'rec_a', 'Dry Alpha Qwv' ) ), 'B-first' );
		$this->applier()->run( $first, 'B-first' );
		$lines  = array(
			$this->line( 'rec_a', 'Dry Alpha Qwv', 'Changed Regulator' ),
			$this->line( 'rec_new', 'Dry New Qwv', '', 'A plain summary.' ),
			str_replace( 'Dry Bad Qwv', 'Dry Tampered', $this->line( 'rec_bad', 'Dry Bad Qwv' ) ),
		);
		$bundle = $this->bundle( $lines, 'B-second' );
		$out    = $this->dry( $bundle, 'B-second' );
		$by_id  = array();
		foreach ( $out['records'] as $entry ) {
			$by_id[ $entry['record_id'] ] = $entry;
		}
		$this->assertSame( 'UPDATED', $by_id['rec_a']['code'] );
		$fields = array_column( $by_id['rec_a']['diff'], 'field' );
		$this->assertContains( 'regulator', $fields );
		$this->assertNotContains( 'name', $fields );
		$this->assertSame( 'CREATED', $by_id['rec_new']['code'] );
		$this->assertContains( 'summary', array_column( $by_id['rec_new']['diff'], 'field' ) );
		$this->assertSame( 'HASH_MISMATCH', $by_id['rec_bad']['code'] );
		$this->assertSame(
			array(
				'CREATED'       => 1,
				'HASH_MISMATCH' => 1,
				'UPDATED'       => 1,
			),
			$out['counts']
		);
	}

	public function test_REQ_207_dry_run_checks_the_live_suppression_list(): void {
		$suppression = new Suppression( AuditLogger::for_site() );
		$suppression->add_from_removal( 'Dry Hidden Qwv', 'organisation', array(), 1 );
		$bundle = $this->bundle( array( $this->line( 'rec_s', 'Dry Hidden Qwv' ) ), 'B-sup' );
		$out    = $this->dry( $bundle, 'B-sup', $suppression );
		$this->assertSame( array( 'SUPPRESSED' => 1 ), $out['counts'] );
	}

	public function test_REQ_237_apply_refuses_if_file_changed_since_dry_run(): void {
		$bundle = $this->bundle( array( $this->line( 'rec_a', 'Dry Alpha Qwv' ) ), 'B-hash' );
		$this->dry( $bundle, 'B-hash' );
		$other = $this->bundle( array( $this->line( 'rec_a', 'Dry Alpha Qwv' ), $this->line( 'rec_b', 'Dry Other Qwv' ) ), 'B-other' );
		$this->expectException( \RuntimeException::class );
		try {
			$this->applier()->run( $other, 'B-hash' );
		} finally {
			$this->assertSame( 0, $this->profile_count() );
		}
	}

	public function test_REQ_237_apply_runs_the_previewed_file(): void {
		$bundle = $this->bundle( array( $this->line( 'rec_a', 'Dry Alpha Qwv' ) ), 'B-same' );
		$this->dry( $bundle, 'B-same' );
		$out = $this->applier()->run( $bundle, 'B-same' );
		$this->assertSame( array( 'CREATED' => 1 ), $out['counts'] );
	}
}
