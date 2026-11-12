<?php
/**
 * The per-record validator (REQ-140, REQ-142, REQ-143, REQ-212, REQ-214, REQ-221). Pure logic.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit\Import;

use WhoIsWho\Core\Import\ContentHasher;
use WhoIsWho\Core\Import\RecordValidator;
use WhoIsWho\Tests\TestCase;

final class RecordValidatorTest extends TestCase {

	/**
	 * The worked example as an object.
	 */
	private function example(): \stdClass {
		return json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/import/worked-example.json' ), false );
	}

	/**
	 * Encode a record, optionally recomputing the content hash after a change, so each test hits one rule only.
	 *
	 * @param \stdClass $record Record.
	 * @param bool      $rehash Recompute the hash.
	 */
	private function line( \stdClass $record, bool $rehash = true ): string {
		if ( $rehash ) {
			$record->content_hash = ContentHasher::hash_record( $record );
		}
		return (string) json_encode( $record ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- plain PHP in a pure unit test.
	}

	/**
	 * Validate and return the code.
	 *
	 * @param string $line Record line.
	 */
	private function code( string $line ): ?string {
		return ( new RecordValidator() )->validate( $line )['code'];
	}

	public function test_REQ_212_the_worked_example_is_accepted(): void {
		$result = ( new RecordValidator() )->validate( $this->line( $this->example(), false ) );
		$this->assertTrue( $result['ok'] );
		$this->assertNull( $result['code'] );
		$this->assertSame( 'rec_org_0001', $result['record_id'] );
		$this->assertSame( 'Sample Bank Ltd', $result['record']['fields']['name']['value'] );
	}

	public function test_REQ_140_forbidden_field_is_refused(): void {
		$record                = $this->example();
		$record->fields->phone = (object) array(
			'value'      => '+234 800 000 0000',
			'provenance' => $record->fields->name->provenance,
		);
		$result                = ( new RecordValidator() )->validate( $this->line( $record ) );
		$this->assertSame( 'FORBIDDEN_FIELD', $result['code'] );
		$this->assertSame( 'phone', $result['field'], 'The field name is reported, never its value.' );
		$this->assertStringNotContainsString( '800', (string) json_encode( $result ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test.
	}

	public function test_REQ_214_organisation_field_naming_a_person_is_refused(): void {
		$record                  = $this->example();
		$record->fields->founder = (object) array(
			'value'      => 'A Person',
			'provenance' => $record->fields->name->provenance,
		);
		$this->assertSame( 'FORBIDDEN_FIELD', $this->code( $this->line( $record ) ) );
	}

	public function test_REQ_143_field_without_provenance_is_refused(): void {
		$record                                = $this->example();
		$record->fields->regulator->provenance = array();
		$this->assertSame( 'UNSOURCED_FIELD', $this->code( $this->line( $record ) ) );
		$record = $this->example();
		unset( $record->fields->regulator->provenance );
		$this->assertSame( 'UNSOURCED_FIELD', $this->code( $this->line( $record ) ) );
	}

	public function test_REQ_143_low_confidence_field_is_refused(): void {
		$record = $this->example();
		$record->fields->regulator->provenance[0]->confidence = 'low';
		$this->assertSame( 'LOW_CONFIDENCE', $this->code( $this->line( $record ) ) );
	}

	public function test_REQ_221_content_hash_mismatch_is_refused(): void {
		$record                      = $this->example();
		$record->fields->name->value = 'Changed After Hashing Ltd';
		$this->assertSame( 'HASH_MISMATCH', $this->code( $this->line( $record, false ) ) );
	}

	public function test_REQ_212_failed_verification_report_is_refused(): void {
		foreach ( array( 'approved_source', 'source_url_ok', 'exact_quotes', 'allowlisted_fields', 'sensitive_scan', 'suppression' ) as $check ) {
			$record                                 = $this->example();
			$record->verification->checks->{$check} = 'fail';
			$this->assertSame( 'VERIFICATION_FAILED', $this->code( $this->line( $record ) ), $check );
		}
		$record                                   = $this->example();
		$record->verification->checks->duplicates = 'flagged';
		$this->assertNull( $this->code( $this->line( $record ) ), 'Flagged duplicates are allowed.' );
		$record->verification->checks->duplicates = 'maybe';
		$this->assertSame( 'VERIFICATION_FAILED', $this->code( $this->line( $record ) ) );
		$record = $this->example();
		unset( $record->verification->checks->sensitive_scan );
		$this->assertSame( 'VERIFICATION_FAILED', $this->code( $this->line( $record ) ), 'A missing check is not a pass.' );
	}

	public function test_REQ_212_non_empty_images_refused(): void {
		$record         = $this->example();
		$record->images = array( (object) array( 'image_id' => 'i1' ) );
		$this->assertSame( 'IMAGE_NOT_ALLOWED', $this->code( $this->line( $record ) ) );
	}

	public function test_REQ_212_state_publish_visibility_or_status_field_is_refused(): void {
		foreach ( array( 'state', 'publish', 'visibility', 'status', 'pub_state', 'post_status' ) as $key ) {
			$record         = $this->example();
			$record->{$key} = 'published';
			$this->assertSame( 'SCHEMA', $this->code( $this->line( $record ) ), $key );
		}
	}

	public function test_REQ_212_schema_problems_return_one_code_each(): void {
		$cases = array(
			'unknown field in fields' => static function ( \stdClass $r ): void {
				$r->fields->nickname = (object) array(
					'value'      => 'Sam',
					'provenance' => $r->fields->name->provenance,
				);
			},
			'bad entity type'         => static function ( \stdClass $r ): void {
				$r->entity_type = 'robot';
			},
			'bad country'             => static function ( \stdClass $r ): void {
				$r->country = 'Nigeria';
			},
			'bad tier'                => static function ( \stdClass $r ): void {
				$r->tier = 'T9';
			},
			'unknown external id'     => static function ( \stdClass $r ): void {
				$r->external_ids->passport = 'X1';
			},
			'missing required field'  => static function ( \stdClass $r ): void {
				unset( $r->fields->org_type );
			},
			'missing key'             => static function ( \stdClass $r ): void {
				unset( $r->flags );
			},
		);
		foreach ( $cases as $label => $change ) {
			$record = $this->example();
			$change( $record );
			$this->assertSame( 'SCHEMA', $this->code( $this->line( $record ) ), $label );
		}
		$this->assertSame( 'SCHEMA', $this->code( 'not json' ) );
		$this->assertSame( 'SCHEMA', $this->code( '[1,2]' ) );

		$record                   = $this->example();
		$record->contract_version = '9.9';
		$this->assertSame( 'CONTRACT_VERSION', $this->code( $this->line( $record ) ) );
	}

	public function test_REQ_212_one_bad_record_does_not_stop_the_rest(): void {
		$validator   = new RecordValidator();
		$good        = $this->line( $this->example(), false );
		$bad         = $this->example();
		$bad->images = array( 1 );
		$results     = array_map(
			static fn ( string $line ): ?string => $validator->validate( $line )['code'],
			array( $good, $this->line( $bad ), $good )
		);
		$this->assertSame( array( null, 'IMAGE_NOT_ALLOWED', null ), $results );
	}
}
