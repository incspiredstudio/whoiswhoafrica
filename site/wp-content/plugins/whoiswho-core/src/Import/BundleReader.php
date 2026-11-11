<?php
/**
 * Reads an export bundle (manifest.json and records.jsonl) and checks the manifest against the file (contract sections 2 and
 * 12, REQ-237, REQ-212): a known contract version, the record count, the counts per entity type and records_sha256 over the
 * bytes of records.jsonl. Any mismatch refuses the whole bundle. Records are streamed line by line, never loaded together.
 * Plain PHP, no WordPress calls.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import;

/**
 * Bundle reader.
 */
final class BundleReader {

	/**
	 * Contract versions this importer accepts.
	 */
	public const ACCEPTED_VERSIONS = array( '1.0' );

	public const MANIFEST_KEYS = array( 'contract_version', 'run_id', 'batch_id', 'generated_at', 'pipeline_version', 'source_list_version', 'sources', 'suppression_snapshot_id', 'record_count', 'counts', 'records_sha256' );

	public const COUNT_KEYS = array( 'organisation', 'individual', 'entrepreneur' );

	/**
	 * Bundle folder.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * The manifest.
	 *
	 * @var array<string, mixed>
	 */
	private array $manifest = array();

	/**
	 * Use open().
	 *
	 * @param string $dir Folder.
	 */
	private function __construct( string $dir ) {
		$this->dir = rtrim( $dir, '/\\' );
	}

	/**
	 * Open a bundle folder and check its manifest.
	 *
	 * @param string $dir Folder holding manifest.json and records.jsonl.
	 * @throws BundleRefused When the bundle must be refused whole.
	 */
	public static function open( string $dir ): self {
		$reader = new self( $dir );
		$reader->check();
		return $reader;
	}

	/**
	 * The checked manifest.
	 *
	 * @return array<string, mixed>
	 */
	public function manifest(): array {
		return $this->manifest;
	}

	/**
	 * The sha256 of records.jsonl as "sha256:" plus hex.
	 */
	public function file_hash(): string {
		return (string) $this->manifest['records_sha256'];
	}

	/**
	 * Stream the records: line number => raw JSON line. The caller decodes and validates each.
	 *
	 * @return \Generator<int, string>
	 * @throws BundleRefused When the file cannot be read.
	 */
	public function lines(): \Generator {
		$handle = fopen( $this->dir . '/records.jsonl', 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming a large local file.
		if ( false === $handle ) {
			throw new BundleRefused( 'MANIFEST_MISMATCH', 'records.jsonl cannot be read.' );
		}
		try {
			$n = 0;
			while ( ! feof( $handle ) ) {
				$line = fgets( $handle );
				if ( false === $line ) {
					break;
				}
				$line = rtrim( $line, "\r\n" );
				if ( '' === $line ) {
					continue;
				}
				++$n;
				yield $n => $line;
			}
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- as above.
		}
	}

	/**
	 * Run every manifest check.
	 *
	 * @throws BundleRefused On the first problem.
	 */
	private function check(): void {
		$manifest_path = $this->dir . '/manifest.json';
		$records_path  = $this->dir . '/records.jsonl';
		if ( ! is_readable( $manifest_path ) || ! is_readable( $records_path ) ) {
			throw new BundleRefused( 'MANIFEST_MISMATCH', 'The bundle needs manifest.json and records.jsonl.' );
		}
		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.WP.AlternativeFunctions.json_encode_json_encode -- local bundle file, plain PHP.
		if ( ! is_array( $manifest ) ) {
			throw new BundleRefused( 'MANIFEST_MISMATCH', 'The manifest is not valid JSON.' );
		}
		$version = $manifest['contract_version'] ?? null;
		if ( ! is_string( $version ) || ! in_array( $version, self::ACCEPTED_VERSIONS, true ) ) {
			throw new BundleRefused( 'CONTRACT_VERSION', 'This contract version is not accepted.' );
		}
		$this->check_shape( $manifest );
		$this->check_file( $manifest, $records_path );
		$this->manifest = $manifest;
	}

	/**
	 * The manifest has exactly the contract's keys; anything else could carry a name and is refused.
	 *
	 * @param array<string, mixed> $manifest Manifest.
	 * @throws BundleRefused On an unknown key or a wrong type.
	 */
	private function check_shape( array $manifest ): void {
		foreach ( self::MANIFEST_KEYS as $key ) {
			if ( ! array_key_exists( $key, $manifest ) ) {
				throw new BundleRefused( 'MANIFEST_MISMATCH', 'The manifest is missing a required key.' );
			}
		}
		if ( array() !== array_diff( array_keys( $manifest ), self::MANIFEST_KEYS ) ) {
			throw new BundleRefused( 'MANIFEST_MISMATCH', 'The manifest has a key the contract does not define.' );
		}
		foreach ( array( 'run_id', 'batch_id', 'generated_at', 'pipeline_version', 'source_list_version', 'suppression_snapshot_id' ) as $key ) {
			if ( ! is_string( $manifest[ $key ] ) || '' === $manifest[ $key ] ) {
				throw new BundleRefused( 'MANIFEST_MISMATCH', 'A manifest value has the wrong type.' );
			}
		}
		if ( ! is_int( $manifest['record_count'] ) || $manifest['record_count'] < 0 || ! is_array( $manifest['counts'] ) || ! is_array( $manifest['sources'] ) ) {
			throw new BundleRefused( 'MANIFEST_MISMATCH', 'A manifest value has the wrong type.' );
		}
		foreach ( $manifest['sources'] as $source ) {
			if ( ! is_array( $source ) || array( 'name', 'source_id' ) !== self::sorted_keys( $source ) || ! is_string( $source['name'] ) || ! is_string( $source['source_id'] ) ) {
				throw new BundleRefused( 'MANIFEST_MISMATCH', 'The sources list may hold only a source id and a display name.' );
			}
		}
		if ( array() !== array_diff( array_keys( $manifest['counts'] ), self::COUNT_KEYS ) ) {
			throw new BundleRefused( 'MANIFEST_MISMATCH', 'The counts hold an unknown entity type.' );
		}
	}

	/**
	 * Check the hash, the record count and the per-type counts against the file.
	 *
	 * @param array<string, mixed> $manifest Manifest.
	 * @param string               $path     records.jsonl.
	 * @throws BundleRefused On any mismatch.
	 */
	private function check_file( array $manifest, string $path ): void {
		$expected = (string) $manifest['records_sha256'];
		$actual   = 'sha256:' . hash_file( 'sha256', $path );
		if ( 1 !== preg_match( '/^sha256:[0-9a-f]{64}$/', $expected ) || ! hash_equals( $actual, $expected ) ) {
			throw new BundleRefused( 'MANIFEST_MISMATCH', 'The records file does not match records_sha256.' );
		}
		$total   = 0;
		$by_type = array_fill_keys( self::COUNT_KEYS, 0 );
		foreach ( $this->lines() as $line ) {
			++$total;
			$record = json_decode( $line, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- plain PHP.
			$type   = is_array( $record ) && isset( $record['entity_type'] ) && is_string( $record['entity_type'] ) ? $record['entity_type'] : '';
			if ( isset( $by_type[ $type ] ) ) {
				++$by_type[ $type ];
			}
		}
		if ( $total !== $manifest['record_count'] ) {
			throw new BundleRefused( 'MANIFEST_MISMATCH', 'The record count does not match the file.' );
		}
		foreach ( self::COUNT_KEYS as $key ) {
			if ( (int) ( $manifest['counts'][ $key ] ?? 0 ) !== $by_type[ $key ] ) {
				throw new BundleRefused( 'MANIFEST_MISMATCH', 'The per-type counts do not match the file.' );
			}
		}
	}

	/**
	 * Sorted keys of an array.
	 *
	 * @param array<mixed> $value Array.
	 * @return array<int, int|string>
	 */
	private static function sorted_keys( array $value ): array {
		$keys = array_keys( $value );
		sort( $keys );
		return $keys;
	}
}
