<?php
/**
 * The import contract (M1-IMP-15; REQ-237, REQ-221, REQ-142). Fictional bundles built from the worked example in contract
 * section 15: the golden bundle imports as CREATED and then UNCHANGED, and every refusal code has one failing fixture that
 * produces exactly that code through the real dry run and apply. Needs the WordPress test suite and the real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Import;

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
use WhoIsWho\Core\Import\BundleRefused;
use WhoIsWho\Core\Import\DryRun\DryRun;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\Support\ImportBundleFixtures as Fixtures;
use WhoIsWho\Tests\TestCase;

final class ImportContractTest extends TestCase {

	/**
	 * Bundle folders to remove.
	 *
	 * @var string[]
	 */
	private array $dirs = array();

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
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Remove every profile, map row, result and batch a test created.
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
	 * Write a bundle to a temporary folder and register its batch.
	 *
	 * @param string[]             $lines    Record lines.
	 * @param string               $batch_id Batch id.
	 * @param array<string, mixed> $override Manifest overrides.
	 */
	private function bundle( array $lines, string $batch_id, array $override = array() ): BundleReader {
		$dir = sys_get_temp_dir() . '/wiw-contract-' . bin2hex( random_bytes( 4 ) );
		mkdir( $dir );
		$this->dirs[] = $dir;
		Fixtures::write( $dir, $lines, array_merge( array( 'batch_id' => $batch_id ), $override ) );
		$bundle = BundleReader::open( $dir );
		( new BatchRepository() )->register( $bundle, 7, array( 'import_all_not_public' => false ), 'bundle' );
		return $bundle;
	}

	/**
	 * The suppression list.
	 */
	private function suppression(): Suppression {
		return new Suppression( AuditLogger::for_site() );
	}

	/**
	 * Preview a bundle.
	 *
	 * @param BundleReader $bundle   Bundle.
	 * @param string       $batch_id Batch id.
	 * @return array{counts: array<string, int>, records: array<int, array<string, mixed>>}
	 */
	private function dry( BundleReader $bundle, string $batch_id ): array {
		return ( new DryRun( $this->suppression() ) )->run( $bundle, $batch_id );
	}

	/**
	 * Apply a bundle.
	 *
	 * @param BundleReader $bundle   Bundle.
	 * @param string       $batch_id Batch id.
	 * @return array<string, mixed>
	 */
	private function apply( BundleReader $bundle, string $batch_id ): array {
		return ( new ChunkApplier( new ProfileApplier( $this->suppression() ) ) )->run( $bundle, $batch_id );
	}

	public function test_REQ_237_the_committed_golden_bundle_matches_the_generator(): void {
		$dir = Fixtures::golden_dir();
		$this->assertSame( Fixtures::golden_line() . "\n", (string) file_get_contents( $dir . '/records.jsonl' ), 'Regenerate with ImportBundleFixtures::write( golden_dir(), array( golden_line() ) ).' );
		$manifest = json_decode( (string) file_get_contents( $dir . '/manifest.json' ), true );
		$this->assertSame( 'sha256:' . hash_file( 'sha256', $dir . '/records.jsonl' ), $manifest['records_sha256'], 'The manifest carries the hash of records.jsonl (contract section 2.1).' );
		$this->assertSame( '1.0', $manifest['contract_version'] );
		$this->assertSame( 1, $manifest['record_count'] );
	}

	public function test_REQ_237_golden_bundle_imports_then_unchanged(): void {
		$golden = BundleReader::open( Fixtures::golden_dir() );
		( new BatchRepository() )->register( $golden, 7, array( 'import_all_not_public' => false ), 'golden' );
		$preview = $this->dry( $golden, 'B1' );
		$this->assertSame( array( 'CREATED' => 1 ), $preview['counts'], 'The dry run says CREATED and writes nothing.' );
		$this->assertNull( ( new RecordMap() )->profile_for( 'rec_org_0001' ) );

		$first = $this->apply( $golden, 'B1' );
		$this->assertSame( array( 'CREATED' => 1 ), $first['counts'] );
		$this->assertNotNull( ( new RecordMap() )->profile_for( 'rec_org_0001' ) );

		// The same record, hash and all, in a later batch: nothing changes.
		$again = $this->bundle( array( Fixtures::golden_line() ), 'B2' );
		$this->assertSame( array( 'UNCHANGED' => 1 ), $this->dry( $again, 'B2' )['counts'] );
		$second = $this->apply( $again, 'B2' );
		$this->assertSame( array( 'UNCHANGED' => 1 ), $second['counts'] );
		$this->assertSame( array( array( 'rec_org_0001', 'UNCHANGED' ) ), ( new ResultRepository() )->pairs( 'B2' ) );
	}

	public function test_REQ_221_each_refusal_code_has_a_failing_fixture(): void {
		$records   = Fixtures::failing_records();
		$manifests = Fixtures::failing_manifests();
		foreach ( Fixtures::REFUSAL_CODES as $code ) {
			$this->assertTrue( isset( $records[ $code ] ) || isset( $manifests[ $code ] ), "No failing fixture for $code." );
		}
		$this->assertSame( count( Fixtures::REFUSAL_CODES ), count( array_unique( array_merge( array_keys( $records ), array_keys( $manifests ) ) ) ), 'No fixture for a code the contract does not list.' );
	}

	public function test_REQ_221_a_failing_record_fixture_produces_exactly_its_code(): void {
		$this->suppression()->add_declined_nominee( Fixtures::suppressed_name(), 'organisation' );
		$n = 0;
		foreach ( Fixtures::failing_records() as $code => $line ) {
			++$n;
			$batch  = 'B-fix-' . $n;
			$bundle = $this->bundle( array( $line ), $batch );
			$out    = $this->dry( $bundle, $batch );
			$this->assertSame( array( $code => 1 ), $out['counts'], "Dry run of the $code fixture." );
			$applied = $this->apply( $bundle, $batch );
			$this->assertSame( array( $code => 1 ), $applied['counts'], "Apply of the $code fixture." );
			$this->assertNull( ( new RecordMap() )->profile_for( 'rec_org_0001' ), "The $code fixture must not create a profile." );
		}
	}

	public function test_REQ_221_a_failing_manifest_fixture_refuses_the_whole_bundle_with_its_code(): void {
		foreach ( Fixtures::failing_manifests() as $code => $override ) {
			$dir = sys_get_temp_dir() . '/wiw-contract-' . bin2hex( random_bytes( 4 ) );
			mkdir( $dir );
			$this->dirs[] = $dir;
			Fixtures::write( $dir, array( Fixtures::golden_line() ), $override );
			try {
				BundleReader::open( $dir );
				$this->fail( "The $code manifest was accepted." );
			} catch ( BundleRefused $e ) {
				$this->assertSame( $code, $e->refusal_code() );
			}
		}
	}

	public function test_REQ_142_fixtures_contain_no_personal_data(): void {
		$text = (string) file_get_contents( Fixtures::golden_dir() . '/records.jsonl' ) . (string) file_get_contents( Fixtures::golden_dir() . '/manifest.json' ) . implode( "\n", Fixtures::failing_records() );
		$this->assertSame( 0, preg_match( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}/i', $text ), 'No email address.' );
		$this->assertSame( 0, preg_match( '/\+\d[\d\s().-]{7,}\d|\(\d{3}\)\s?\d{3}/', $text ), 'No phone-like number.' );
		preg_match_all( '#https?://([^/"\s]+)#i', $text, $hosts );
		foreach ( array_unique( $hosts[1] ) as $host ) {
			$this->assertMatchesRegularExpression( '/\.example$/', $host, "Only .example hosts: $host" );
		}
		$this->assertStringContainsString( 'Sample', $text );
	}
}
