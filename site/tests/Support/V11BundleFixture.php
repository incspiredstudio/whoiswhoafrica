<?php
/**
 * A small fictional contract 1.1 bundle (REQ-237, REQ-162, REQ-139; contract 1.1 sections 2, 4.3 and 10): a deceased
 * individual with a lifespan, a field, the four narrative sections and an open-licence photo; an entrepreneur with only a name
 * and two sections and an image whose licence is not allowed; and an organisation with the four sections and a public-domain
 * logo. Nobody here is real: the names are "Sample" and "Example" names and every address ends in .example.
 *
 * The committed bundle in tests/fixtures/import/v11/ was written by build(); the tests read the committed files.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Support;

use WhoIsWho\Core\Import\ContentHasher;

/**
 * Contract 1.1 bundle fixture.
 */
final class V11BundleFixture {

	public const BATCH = 'B-v11-fixture';

	/**
	 * The committed bundle folder.
	 */
	public static function dir(): string {
		return dirname( __DIR__ ) . '/fixtures/import/v11';
	}

	/**
	 * Zip the committed bundle (inside one top folder, as the export writes it) and return the zip path.
	 *
	 * @param string $target Zip path to write.
	 */
	public static function zip( string $target ): string {
		$zip = new \ZipArchive();
		$zip->open( $target, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		$top = 'export-run_v11-' . self::BATCH . '/';
		$zip->addFile( self::dir() . '/manifest.json', $top . 'manifest.json' );
		$zip->addFile( self::dir() . '/records.jsonl', $top . 'records.jsonl' );
		foreach ( (array) glob( self::dir() . '/images/*' ) as $file ) {
			$zip->addFile( (string) $file, $top . 'images/' . basename( (string) $file ) );
		}
		$zip->close();
		return $target;
	}

	/**
	 * Write the bundle (images drawn with GD) into a folder.
	 *
	 * @param string $dir Existing folder.
	 */
	public static function build( string $dir ): void {
		if ( ! is_dir( $dir . '/images' ) ) {
			mkdir( $dir . '/images', 0777, true );
		}
		$photo = self::png( $dir, 160, 200, array( 120, 90, 60 ) );
		$logo  = self::png( $dir, 200, 200, array( 30, 60, 120 ) );
		$other = self::png( $dir, 120, 120, array( 90, 120, 60 ) );
		$lines = array(
			self::line( self::person( $photo ) ),
			self::line( self::entrepreneur( $other ) ),
			self::line( self::organisation( $logo ) ),
		);
		$body  = implode( "\n", $lines ) . "\n";
		file_put_contents( $dir . '/records.jsonl', $body );
		$manifest = array(
			'contract_version'        => '1.1',
			'run_id'                  => 'run_v11',
			'batch_id'                => self::BATCH,
			'generated_at'            => '2026-11-14T09:00:00Z',
			'pipeline_version'        => 'fixture-1.1',
			'source_list_version'     => 'fixture-1',
			'sources'                 => array(
				array(
					'source_id' => 'src_sample_archive',
					'name'      => 'Sample Archive',
				),
			),
			'suppression_snapshot_id' => 'snap_fixture_1',
			'record_count'            => 3,
			'counts'                  => array(
				'organisation' => 1,
				'individual'   => 1,
				'entrepreneur' => 1,
			),
			'records_sha256'          => 'sha256:' . hash( 'sha256', $body ),
		);
		file_put_contents( $dir . '/manifest.json', json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
	}

	/**
	 * Draw a PNG, save it as images/<sha256>.png and return its entry facts.
	 *
	 * @param string $dir  Bundle folder.
	 * @param int    $w    Width.
	 * @param int    $h    Height.
	 * @param int[]  $rgb  Colour.
	 * @return array{file: string, sha256: string, width: int, height: int}
	 */
	private static function png( string $dir, int $w, int $h, array $rgb ): array {
		$img = imagecreatetruecolor( $w, $h );
		imagefill( $img, 0, 0, (int) imagecolorallocate( $img, $rgb[0], $rgb[1], $rgb[2] ) );
		imagefilledellipse( $img, (int) ( $w / 2 ), (int) ( $h / 2 ), (int) ( $w / 2 ), (int) ( $h / 2 ), (int) imagecolorallocate( $img, 240, 230, 210 ) );
		ob_start();
		imagepng( $img );
		$bytes = (string) ob_get_clean();
		$sha   = hash( 'sha256', $bytes );
		file_put_contents( $dir . '/images/' . $sha . '.png', $bytes );
		return array(
			'file'   => 'images/' . $sha . '.png',
			'sha256' => $sha,
			'width'  => $w,
			'height' => $h,
		);
	}

	/**
	 * One field with provenance.
	 *
	 * @param mixed  $value  Value.
	 * @param string $method Method.
	 * @return array<string, mixed>
	 */
	private static function field( $value, string $method = 'api' ): array {
		return array(
			'value'      => $value,
			'provenance' => array(
				array(
					'source_id'    => 'src_sample_archive',
					'source_url'   => 'https://archive.example/sample',
					'retrieved_at' => '2026-11-12T09:00:00Z',
					'confidence'   => 'ai' === $method ? 'medium' : 'high',
					'method'       => $method,
					'source_hash'  => 'sha256:' . str_repeat( 'a', 64 ),
				),
			),
		);
	}

	/**
	 * An image entry.
	 *
	 * @param array<string, mixed> $file    File facts.
	 * @param string               $id      Image id.
	 * @param string               $kind    photo or logo.
	 * @param string               $licence Licence id.
	 * @param string               $creator Creator.
	 * @return array<string, mixed>
	 */
	private static function image( array $file, string $id, string $kind, string $licence, string $creator ): array {
		return array(
			'image_id'         => $id,
			'file'             => $file['file'],
			'sha256'           => $file['sha256'],
			'mime_type'        => 'image/png',
			'width'            => $file['width'],
			'height'           => $file['height'],
			'kind'             => $kind,
			'source_url'       => 'https://images.example/file/' . $id,
			'retrieved_at'     => '2026-11-12T09:05:00Z',
			'creator'          => $creator,
			'licence'          => $licence,
			'licence_url'      => 'https://licences.example/' . strtolower( $licence ),
			'attribution_text' => '' === $creator ? 'Public domain' : $creator . ', ' . $licence,
		);
	}

	/**
	 * The parts every record shares.
	 *
	 * @param string               $id     Record id.
	 * @param string               $type   Entity type.
	 * @param string               $crit   Criterion code.
	 * @param array<string, mixed> $fields Fields.
	 * @param array<int, mixed>    $images Images.
	 * @return array<string, mixed>
	 */
	private static function record( string $id, string $type, string $crit, array $fields, array $images ): array {
		return array(
			'contract_version' => '1.1',
			'record_id'        => $id,
			'record_version'   => 1,
			'run_id'           => 'run_v11',
			'batch_id'         => self::BATCH,
			'entity_type'      => $type,
			'tier'             => 'T3',
			'country'          => 'GH',
			'external_ids'     => new \stdClass(),
			'inclusion'        => array(
				'criterion_code' => $crit,
				'evidence_url'   => 'https://archive.example/sample',
			),
			'fields'           => $fields,
			'claims'           => array(),
			'passages'         => array(),
			'images'           => $images,
			'ai'               => array(
				'ai_assisted'    => true,
				'model'          => 'sample-model',
				'prompt_version' => 'reword-v1',
			),
			'flags'            => array(
				'probable_duplicate' => false,
				'duplicate_of'       => array(),
				'flag_reasons'       => array(),
			),
			'verification'     => array(
				'run_id'         => 'run_v11',
				'verified_at'    => '2026-11-12T11:00:00Z',
				'checks'         => array(
					'approved_source'    => 'pass',
					'source_url_ok'      => 'pass',
					'exact_quotes'       => 'pass',
					'allowlisted_fields' => 'pass',
					'sensitive_scan'     => 'pass',
					'suppression'        => 'pass',
					'duplicates'         => 'clear',
				),
				'claims_kept'    => 0,
				'claims_dropped' => 0,
				'fields_dropped' => array(),
			),
			'content_hash'     => '',
		);
	}

	/**
	 * The deceased individual.
	 *
	 * @param array<string, mixed> $photo Photo file.
	 * @return array<string, mixed>
	 */
	private static function person( array $photo ): array {
		return self::record(
			'rec_ind_v11_1',
			'individual',
			'HIST',
			array(
				'name'        => self::field( 'Ama Fixture Sample' ),
				'lifespan'    => self::field( '1924–2013' ),
				'field'       => self::field( 'Music' ),
				'bio'         => self::field( "Ama Fixture Sample was a fictional singer and bandleader.\n\nShe led a sample orchestra for thirty years.", 'ai' ),
				'achievement' => self::field( 'She recorded twelve sample albums.', 'ai' ),
				'influence'   => self::field( 'Younger sample singers took up her style.', 'ai' ),
				'legacy'      => self::field( 'A sample music school carries her name.', 'ai' ),
			),
			array( self::image( $photo, 'photo-1', 'photo', 'CC-BY-SA-4.0', 'Sample Photographer' ) )
		);
	}

	/**
	 * The entrepreneur: only a name and two sections; the image licence is not allowed.
	 *
	 * @param array<string, mixed> $file Image file.
	 * @return array<string, mixed>
	 */
	private static function entrepreneur( array $file ): array {
		return self::record(
			'rec_ent_v11_1',
			'entrepreneur',
			'LEAD',
			array(
				'name'        => self::field( 'Kofi Fixture Example' ),
				'bio'         => self::field( 'Kofi Fixture Example is a fictional founder.', 'ai' ),
				'achievement' => self::field( 'He built a sample delivery company.', 'ai' ),
			),
			array( self::image( $file, 'photo-1', 'photo', 'GFDL-1.3', 'Sample Photographer' ) )
		);
	}

	/**
	 * The organisation with a public-domain logo.
	 *
	 * @param array<string, mixed> $logo Logo file.
	 * @return array<string, mixed>
	 */
	private static function organisation( array $logo ): array {
		return self::record(
			'rec_org_v11_1',
			'organisation',
			'O5',
			array(
				'name'        => self::field( 'Sample Fixture Cooperative Ltd' ),
				'org_type'    => self::field( 'commercial-bank' ),
				'bio'         => self::field( "Sample Fixture Cooperative is a fictional lender.\n\nIt serves sample farmers.", 'ai' ),
				'achievement' => self::field( 'It opened forty sample branches.', 'ai' ),
				'influence'   => self::field( 'Other sample lenders copied its model.', 'ai' ),
				'legacy'      => self::field( 'Its savings scheme is still used.', 'ai' ),
			),
			array( self::image( $logo, 'logo-1', 'logo', 'CC0-1.0', '' ) )
		);
	}

	/**
	 * Encode a record with its content hash.
	 *
	 * @param array<string, mixed> $record Record.
	 */
	private static function line( array $record ): string {
		$object               = json_decode( (string) json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), false );
		$object->content_hash = ContentHasher::hash_record( $object );
		return (string) json_encode( $object, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}
