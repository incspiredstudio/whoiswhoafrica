<?php
/**
 * Field allowlists and the field validator (REQ-139, REQ-140, REQ-142, REQ-143, REQ-068). Pure logic.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit\Directory;

use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\Fields\FieldValidator;
use WhoIsWho\Tests\TestCase;

final class FieldValidatorTest extends TestCase {

	/**
	 * A field with good provenance.
	 *
	 * @param mixed  $value      Value.
	 * @param string $confidence Confidence.
	 * @return array<string, mixed>
	 */
	private function field( $value, string $confidence = 'high' ): array {
		return array(
			'value'      => $value,
			'provenance' => array(
				array(
					'source_url'   => 'https://example.org/page',
					'retrieved_at' => '2026-11-10T09:00:00Z',
					'confidence'   => $confidence,
					'method'       => 'import',
				),
			),
		);
	}

	/**
	 * A complete individual.
	 *
	 * @return array<string, mixed>
	 */
	private function person(): array {
		return array(
			'name'                 => $this->field( 'Ada Obi' ),
			'current_title'        => $this->field( 'Chief Executive' ),
			'current_organisation' => $this->field( 'Example Bank' ),
			'industry'             => $this->field( 'Banking' ),
			'public_record_roles'  => $this->field( array( 'Director, Example Bank' ) ),
			'published_awards'     => $this->field( array( 'Banker of the Year 2025' ) ),
			'official_website'     => $this->field( 'https://example.org' ),
			'bio'                  => $this->field( 'A banker.' ),
		);
	}

	public function test_REQ_139_individual_profile_stores_only_allowlisted_fields(): void {
		$allow = new FieldAllowlist();
		$this->assertSame(
			array( 'name', 'current_title', 'current_organisation', 'current_organisation_ref', 'industry', 'public_record_roles', 'published_awards', 'official_website', 'bio' ),
			array_keys( $allow->for_type( 'individual' ) )
		);
		$this->assertSame( $allow->for_type( 'individual' ), $allow->for_type( 'entrepreneur' ) );
		$this->assertNotSame( array_keys( $allow->for_type( 'organisation' ) ), array_keys( $allow->for_type( 'individual' ) ), 'Allowlists differ by type.' );
		$this->assertSame( array(), ( new FieldValidator() )->validate( 'individual', $this->person() ) );
	}

	public function test_REQ_140_forbidden_attributes_have_no_storage(): void {
		$allow     = new FieldAllowlist();
		$validator = new FieldValidator();
		foreach ( $allow->forbidden() as $key ) {
			$this->assertNotContains( $key, $allow->all_keys(), "{$key} has no field" );
			$fields         = $this->person();
			$fields[ $key ] = $this->field( 'x' );
			$this->assertSame(
				array(
					array(
						'field' => $key,
						'code'  => 'FORBIDDEN_FIELD',
					),
				),
				$validator->validate( 'individual', $fields ),
				$key
			);
		}
		foreach ( array( 'email', 'phone', 'home_address', 'date_of_birth', 'religion', 'politics', 'ethnicity', 'social_handles' ) as $key ) {
			$this->assertContains( $key, $allow->forbidden() );
		}
		// An unknown but harmless key is a schema error, not stored either.
		$fields          = $this->person();
		$fields['hobby'] = $this->field( 'chess' );
		$this->assertSame( 'SCHEMA', ( new FieldValidator() )->validate( 'individual', $fields )[0]['code'] );
		// An organisation field is not allowed on a person.
		$fields = $this->person() + array( 'year_founded' => $this->field( 1999 ) );
		$this->assertSame( 'SCHEMA', ( new FieldValidator() )->validate( 'individual', $fields )[0]['code'] );
	}

	public function test_REQ_140_individual_has_no_contact_field(): void {
		$keys = array_keys( ( new FieldAllowlist() )->for_type( 'individual' ) );
		foreach ( array( 'email', 'phone', 'mobile', 'address' ) as $contact ) {
			$this->assertNotContains( $contact, $keys );
		}
	}

	public function test_REQ_068_no_age_or_date_of_birth_field_exists(): void {
		$allow = new FieldAllowlist();
		foreach ( $allow->all_keys() as $key ) {
			$this->assertDoesNotMatchRegularExpression( '/(^|_)(age|dob|birth|born)(_|$)/', $key );
		}
		$this->assertTrue( $allow->is_forbidden( 'age' ) );
		$this->assertTrue( $allow->is_forbidden( 'date_of_birth' ) );
	}

	public function test_REQ_142_field_without_source_is_refused(): void {
		$fields                                = $this->person();
		$fields['current_title']['provenance'] = array();
		$this->assertSame(
			array(
				array(
					'field' => 'current_title',
					'code'  => 'UNSOURCED_FIELD',
				),
			),
			( new FieldValidator() )->validate( 'individual', $fields )
		);
		unset( $fields['current_title']['provenance'] );
		$this->assertSame( 'UNSOURCED_FIELD', ( new FieldValidator() )->validate( 'individual', $fields )[0]['code'] );
		$fields                                   = $this->person();
		$fields['bio']['provenance'][0]['method'] = '';
		$this->assertSame( 'UNSOURCED_FIELD', ( new FieldValidator() )->validate( 'individual', $fields )[0]['code'] );
	}

	public function test_REQ_143_low_confidence_field_cannot_be_published(): void {
		$fields        = $this->person();
		$fields['bio'] = $this->field( 'A banker.', 'low' );
		$this->assertSame(
			array(
				array(
					'field' => 'bio',
					'code'  => 'LOW_CONFIDENCE',
				),
			),
			( new FieldValidator() )->validate( 'individual', $fields )
		);
	}

	public function test_REQ_139_required_fields_and_value_types(): void {
		$validator = new FieldValidator();
		$fields    = $this->person();
		unset( $fields['current_title'] );
		$this->assertSame( 'SCHEMA', $validator->validate( 'individual', $fields )[0]['code'] );

		$org = array(
			'name'         => $this->field( 'Example Bank Plc' ),
			'org_type'     => $this->field( 'Bank' ),
			'website'      => $this->field( 'https://example.org' ),
			'year_founded' => $this->field( 1999 ),
		);
		$this->assertSame( array(), $validator->validate( 'organisation', $org ) );
		$org['website']      = $this->field( 'javascript:alert(1)' );
		$org['year_founded'] = $this->field( '1999' );
		$codes               = array_column( $validator->validate( 'organisation', $org ), 'code', 'field' );
		$this->assertSame(
			array(
				'website'      => 'SCHEMA',
				'year_founded' => 'SCHEMA',
			),
			$codes
		);
		$this->assertSame( 'SCHEMA', $validator->validate( 'unknown_type', array() )[0]['code'] );
	}
}
