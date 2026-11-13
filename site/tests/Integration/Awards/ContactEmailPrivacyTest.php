<?php
/**
 * The nomination Contact email in the PII catalogue, retention and erasure (REQ-268, REQ-064). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\ContactEmailPrivacy;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Tests\WpTestCase;

final class ContactEmailPrivacyTest extends WpTestCase {

	private const PROBE = 'wiw_contact_email_audit_probe';

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		foreach ( AwardsTables::definitions() as $name => $definition ) {
			Schema::create_table( $name, $definition );
		}
	}

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		foreach ( array( AwardsTables::NOMINATIONS, AwardsTables::CHANGES ) as $name ) {
			$wpdb->query( 'DELETE FROM ' . AwardsTables::table( $name ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		parent::tearDown();
	}

	private function service(): ContactEmailPrivacy {
		return new ContactEmailPrivacy( new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00' ) );
	}

	private function nomination( string $email, string $created = '2026-11-12 10:00:00' ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::NOMINATIONS ),
			array(
				'category_id'   => 1,
				'contact_email' => $email,
				'created_at'    => $created,
				'updated_at'    => $created,
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function email_of( int $id ): ?string {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( 'SELECT contact_email FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		return null === $value ? null : (string) $value;
	}

	private function exists( int $id ): bool {
		global $wpdb;
		return null !== $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
	}

	public function test_REQ_268_contact_email_is_in_the_pii_catalogue_and_erased_with_the_nomination(): void {
		$entry = ( new PiiCatalogue() )->find( 'wiw_nominations.contact_email' );
		$this->assertNotNull( $entry );
		$this->assertSame( 'delete', $entry['erase'] );
		$this->assertSame( 'exclude', $entry['export'] );

		$mine  = $this->nomination( 'press@example.org' );
		$other = $this->nomination( 'other@example.org' );

		$removed = $this->service()->erase_for_email( 'Press@Example.org' );

		$this->assertSame( 1, $removed );
		$this->assertFalse( $this->exists( $mine ) );
		$this->assertTrue( $this->exists( $other ) );
	}

	public function test_REQ_268_contact_email_is_not_in_public_output_exports_or_logs(): void {
		$catalogue = new PiiCatalogue();
		$this->assertSame( 'exclude', $catalogue->find( 'wiw_nominations.contact_email' )['export'] );
		$this->assertNotContains( 'contact_email', $this->service()->export_columns() );

		$this->assertContains( 'wiw_nominations.contact_email', array_column( $catalogue->personal(), 'name' ) );

		$id = $this->nomination( 'press@example.org' );
		$this->service()->erase_for_email( 'press@example.org' );
		global $wpdb;
		$audit = (string) $wpdb->get_var( 'SELECT GROUP_CONCAT(CONCAT_WS(" ", action, subject_id, old_value, new_value)) FROM ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		$this->assertStringNotContainsString( 'press@example.org', $audit );
		$this->assertStringContainsString( (string) $id, $audit );
	}

	public function test_REQ_268_retention_removes_the_contact_email(): void {
		$old   = $this->nomination( 'old@example.org', '2024-01-01 00:00:00' );
		$fresh = $this->nomination( 'fresh@example.org', '2026-11-01 00:00:00' );

		$cleared = $this->service()->retain( new \DateTimeImmutable( '2025-01-01 00:00:00', new \DateTimeZone( 'UTC' ) ) );

		$this->assertSame( 1, $cleared );
		$this->assertNull( $this->email_of( $old ) );
		$this->assertSame( 'fresh@example.org', $this->email_of( $fresh ) );
		$this->assertTrue( $this->exists( $old ) );
	}
}
