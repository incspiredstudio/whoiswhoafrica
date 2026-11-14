<?php
/**
 * Field labels come from config/profile-fields.php (REQ-125, REQ-139; issue 806): every allowlisted field has a plain label, and a
 * field without one falls back to a readable form of its key. Pure logic.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit\Directory;

use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Tests\TestCase;

final class FieldLabelTest extends TestCase {

	public function test_REQ_125_every_configured_field_has_a_plain_label(): void {
		$allowlist = new FieldAllowlist();
		foreach ( $allowlist->entity_types() as $type ) {
			foreach ( $allowlist->for_type( $type ) as $key => $rule ) {
				$this->assertIsString( $rule['label'] ?? null, "{$type}.{$key} has a label in the config" );
				$this->assertNotSame( '', trim( (string) $rule['label'] ) );
				$this->assertStringNotContainsString( '_', (string) $rule['label'], "{$type}.{$key} label is not a machine key" );
			}
		}
	}

	public function test_REQ_125_label_reads_the_config_and_falls_back_to_a_readable_key(): void {
		$allowlist = new FieldAllowlist();
		$this->assertSame( 'Current title', $allowlist->label( 'individual', 'current_title' ) );
		$this->assertSame( 'Head office city', $allowlist->label( 'organisation', 'hq_city' ) );
		$this->assertSame( 'Organisation type', $allowlist->label( 'institution', 'org_type' ) );

		$bare = new FieldAllowlist(
			array(
				'types'     => array(
					'organisation' => array(
						'some_new_field' => array(
							'type'     => 'string',
							'required' => false,
						),
					),
				),
				'forbidden' => array(),
			)
		);
		$this->assertSame( 'Some new field', $bare->label( 'organisation', 'some_new_field' ) );
		$this->assertSame( 'Unknown key', $bare->label( 'organisation', 'unknown_key' ) );
	}
}
