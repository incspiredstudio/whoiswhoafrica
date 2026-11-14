<?php
/**
 * Contract 1.1 on the website side (REQ-139, REQ-162, REQ-203, REQ-212, REQ-221, REQ-237; contract 1.1 sections 2, 4 and 10):
 * the 1.1 worked example is accepted and its hash matches, a person needs only a name, the narrative sections are limited to
 * 6,000 characters, images are judged one by one against the licence allowlist, and a zip is checked before anything is read
 * out of it. Pure logic.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit\Import;

use WhoIsWho\Core\Import\Admin\BundleStore;
use WhoIsWho\Core\Import\BundleReader;
use WhoIsWho\Core\Import\ContentHasher;
use WhoIsWho\Core\Import\Images\ImagePolicy;
use WhoIsWho\Core\Import\RecordValidator;
use WhoIsWho\Tests\Support\V11BundleFixture;
use WhoIsWho\Tests\TestCase;

final class ContractV11Test extends TestCase {

	/**
	 * Zips to remove.
	 *
	 * @var string[]
	 */
	private array $zips = array();

	protected function tearDown(): void {
		foreach ( $this->zips as $zip ) {
			if ( is_file( $zip ) ) {
				unlink( $zip );
			}
		}
		parent::tearDown();
	}

	/**
	 * The 1.1 worked example (contract section 15.2).
	 */
	private function example(): \stdClass {
		return json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/import/worked-example-v11.json' ), false );
	}

	/**
	 * Validate a record after rehashing it.
	 *
	 * @param \stdClass $record Record.
	 * @return array<string, mixed>
	 */
	private function validate( \stdClass $record ): array {
		$record->content_hash = ContentHasher::hash_record( $record );
		return ( new RecordValidator() )->validate( (string) json_encode( $record, JSON_UNESCAPED_UNICODE ) );
	}

	public function test_REQ_221_the_contract_1_1_worked_example_hashes_as_published(): void {
		$record = $this->example();
		$this->assertSame( 'sha256:54971f5bc3a2905ffbd898f0ff86dd9847e6015a7d6d820096a8e063ee75fe49', ContentHasher::hash_record( $record ) );
		$record->images = array();
		$this->assertSame( 'sha256:7d34c9a8ac3dee2e1ddf3febeba2295b8f8cf97b92395f19972c47f5dffb219b', ContentHasher::hash_record( $record ), 'Without the image entry (contract 15.2).' );
	}

	public function test_REQ_212_the_contract_1_1_worked_example_is_accepted_with_its_image(): void {
		$out = ( new RecordValidator() )->validate( (string) json_encode( $this->example(), JSON_UNESCAPED_UNICODE ) );
		$this->assertTrue( $out['ok'], (string) $out['code'] . ' ' . $out['field'] );
		$this->assertCount( 1, $out['images'] );
		$this->assertSame( array(), $out['images_refused'] );
		$this->assertContains( '1.1', BundleReader::ACCEPTED_VERSIONS );
		$this->assertContains( '1.0', BundleReader::ACCEPTED_VERSIONS );
	}

	public function test_REQ_139_a_person_needs_only_a_name_and_may_carry_lifespan_and_field(): void {
		$record                = $this->example();
		$record->entity_type   = 'entrepreneur';
		$record->fields->field = (object) array(
			'value'      => 'Music',
			'provenance' => $record->fields->name->provenance,
		);
		$this->assertTrue( $this->validate( $record )['ok'] );
		$record->fields = (object) array( 'name' => $record->fields->name );
		$this->assertTrue( $this->validate( $record )['ok'], 'Only the name is required.' );
		$record->fields = (object) array( 'lifespan' => $this->example()->fields->lifespan );
		$out            = $this->validate( $record );
		$this->assertSame( array( 'SCHEMA', 'name' ), array( $out['code'], $out['field'] ) );
	}

	public function test_REQ_139_narrative_sections_hold_up_to_6000_characters_in_any_script(): void {
		foreach ( array( 'organisation', 'individual' ) as $type ) {
			foreach ( array( 'bio', 'achievement', 'influence', 'legacy' ) as $key ) {
				$record              = $this->example();
				$record->entity_type = $type;
				$record->fields      = (object) array( 'name' => $record->fields->name );
				if ( 'organisation' === $type ) {
					$record->fields->org_type = (object) array(
						'value'      => 'commercial-bank',
						'provenance' => $record->fields->name->provenance,
					);
				}
				$record->fields->{$key} = (object) array(
					'value'      => str_repeat( 'é', 6000 ),
					'provenance' => $this->example()->fields->bio->provenance,
				);
				$this->assertTrue( $this->validate( $record )['ok'], $type . ' ' . $key . ': 6,000 characters (12,000 bytes) fit' );
				$record->fields->{$key}->value = str_repeat( 'a', 6001 );
				$out                           = $this->validate( $record );
				$this->assertSame( array( 'SCHEMA', $key ), array( $out['code'], $out['field'] ), $type . ' ' . $key );
			}
		}
	}

	public function test_REQ_143_ai_narrative_provenance_without_passage_is_accepted(): void {
		$record = $this->example();
		$this->assertSame( 'ai', $record->fields->bio->provenance[0]->method );
		$this->assertObjectNotHasProperty( 'passage_id', $record->fields->bio->provenance[0] );
		$this->assertTrue( $this->validate( $record )['ok'] );
	}

	public function test_REQ_162_an_image_with_a_licence_off_the_list_is_refused_alone(): void {
		$record                     = $this->example();
		$record->images[0]->licence = 'GFDL-1.3';
		$out                        = $this->validate( $record );
		$this->assertTrue( $out['ok'], 'The record still imports.' );
		$this->assertSame( array(), $out['images'] );
		$this->assertSame( 'licence', $out['images_refused'][0]['reason'] );
		$this->assertSame( 'photo-1', $out['images_refused'][0]['image_id'] );
	}

	public function test_REQ_162_contract_1_0_records_still_carry_no_images(): void {
		$record                   = $this->example();
		$record->contract_version = '1.0';
		$this->assertSame( 'IMAGE_NOT_ALLOWED', $this->validate( $record )['code'] );
	}

	public function test_REQ_203_image_policy_checks_each_rule(): void {
		$good = (array) $this->example()->images[0];
		$this->assertNull( ImagePolicy::refusal( $good ) );
		foreach ( ImagePolicy::LICENCES as $licence ) {
			$this->assertNull( ImagePolicy::refusal( array_merge( $good, array( 'licence' => $licence ) ) ), $licence );
		}
		$cases = array(
			'licence' => array( 'licence' => 'CC-BY-SA-3.0-DE' ),
			'type'    => array( 'mime_type' => 'image/svg+xml' ),
			'file'    => array( 'file' => '../images/' . $good['sha256'] . '.jpg' ),
			'hash'    => array( 'sha256' => str_repeat( 'b', 64 ) ),
			'kind'    => array( 'kind' => 'banner' ),
		);
		foreach ( $cases as $reason => $change ) {
			$this->assertSame( $reason, ImagePolicy::refusal( array_merge( $good, $change ) ), $reason );
		}
		$this->assertSame( 'file', ImagePolicy::refusal( array_merge( $good, array( 'mime_type' => 'image/png' ) ) ), 'The extension must match the type.' );
		$this->assertSame( 'shape', ImagePolicy::refusal( array_merge( $good, array( 'creator' => '' ) ) ), 'A creator is needed outside the public domain.' );
		$this->assertNull(
			ImagePolicy::refusal(
				array_merge(
					$good,
					array(
						'creator' => '',
						'licence' => 'CC0-1.0',
					)
				)
			)
		);
		$this->assertSame( 'shape', ImagePolicy::refusal( array_merge( $good, array( 'extra' => 'x' ) ) ), 'Unknown keys are refused.' );
		$this->assertNull( ImagePolicy::refusal( array_merge( $good, array( 'sha256' => 'sha256:' . $good['sha256'] ) ) ), 'A prefixed hash is read too.' );
	}

	public function test_REQ_237_the_committed_v11_fixture_bundle_opens_and_validates(): void {
		$bundle = BundleReader::open( V11BundleFixture::dir() );
		$this->assertSame( '1.1', $bundle->manifest()['contract_version'] );
		$codes   = array();
		$refused = 0;
		foreach ( $bundle->lines() as $line ) {
			$out      = ( new RecordValidator() )->validate( $line );
			$codes[]  = $out['ok'] ? 'OK' : (string) $out['code'];
			$refused += count( $out['images_refused'] ?? array() );
		}
		$this->assertSame( array( 'OK', 'OK', 'OK' ), $codes );
		$this->assertSame( 1, $refused, 'The GFDL image is refused alone.' );
	}

	/**
	 * Write a zip with the given entries.
	 *
	 * @param array<string, string> $entries Name => contents.
	 */
	private function zip( array $entries ): \ZipArchive {
		$path         = sys_get_temp_dir() . '/wiw-v11-' . bin2hex( random_bytes( 4 ) ) . '.zip';
		$this->zips[] = $path;
		$zip          = new \ZipArchive();
		$zip->open( $path, \ZipArchive::CREATE );
		foreach ( $entries as $name => $body ) {
			$zip->addFromString( $name, $body );
		}
		$zip->close();
		$zip->open( $path );
		return $zip;
	}

	public function test_REQ_092_zip_paths_are_checked_before_anything_is_read(): void {
		$sha  = str_repeat( 'c', 64 );
		$plan = BundleStore::plan(
			$this->zip(
				array(
					'export-a/manifest.json'           => '{}',
					'export-a/records.jsonl'           => '',
					'export-a/images/' . $sha . '.png' => 'x',
					'export-a/images/notes.txt'        => 'ignored',
					'__MACOSX/._x'                     => 'ignored',
				)
			)
		);
		$this->assertSame( array( 'manifest.json', 'records.jsonl', 'images/' . $sha . '.png' ), array_keys( $plan ), 'One top folder is read; other files are ignored.' );
		$this->assertSame(
			array( 'manifest.json', 'records.jsonl' ),
			array_keys(
				BundleStore::plan(
					$this->zip(
						array(
							'manifest.json' => '{}',
							'records.jsonl' => '',
						)
					)
				)
			)
		);

		$bad = array(
			'parent'   => array( '../manifest.json' => '{}' ),
			'nested'   => array( 'a/../../x' => '1' ),
			'absolute' => array( '/etc/x' => '1' ),
			'drive'    => array( 'C:/x' => '1' ),
			'missing'  => array( 'records.jsonl' => '' ),
		);
		foreach ( $bad as $label => $entries ) {
			try {
				BundleStore::plan( $this->zip( $entries + array( 'records.jsonl' => '' ) ) );
				$this->fail( 'expected a refusal: ' . $label );
			} catch ( \RuntimeException $e ) {
				$this->assertNotSame( '', $e->getMessage(), $label );
			}
		}
	}

	public function test_REQ_092_a_zip_holding_a_link_is_refused(): void {
		$zip = $this->zip(
			array(
				'manifest.json' => '{}',
				'records.jsonl' => '',
				'images/link'   => '/etc/passwd',
			)
		);
		$zip->setExternalAttributesName( 'images/link', \ZipArchive::OPSYS_UNIX, ( 0120777 << 16 ) );
		$path = $zip->filename;
		$zip->close();
		$zip->open( $path );
		$this->expectException( \RuntimeException::class );
		BundleStore::plan( $zip );
	}
}
