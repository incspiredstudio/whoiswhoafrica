<?php
/**
 * Fictional import bundles for the contract tests (M1-IMP-15; REQ-237, REQ-221, REQ-142). Every record comes from the worked
 * example in docs/contracts/import-format.md section 15 (tests/fixtures/import/worked-example.json). Nothing here names a real
 * person or organisation: the only names are "Sample ..." and the only domains end in .example.
 *
 * The golden bundle is committed in tests/fixtures/import/golden/; a test proves the committed files equal what this class
 * generates, so the folder cannot drift from the generator.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Support;

use WhoIsWho\Core\Import\ContentHasher;

/**
 * Bundle fixtures.
 */
final class ImportBundleFixtures {

	/**
	 * Every refusal code in contract section 12, in the order the contract lists them.
	 */
	public const REFUSAL_CODES = array( 'CONTRACT_VERSION', 'MANIFEST_MISMATCH', 'SCHEMA', 'HASH_MISMATCH', 'VERIFICATION_FAILED', 'SUPPRESSED', 'UNSOURCED_FIELD', 'LOW_CONFIDENCE', 'FORBIDDEN_FIELD', 'IMAGE_NOT_ALLOWED' );

	/**
	 * Codes a bad manifest raises: the whole bundle is refused.
	 */
	public const BUNDLE_CODES = array( 'CONTRACT_VERSION', 'MANIFEST_MISMATCH' );

	/**
	 * The folder of the committed golden bundle.
	 */
	public static function golden_dir(): string {
		return dirname( __DIR__ ) . '/fixtures/import/golden';
	}

	/**
	 * The worked example (contract section 15) as an object.
	 */
	public static function example(): \stdClass {
		return json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/import/worked-example.json' ), false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test fixture; objects must stay distinct from arrays.
	}

	/**
	 * Encode a record as one line, recomputing the content hash unless told otherwise, so a fixture breaks one rule only.
	 *
	 * @param \stdClass $record Record.
	 * @param bool      $rehash Recompute the hash after the change.
	 */
	public static function line( \stdClass $record, bool $rehash = true ): string {
		if ( $rehash ) {
			$record->content_hash = ContentHasher::hash_record( $record );
		}
		return (string) json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- plain PHP.
	}

	/**
	 * The golden record line: the worked example, untouched.
	 */
	public static function golden_line(): string {
		return self::line( self::example(), false );
	}

	/**
	 * A manifest for the given record lines (contract section 2.1).
	 *
	 * @param string[]             $lines    Record lines.
	 * @param array<string, mixed> $override Fields to replace.
	 * @return array<string, mixed>
	 */
	public static function manifest( array $lines, array $override = array() ): array {
		$body   = implode( "\n", $lines ) . "\n";
		$counts = array();
		foreach ( $lines as $line ) {
			$type            = (string) ( json_decode( $line, true )['entity_type'] ?? 'organisation' );
			$type            = in_array( $type, array( 'organisation', 'individual', 'entrepreneur' ), true ) ? $type : 'organisation'; // The manifest must stay valid when a fixture breaks the record.
			$counts[ $type ] = ( $counts[ $type ] ?? 0 ) + 1;
		}
		return array_merge(
			array(
				'contract_version'        => '1.0',
				'run_id'                  => 'run_20261110_a',
				'batch_id'                => 'B1',
				'generated_at'            => '2026-11-10T09:00:00Z',
				'pipeline_version'        => 'fixture-1',
				'source_list_version'     => 'fixture-1',
				'sources'                 => array(
					array(
						'source_id' => 'src_sample_regulator',
						'name'      => 'Sample Regulator',
					),
				),
				'suppression_snapshot_id' => 'snap_fixture_1',
				'record_count'            => count( $lines ),
				'counts'                  => $counts,
				'records_sha256'          => 'sha256:' . hash( 'sha256', $body ),
			),
			$override
		);
	}

	/**
	 * Write a bundle folder.
	 *
	 * @param string               $dir               Existing folder.
	 * @param string[]             $lines             Record lines.
	 * @param array<string, mixed> $manifest_override Manifest fields to replace.
	 */
	public static function write( string $dir, array $lines, array $manifest_override = array() ): void {
		file_put_contents( $dir . '/records.jsonl', implode( "\n", $lines ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents -- test fixture.
		file_put_contents( $dir . '/manifest.json', json_encode( self::manifest( $lines, $manifest_override ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents,WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test fixture.
	}

	/**
	 * The failing record fixtures: refusal code => record line. Each breaks exactly one rule of the golden record.
	 * SUPPRESSED needs a matching suppression entry (see suppressed_name()); the line itself is valid.
	 *
	 * @return array<string, string>
	 */
	public static function failing_records(): array {
		$out = array();

		$r                       = self::example();
		$r->contract_version     = '9.9';
		$out['CONTRACT_VERSION'] = self::line( $r );

		$r             = self::example();
		$r->state      = 'published'; // A record MUST NOT carry a state, publish, visibility or status field (contract section 3).
		$out['SCHEMA'] = self::line( $r );

		$r                      = self::example();
		$r->fields->name->value = 'Sample Bank Changed After Hashing Ltd';
		$out['HASH_MISMATCH']   = self::line( $r, false );

		$r                                       = self::example();
		$r->verification->checks->sensitive_scan = 'fail';
		$out['VERIFICATION_FAILED']              = self::line( $r );

		$r                 = self::example();
		$out['SUPPRESSED'] = self::line( $r );

		$r                                = self::example();
		$r->fields->regulator->provenance = array();
		$out['UNSOURCED_FIELD']           = self::line( $r );

		$r = self::example();
		$r->fields->regulator->provenance[0]->confidence = 'low';
		$out['LOW_CONFIDENCE']                           = self::line( $r );

		$r                      = self::example();
		$r->fields->phone       = (object) array(
			'value'      => '0000-fixture',
			'provenance' => $r->fields->name->provenance,
		);
		$out['FORBIDDEN_FIELD'] = self::line( $r );

		$r                        = self::example();
		$r->images                = array( (object) array( 'image_id' => 'img_fixture_1' ) );
		$out['IMAGE_NOT_ALLOWED'] = self::line( $r );

		return $out;
	}

	/**
	 * The organisation name in the golden record, which the SUPPRESSED fixture matches.
	 */
	public static function suppressed_name(): string {
		return (string) self::example()->fields->name->value;
	}

	/**
	 * Failing manifests: refusal code => manifest overrides, for the codes that refuse the whole bundle.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function failing_manifests(): array {
		return array(
			'CONTRACT_VERSION'  => array( 'contract_version' => '9.9' ),
			'MANIFEST_MISMATCH' => array( 'records_sha256' => 'sha256:' . str_repeat( '0', 64 ) ),
		);
	}
}
