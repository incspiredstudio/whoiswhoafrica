<?php
/**
 * The content hash (REQ-221): RFC 8785 canonical JSON over the "In hash" fields. Pure logic.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit\Import;

use WhoIsWho\Core\Import\ContentHasher;
use WhoIsWho\Tests\TestCase;

final class ContentHasherTest extends TestCase {

	private const EXPECTED = 'sha256:90f04fe6e1d2e6956fcabd6dff4980e7ad1f517d8687713ec31fefa5582f6e01';

	private function example(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/import/worked-example.json' );
	}

	public function test_REQ_221_worked_example_hash_matches(): void {
		$this->assertSame( self::EXPECTED, ContentHasher::hash_json( $this->example() ) );
		$record = json_decode( $this->example(), false );
		$this->assertTrue( ContentHasher::matches( $record ) );
	}

	public function test_REQ_221_hash_ignores_fields_not_in_the_hash(): void {
		$record                            = json_decode( $this->example(), false );
		$record->record_version            = 9;
		$record->batch_id                  = 'B2';
		$record->flags->probable_duplicate = true;
		$this->assertSame( self::EXPECTED, ContentHasher::hash_record( $record ) );
	}

	public function test_REQ_221_hash_changes_when_field_changes(): void {
		$record                      = json_decode( $this->example(), false );
		$record->fields->name->value = 'Sample Bank Plc';
		$this->assertNotSame( self::EXPECTED, ContentHasher::hash_record( $record ) );
		$this->assertFalse( ContentHasher::matches( $record ) );

		$record              = json_decode( $this->example(), false );
		$record->entity_type = 'institution';
		$this->assertNotSame( self::EXPECTED, ContentHasher::hash_record( $record ) );
	}

	public function test_REQ_221_strings_normalised_to_nfc(): void {
		if ( ! class_exists( '\Normalizer' ) ) {
			$this->markTestSkipped( 'The intl extension is needed.' );
		}
		$decomposed                      = json_decode( $this->example(), false );
		$composed                        = json_decode( $this->example(), false );
		$decomposed->fields->name->value = "Cafe\u{301} Bank";
		$composed->fields->name->value   = "Caf\u{e9} Bank";
		$this->assertSame( ContentHasher::hash_record( $composed ), ContentHasher::hash_record( $decomposed ) );
	}

	public function test_REQ_221_canonical_form_follows_rfc_8785(): void {
		$value = json_decode( '{"b":1,"a":[true,null,"x\u007f\u0001/ "],"é":{},"c":[]}', false );
		$this->assertSame( "{\"a\":[true,null,\"x\u{7f}\\u0001/\u{2028}\"],\"b\":1,\"c\":[],\"\u{e9}\":{}}", ContentHasher::canonicalize( $value ) );
		// Keys sort by UTF-16 code units: U+FF5E (BMP) sorts after U+1F600 (surrogate pair D83D) is false, as D83D < FF5E.
		$keys = json_decode( "{\"\u{ff5e}\":1,\"\u{1f600}\":2}", false );
		$this->assertSame( "{\"\u{1f600}\":2,\"\u{ff5e}\":1}", ContentHasher::canonicalize( $keys ) );
	}

	public function test_REQ_221_a_non_object_is_refused(): void {
		$this->expectException( \InvalidArgumentException::class );
		ContentHasher::hash_json( '[1,2]' );
	}
}
