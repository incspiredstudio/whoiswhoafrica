<?php
/**
 * The free-text scanner (REQ-141). Pure logic.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit\Import;

use WhoIsWho\Core\Import\Scan\FreeTextScanner;
use WhoIsWho\Tests\TestCase;

final class FreeTextScannerTest extends TestCase {

	/**
	 * Categories found in one summary text.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	private function categories( string $text ): array {
		$found = ( new FreeTextScanner() )->scan( array( 'summary' => array( 'value' => $text ) ) );
		return array_column( $found, 'category' );
	}

	public function test_REQ_141_free_text_phone_number_is_flagged(): void {
		$this->assertSame( array( 'phone' ), $this->categories( 'Call the founder on +234 803 555 0142 for details.' ) );
		$this->assertSame( array( 'phone' ), $this->categories( 'Reach us on 0803 555 0142.' ) );
		$this->assertSame( array( 'phone' ), $this->categories( 'Office (01) 555-0142-77' ) );
	}

	public function test_REQ_141_email_address_and_street_address_are_flagged(): void {
		$this->assertSame( array( 'email' ), $this->categories( 'Write to jane.doe@example.com today.' ) );
		$this->assertSame( array( 'address' ), $this->categories( 'Based at 12 Adeola Odeku Street, Victoria Island.' ) );
		$this->assertSame( array( 'address' ), $this->categories( 'Post to P.O. Box 4411.' ) );
	}

	public function test_REQ_141_age_and_date_of_birth_are_flagged(): void {
		$this->assertSame( array( 'age' ), $this->categories( 'The director, aged 47, joined in 2010.' ) );
		$this->assertSame( array( 'age' ), $this->categories( 'She is a 52-year-old banker.' ) );
		$this->assertSame( array( 'age' ), $this->categories( 'Born on 3 May 1975 in Lagos.' ) );
	}

	public function test_REQ_141_sensitive_terms_are_flagged_as_whole_words(): void {
		$this->assertSame( array( 'sensitive_term' ), $this->categories( 'The chair was convicted of an offence.' ) );
		$this->assertSame( array( 'sensitive_term' ), $this->categories( 'A Muslim charity.' ) );
		$this->assertSame( array(), $this->categories( 'The firm declined to comment on the arresting officer.' ), 'A term inside a longer word is not a match.' );
	}

	public function test_REQ_141_plain_business_text_is_not_flagged(): void {
		$this->assertSame( array(), $this->categories( 'A licensed commercial bank founded in 1998 and active 2005 - 2024 across 14 states.' ) );
	}

	public function test_REQ_141_list_fields_and_plain_values_are_scanned_and_only_free_text_fields(): void {
		$found = ( new FreeTextScanner() )->scan(
			array(
				'name'                => array( 'value' => 'Reach us on 0803 555 0142 Ltd' ),
				'public_record_roles' => array( 'value' => array( 'Board member', 'Contact jane@example.com' ) ),
				'mandate'             => 'Regulates banks.',
			)
		);
		$this->assertSame(
			array(
				array(
					'field'    => 'public_record_roles',
					'category' => 'email',
				),
			),
			$found,
			'The name field is not free text; list values are scanned.'
		);
	}

	public function test_REQ_141_result_never_contains_the_matched_text(): void {
		$found = ( new FreeTextScanner() )->scan( array( 'summary' => array( 'value' => 'Call +234 803 555 0142 or jane@example.com' ) ) );
		$this->assertNotSame( array(), $found );
		$encoded = (string) json_encode( $found ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- plain PHP in a pure unit test.
		$this->assertStringNotContainsString( '803', $encoded );
		$this->assertStringNotContainsString( 'jane', $encoded );
	}
}
