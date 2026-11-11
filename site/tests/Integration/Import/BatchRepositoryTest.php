<?php
/**
 * The import batch row records the contract version and file hash (REQ-237). Needs the WordPress test suite; plain TestCase
 * because it needs the real table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Import;

use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\BundleReader;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

final class BatchRepositoryTest extends TestCase {

	/**
	 * Folder of the bundle.
	 *
	 * @var string
	 */
	private string $dir = '';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$wpdb->query( 'TRUNCATE TABLE ' . BatchRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
		$this->dir = sys_get_temp_dir() . '/wiw-batch-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->dir );
		$body = '{"record_id":"r1","entity_type":"organisation"}' . "\n";
		file_put_contents( $this->dir . '/records.jsonl', $body );
		file_put_contents(
			$this->dir . '/manifest.json',
			wp_json_encode(
				array(
					'contract_version'        => '1.0',
					'run_id'                  => 'run_1',
					'batch_id'                => 'B-test-1',
					'generated_at'            => '2026-11-10T09:00:00Z',
					'pipeline_version'        => 'p1',
					'source_list_version'     => 's1',
					'sources'                 => array(),
					'suppression_snapshot_id' => 'snap_1',
					'record_count'            => 1,
					'counts'                  => array( 'organisation' => 1 ),
					'records_sha256'          => 'sha256:' . hash( 'sha256', $body ),
				)
			)
		);
	}

	protected function tearDown(): void {
		foreach ( glob( $this->dir . '/*' ) ?: array() as $file ) {
			unlink( $file );
		}
		rmdir( $this->dir );
		parent::tearDown();
	}

	public function test_REQ_237_batch_row_records_contract_version_and_file_hash(): void {
		$repo   = new BatchRepository();
		$bundle = BundleReader::open( $this->dir );
		$repo->register( $bundle, 3, array( 'import_as_not_public' => true ), 'bundle.zip' );
		$row = $repo->get( 'B-test-1' );
		$this->assertNotNull( $row );
		$this->assertSame( '1.0', $row['contract_version'] );
		$this->assertSame( $bundle->file_hash(), $row['file_hash'] );
		$this->assertSame( 'checked', $row['status'] );
		$this->assertSame( '1', $row['record_count'] );
		$this->assertSame( '3', $row['created_by'] );
		$this->assertSame( '{"import_as_not_public":true}', $row['settings_snapshot'] );

		$this->expectException( \RuntimeException::class );
		$repo->register( $bundle, 3 );
	}
}
