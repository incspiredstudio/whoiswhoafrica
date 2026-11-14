<?php
/**
 * Applying an import bundle in resumable chunks (REQ-237, REQ-161, REQ-221, REQ-207, REQ-242). Needs the WordPress test suite and
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
use WhoIsWho\Core\Import\ContentHasher;
use WhoIsWho\Core\Import\Scan\ScanBlock;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Review\ReviewService;
use WhoIsWho\Core\Review\StateException;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ApplyTest extends TestCase {

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
			if ( ! is_dir( $dir ) ) {
				continue; // The apply job deletes a finished bundle.
			}
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

	public function test_REQ_242_import_tags_source_and_batch(): void {
		$bundle = $this->bundle( array( $this->line( 'rec_a', 'Apply Alpha Qwv' ) ), 'B-tag' );
		$out    = $this->applier()->run( $bundle, 'B-tag' );
		$this->assertTrue( $out['done'] );
		$this->assertSame( array( 'CREATED' => 1 ), $out['counts'] );
		$id  = ( new RecordMap() )->profile_for( 'rec_a' );
		$row = ( new StateRepository() )->get( (int) $id );
		$this->assertSame( 'B-tag', $row['import_batch'] );
		$this->assertSame( 'src_sample_regulator', $row['source'] );
		$this->assertSame( PubState::PUBLISHED_PENDING, $row['pub_state'] );
		$this->assertSame( 'Apply Alpha Qwv', get_the_title( (int) $id ) );
		$this->assertNotSame( array(), ( new ProvenanceRepository() )->for_profile( (int) $id, 'name' ) );
		$this->assertSame( 'applied', ( new BatchRepository() )->get( 'B-tag' )['status'] );
	}

	public function test_REQ_237_second_import_of_same_bundle_is_all_unchanged(): void {
		$lines = array( $this->line( 'rec_a', 'Apply Alpha Qwv' ), $this->line( 'rec_b', 'Apply Beta Qwv' ) );
		$first = $this->bundle( $lines, 'B-one' );
		$this->applier()->run( $first, 'B-one' );
		$this->assertSame( 2, $this->profile_count() );
		$second = $this->bundle( $lines, 'B-two' );
		$out    = $this->applier()->run( $second, 'B-two' );
		$this->assertSame( array( 'UNCHANGED' => 2 ), $out['counts'] );
		$this->assertSame( 2, $this->profile_count() );
	}

	public function test_REQ_237_apply_resumes_after_timeout(): void {
		$lines = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$lines[] = $this->line( 'rec_' . $i, 'Apply Item Qwv ' . $i );
		}
		$bundle = $this->bundle( $lines, 'B-resume' );
		// First request: the time budget is spent after two records.
		$calls = 0;
		$out   = $this->applier()->run(
			$bundle,
			'B-resume',
			25,
			static function () use ( &$calls ): bool {
				return ++$calls > 2;
			}
		);
		$this->assertFalse( $out['done'] );
		$this->assertSame( 2, $out['handled'] );
		$this->assertSame( '2', ( new BatchRepository() )->get( 'B-resume' )['progress_cursor'] );
		// A crash in the middle of the next request: the third record throws before it is written.
		$crash = new class( AuditLogger::for_site() ) extends Suppression {
			/**
			 * Throws once.
			 *
			 * @param array<string, mixed> $record Record.
			 * @throws \RuntimeException Always.
			 */
			public function refusal_for_import( array $record ): ?string {
				throw new \RuntimeException( 'Simulated timeout.' );
			}
		};
		try {
			$this->applier( $crash )->run( $bundle, 'B-resume' );
			$this->fail( 'The crash should have propagated.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'Simulated timeout.', $e->getMessage() );
		}
		$this->assertSame( 2, $this->profile_count() );
		// The next request resumes from the last saved cursor and finishes.
		$out = $this->applier()->run( $bundle, 'B-resume' );
		$this->assertTrue( $out['done'] );
		$this->assertSame( 3, $out['handled'] );
		$this->assertSame( array( 'CREATED' => 5 ), $out['counts'] );
		$this->assertSame( 5, $this->profile_count(), 'No record is duplicated.' );
		$this->assertSame( 5, count( ( new ResultRepository() )->pairs( 'B-resume' ) ) );
	}

	public function test_REQ_237_apply_resumes_when_cursor_was_not_saved(): void {
		$bundle = $this->bundle( array( $this->line( 'rec_a', 'Apply Alpha Qwv' ), $this->line( 'rec_b', 'Apply Beta Qwv' ) ), 'B-cursor' );
		$this->applier()->run( $bundle, 'B-cursor', 1 );
		// Lose the cursor, as if the process died between the write and the save.
		( new BatchRepository() )->advance( 'B-cursor', 0, 'applying' );
		$out = $this->applier()->run( $bundle, 'B-cursor' );
		$this->assertSame( 2, $this->profile_count() );
		$this->assertSame( array( 'CREATED' => 2 ), $out['counts'] );
	}

	public function test_REQ_207_suppressed_record_is_skipped_on_import(): void {
		( new Suppression( AuditLogger::for_site() ) )->add_declined_nominee( 'Apply Gamma Qwv', 'organisation' );
		$bundle = $this->bundle( array( $this->line( 'rec_s', 'Apply Gamma Qwv' ), $this->line( 'rec_ok', 'Apply Delta Qwv' ) ), 'B-supp' );
		$out    = $this->applier()->run( $bundle, 'B-supp' );
		$this->assertSame(
			array(
				'CREATED'    => 1,
				'SUPPRESSED' => 1,
			),
			$out['counts']
		);
		$this->assertNull( ( new RecordMap() )->profile_for( 'rec_s' ) );
		$this->assertSame( 1, $this->profile_count(), 'A suppressed record creates no profile and does not stop the rest.' );
		$this->assertSame( array( array( 'rec_s', 'SUPPRESSED' ), array( 'rec_ok', 'CREATED' ) ), ( new ResultRepository() )->pairs( 'B-supp' ) );
	}

	public function test_REQ_237_a_refused_record_does_not_stop_the_rest(): void {
		$bad               = json_decode( $this->line( 'rec_bad', 'Apply Bad Qwv' ) );
		$bad->content_hash = 'sha256:' . str_repeat( '0', 64 );
		$bundle            = $this->bundle( array( (string) json_encode( $bad ), $this->line( 'rec_ok', 'Apply Good Qwv' ) ), 'B-bad' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test.
		$out               = $this->applier()->run( $bundle, 'B-bad' );
		$this->assertSame(
			array(
				'CREATED'       => 1,
				'HASH_MISMATCH' => 1,
			),
			$out['counts']
		);
	}

	public function test_REQ_221_changed_unreviewed_record_is_updated_as_a_new_version(): void {
		$this->applier()->run( $this->bundle( array( $this->line( 'rec_a', 'Apply Alpha Qwv' ) ), 'B-v1' ), 'B-v1' );
		$id     = (int) ( new RecordMap() )->profile_for( 'rec_a' );
		$before = count( ( new VersionRepository() )->for_profile( $id ) ); // Version rows are append-only, so earlier runs may have left some for a reused id.
		$out    = $this->applier()->run( $this->bundle( array( $this->line( 'rec_a', 'Apply Alpha Qwv', 'Changed ' . bin2hex( random_bytes( 3 ) ) ) ), 'B-v2' ), 'B-v2' );
		$this->assertSame( array( 'UPDATED' => 1 ), $out['counts'] );
		$this->assertStringStartsWith( 'Changed ', (string) get_post_meta( $id, 'wiw_regulator', true ) );
		$versions = ( new VersionRepository() )->for_profile( $id );
		$this->assertCount( $before + 1, $versions );
		$this->assertSame( 'import', $versions[0]['created_via'] );
		$this->assertSame( 'B-v2', ( new StateRepository() )->get( $id )['import_batch'] );
	}

	public function test_REQ_161_reviewed_field_change_is_flagged_not_overwritten(): void {
		$this->applier()->run( $this->bundle( array( $this->line( 'rec_a', 'Apply Alpha Qwv' ) ), 'B-r1' ), 'B-r1' );
		$id = (int) ( new RecordMap() )->profile_for( 'rec_a' );
		( new StateMachine() )->transition( $id, PubState::PUBLISHED_REVIEWED, 'Checked', 7 );
		$before = get_post_meta( $id, 'wiw_regulator', true );
		$stale  = count( ( new VersionRepository() )->pending_for_profile( $id ) );

		$changed = $this->line( 'rec_a', 'Apply Alpha Qwv', 'Changed ' . bin2hex( random_bytes( 3 ) ) );
		$out     = $this->applier()->run( $this->bundle( array( $changed ), 'B-r2' ), 'B-r2' );
		$this->assertSame( array( 'FLAGGED_CHANGE' => 1 ), $out['counts'] );
		$this->assertSame( $before, get_post_meta( $id, 'wiw_regulator', true ), 'Reviewed fields are not changed.' );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, ( new StateRepository() )->pub_state( $id ) );
		$base = count( ( new VersionRepository() )->pending_for_profile( $id ) );
		$this->assertSame( 1, $base - $stale, 'A pending import version is on the review queue.' );

		// The same change in a later batch does not queue a second item.
		$this->applier()->run( $this->bundle( array( $changed ), 'B-r3' ), 'B-r3' );
		$this->assertCount( $stale + 1, ( new VersionRepository() )->pending_for_profile( $id ) );
	}

	public function test_REQ_237_job_applies_a_registered_batch_and_audits_the_counts(): void {
		global $wpdb;
		$lines  = array( $this->line( 'rec_a', 'Apply Alpha Qwv' ), $this->line( 'rec_b', 'Apply Beta Qwv' ) );
		$bundle = $this->bundle( $lines, 'B-job' );
		$dir    = '';
		foreach ( $this->dirs as $d ) {
			$dir = $d;
		}
		$batches = new BatchRepository();
		$wpdb->update(
			BatchRepository::table(),
			array(
				'settings_snapshot' => (string) wp_json_encode(
					array(
						'import_all_not_public' => false,
						'bundle_dir'            => $dir,
					)
				),
			),
			array( 'batch_id' => 'B-job' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertSame( 'checked', $batches->get( 'B-job' )['status'] );
		( new \WhoIsWho\Core\Import\Apply\ApplyJob() )->run( array( 'batch_id' => 'B-job' ) );
		$this->assertSame( 'applied', $batches->get( 'B-job' )['status'] );
		$this->assertSame( 2, $this->profile_count() );
		$this->assertSame( $bundle->file_hash(), $batches->get( 'B-job' )['file_hash'] );
		$audit = $wpdb->get_var( $wpdb->prepare( 'SELECT new_value FROM ' . $wpdb->prefix . "wiw_audit_log WHERE action = 'import.apply' AND subject_id = %s ORDER BY id DESC LIMIT 1", 'B-job' ) ); // phpcs:ignore WordPress.DB
		$this->assertStringContainsString( '"CREATED":2', (string) $audit );
	}

	public function test_REQ_141_flagged_record_blocked_until_cleared(): void {
		global $wpdb;
		$line = $this->line( 'rec_flag', 'Apply Flag Qwv', '', 'Contact the founder on +234 803 555 0142 about listing.' );
		$out  = $this->applier()->run( $this->bundle( array( $line ), 'B-flag' ), 'B-flag' );
		$this->assertSame( array( 'CREATED' => 1 ), $out['counts'] );
		$id = (int) ( new RecordMap() )->profile_for( 'rec_flag' );
		$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, ( new StateRepository() )->pub_state( $id ), 'A flagged organisation does not go live.' );
		$this->assertSame(
			array(
				array(
					'field'    => 'summary',
					'category' => 'phone',
				),
			),
			ScanBlock::flags( $id )
		);
		$this->assertStringNotContainsString( '803', (string) get_post_meta( $id, ScanBlock::META_KEY, true ), 'The matched text is never stored in the flags.' );

		$reviewer = $this->user();
		get_user_by( 'id', $reviewer )->add_cap( 'wiw_review_records' );
		$service = new ReviewService();
		try {
			$service->approve( $id, $reviewer );
			$this->fail( 'A blocked record must not be approved.' );
		} catch ( StateException $e ) {
			$this->assertStringContainsString( 'Clear the block', $e->getMessage() );
		}
		$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, ( new StateRepository() )->pub_state( $id ) );

		( new ScanBlock() )->clear( $id, $reviewer, 'Number is the registry office line.' );
		$this->assertFalse( ScanBlock::is_blocked( $id ) );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT actor_id, old_value, reason FROM ' . $wpdb->prefix . "wiw_audit_log WHERE action = 'import.scan_cleared' AND subject_id = %s ORDER BY id DESC LIMIT 1", (string) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$this->assertSame( (string) $reviewer, $row['actor_id'] );
		$this->assertStringNotContainsString( '803', (string) $row['old_value'], 'The audit row names the field and category, not the text.' );
		$service->approve( $id, $reviewer );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, ( new StateRepository() )->pub_state( $id ) );
	}

	public function test_REQ_141_clean_record_is_not_blocked_and_clearing_needs_the_capability(): void {
		$this->applier()->run( $this->bundle( array( $this->line( 'rec_clean', 'Apply Clean Qwv', '', 'A licensed commercial bank.' ) ), 'B-clean' ), 'B-clean' );
		$id = (int) ( new RecordMap() )->profile_for( 'rec_clean' );
		$this->assertFalse( ScanBlock::is_blocked( $id ) );
		$this->assertSame( PubState::PUBLISHED_PENDING, ( new StateRepository() )->pub_state( $id ) );

		update_post_meta( $id, ScanBlock::META_KEY, wp_slash( '[{"field":"summary","category":"email"}]' ) );
		$nobody = $this->user();
		$this->expectException( StateException::class );
		( new ScanBlock() )->clear( $id, $nobody, 'Because.' );
	}
}
