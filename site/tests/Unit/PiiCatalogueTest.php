<?php
/**
 * The PII catalogue (REQ-063, REQ-069, REQ-050).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Tests\TestCase;

final class PiiCatalogueTest extends TestCase {

	public function test_REQ_069_catalogue_lists_export_strategy_for_each_entry(): void {
		$catalogue = new PiiCatalogue();
		$this->assertSame( array(), $catalogue->validate(), 'the shipped catalogue is well formed' );
		$this->assertNotEmpty( $catalogue->entries() );
		foreach ( $catalogue->entries() as $entry ) {
			$this->assertContains( $entry['export'], PiiCatalogue::EXPORTS, $entry['name'] );
			$this->assertContains( $entry['erase'], PiiCatalogue::ERASES, $entry['name'] );
			$this->assertNotSame( '', $entry['retention'], $entry['name'] . ' has a retention class' );
		}
	}

	public function test_REQ_063_the_m0_tables_are_catalogued(): void {
		$catalogue = new PiiCatalogue();
		foreach ( array( 'wiw_signed_links.email', 'wiw_email_events.recipient_ref', 'wiw_notes.body', 'wiw_audit_log.old_value', 'wiw_audit_log.new_value', 'users.user_email' ) as $name ) {
			$this->assertTrue( $catalogue->has( $name ), "{$name} must be listed" );
		}
		$this->assertSame( 'hash_only', $catalogue->find( 'wiw_email_events.recipient_ref' )['export'], 'the mail log stores a keyed hash, never the address' );
		$this->assertSame( 'caller_supplied', $catalogue->find( 'wiw_audit_log.new_value' )['class'] );
	}

	public function test_REQ_063_a_migration_that_declares_an_unlisted_personal_column_fails(): void {
		$catalogue = new PiiCatalogue();
		$this->assertSame( array(), $catalogue->missing( array( 'wiw_signed_links.email' ) ) );
		$this->assertSame( array( 'wiw_claims.claimant_phone' ), $catalogue->missing( array( 'wiw_signed_links.email', 'wiw_claims.claimant_phone' ) ) );
	}

	public function test_REQ_063_a_personal_looking_column_must_be_classified(): void {
		$catalogue = new PiiCatalogue();
		$this->assertSame(
			array( 'wiw_enquiries.sender_email', 'wiw_enquiries.ip_address' ),
			$catalogue->unclassified( array( 'wiw_enquiries.sender_email', 'wiw_enquiries.ip_address', 'wiw_enquiries.status', 'wiw_audit_log.action', 'wiw_signed_links.email' ) ),
			'unlisted personal-looking columns are reported; catalogued and allowlisted ones are not'
		);
		foreach ( array( 'email', 'user_email', 'full_name', 'phone', 'ip', 'ip_hash_ref', 'postal_address', 'date_of_birth' ) as $column ) {
			$this->assertTrue( PiiCatalogue::looks_personal( $column ), $column );
		}
		foreach ( array( 'status', 'hash', 'created_at', 'amount_minor', 'pub_state', 'recipient_ref' ) as $column ) {
			$this->assertFalse( PiiCatalogue::looks_personal( $column ), $column );
		}
	}

	public function test_REQ_050_a_badly_formed_catalogue_is_reported(): void {
		$bad  = new PiiCatalogue(
			array(
				array(
					'kind'      => 'column',
					'name'      => 'wiw_x.email',
					'class'     => 'secret',
					'retention' => 'life',
					'export'    => 'include',
					'erase'     => 'keep',
					'note'      => '',
				),
				array(
					'kind'      => 'column',
					'name'      => 'wiw_x.email',
					'class'     => 'pii',
					'retention' => '',
					'export'    => 'maybe',
					'erase'     => 'delete',
					'note'      => '',
				),
			)
		);
		$text = implode( ' | ', $bad->validate() );
		$this->assertStringContainsString( 'unknown class', $text );
		$this->assertStringContainsString( 'a kept entry needs a note', $text );
		$this->assertStringContainsString( 'unknown export strategy', $text );
		$this->assertStringContainsString( 'retention is missing', $text );
		$this->assertStringContainsString( 'listed twice', $text );
	}

	public function test_REQ_050_the_scrubber_redacts_catalogued_meta_keys(): void {
		$catalogue = new PiiCatalogue(
			array(
				array(
					'kind'      => 'meta',
					'name'      => 'wiw_contact_email',
					'class'     => 'pii',
					'retention' => 'case_life',
					'export'    => 'include',
					'erase'     => 'delete',
					'note'      => '',
				),
			)
		);
		$this->assertSame(
			array(
				'wiw_contact_email' => PiiCatalogue::REDACTED,
				'state'             => 'published',
			),
			$catalogue->scrub(
				array(
					'wiw_contact_email' => 'jane@example.org',
					'state'             => 'published',
				)
			)
		);
	}
}
