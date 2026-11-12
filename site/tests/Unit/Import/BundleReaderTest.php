<?php
/**
 * Reading a bundle and checking its manifest (REQ-237, REQ-212). Pure PHP on temporary folders.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit\Import;

use WhoIsWho\Core\Import\BundleReader;
use WhoIsWho\Core\Import\BundleRefused;
use WhoIsWho\Tests\TestCase;

final class BundleReaderTest extends TestCase {

	/**
	 * Folders to remove.
	 *
	 * @var string[]
	 */
	private array $dirs = array();

	protected function tearDown(): void {
		foreach ( $this->dirs as $dir ) {
			foreach ( glob( $dir . '/*' ) ?: array() as $file ) {
				unlink( $file );
			}
			rmdir( $dir );
		}
		parent::tearDown();
	}

	/**
	 * Build a bundle on disk.
	 *
	 * @param array<string, mixed> $override Manifest overrides.
	 * @param string[]|null        $lines    Record lines; defaults to two organisations and one individual.
	 */
	private function bundle( array $override = array(), ?array $lines = null ): string {
		$dir = sys_get_temp_dir() . '/wiw-bundle-' . bin2hex( random_bytes( 4 ) );
		mkdir( $dir );
		$this->dirs[] = $dir;
		$lines      ??= array(
			'{"record_id":"r1","entity_type":"organisation"}',
			'{"record_id":"r2","entity_type":"organisation"}',
			'{"record_id":"r3","entity_type":"individual"}',
		);
		$body         = implode( "\n", $lines ) . "\n";
		file_put_contents( $dir . '/records.jsonl', $body );
		$manifest = array_merge(
			array(
				'contract_version'        => '1.0',
				'run_id'                  => 'run_1',
				'batch_id'                => 'B1',
				'generated_at'            => '2026-11-10T09:00:00Z',
				'pipeline_version'        => 'p1',
				'source_list_version'     => 's1',
				'sources'                 => array(
					array(
						'source_id' => 'src_1',
						'name'      => 'Sample Regulator',
					),
				),
				'suppression_snapshot_id' => 'snap_1',
				'record_count'            => count( $lines ),
				'counts'                  => array(
					'organisation' => 2,
					'individual'   => 1,
					'entrepreneur' => 0,
				),
				'records_sha256'          => 'sha256:' . hash( 'sha256', $body ),
			),
			$override
		);
		file_put_contents( $dir . '/manifest.json', json_encode( $manifest ) );
		return $dir;
	}

	private function refusal_code( string $dir ): string {
		try {
			BundleReader::open( $dir );
		} catch ( BundleRefused $e ) {
			return $e->refusal_code();
		}
		return 'ACCEPTED';
	}

	public function test_REQ_237_a_good_bundle_opens_and_streams_records(): void {
		$reader = BundleReader::open( $this->bundle() );
		$this->assertSame( '1.0', $reader->manifest()['contract_version'] );
		$this->assertCount( 3, iterator_to_array( $reader->lines() ) );
		$this->assertMatchesRegularExpression( '/^sha256:[0-9a-f]{64}$/', $reader->file_hash() );
	}

	public function test_REQ_237_bad_manifest_hash_refuses_whole_bundle(): void {
		$this->assertSame( 'MANIFEST_MISMATCH', $this->refusal_code( $this->bundle( array( 'records_sha256' => 'sha256:' . str_repeat( '0', 64 ) ) ) ) );
		$dir = $this->bundle();
		file_put_contents( $dir . '/records.jsonl', '{"record_id":"r1","entity_type":"organisation"}' . "\n", FILE_APPEND );
		$this->assertSame( 'MANIFEST_MISMATCH', $this->refusal_code( $dir ), 'A changed file no longer matches the hash.' );
	}

	public function test_REQ_237_unknown_contract_version_refused(): void {
		$this->assertSame( 'CONTRACT_VERSION', $this->refusal_code( $this->bundle( array( 'contract_version' => '2.0' ) ) ) );
		$this->assertSame( 'CONTRACT_VERSION', $this->refusal_code( $this->bundle( array( 'contract_version' => '1.0-draft' ) ) ) );
	}

	public function test_REQ_212_manifest_counts_must_match_records(): void {
		$this->assertSame( 'MANIFEST_MISMATCH', $this->refusal_code( $this->bundle( array( 'record_count' => 4 ) ) ) );
		$this->assertSame(
			'MANIFEST_MISMATCH',
			$this->refusal_code(
				$this->bundle(
					array(
						'counts' => array(
							'organisation' => 1,
							'individual'   => 2,
							'entrepreneur' => 0,
						),
					)
				)
			)
		);
	}

	public function test_REQ_212_manifest_that_could_name_a_person_is_refused(): void {
		$this->assertSame( 'MANIFEST_MISMATCH', $this->refusal_code( $this->bundle( array( 'reviewer' => 'Ada Obi' ) ) ) );
		$this->assertSame(
			'MANIFEST_MISMATCH',
			$this->refusal_code(
				$this->bundle(
					array(
						'sources' => array(
							array(
								'source_id' => 'src_1',
								'name'      => 'X',
								'contact'   => 'Ada Obi',
							),
						),
					)
				)
			)
		);
	}

	public function test_REQ_237_missing_files_are_refused(): void {
		$dir = sys_get_temp_dir() . '/wiw-bundle-empty-' . bin2hex( random_bytes( 4 ) );
		mkdir( $dir );
		$this->dirs[] = $dir;
		$this->assertSame( 'MANIFEST_MISMATCH', $this->refusal_code( $dir ) );
	}
}
