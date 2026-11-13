<?php
/**
 * The nominations export: Administrator only, audited, no Contact email and no commercial field (REQ-031, REQ-199, REQ-268).
 * Needs the WordPress test suite and the real awards and audit tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\NominationExport;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\WpTestCase;

final class NominationExportTest extends WpTestCase {

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
	}

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		foreach ( array( AwardsTables::NOMINATIONS, AwardsTables::CATEGORIES ) as $name ) {
			$wpdb->query( 'DELETE FROM ' . AwardsTables::table( $name ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		}
		wp_set_current_user( 0 );
	}

	private function seed(): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::CATEGORIES ),
			array(
				'group_key'  => 'technology-innovation',
				'title'      => 'Digital Pioneer',
				'created_at' => '2026-11-12 10:00:00',
			)
		);
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::NOMINATIONS ),
			array(
				'category_id'     => (int) $wpdb->insert_id,
				'reference'       => 'NOM-AAAAAA',
				'nominator_name'  => 'Nora Nominator',
				'nominator_email' => 'nora.nominator@example.test',
				'contact_email'   => 'press.contact@example.test',
				'nominee_name'    => '=HYPERLINK("http://evil.example","click")',
				'nominee_org'     => 'Obi Labs',
				'evidence'        => 'Built the national payments switch.',
				'stage'           => 'nominated',
				'status'          => 'not_live',
				'source'          => 'public_form',
				'created_at'      => '2026-11-12 10:00:00',
				'updated_at'      => '2026-11-12 10:00:00',
			)
		);
	}

	private function as_role( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	private function export(): NominationExport {
		return new NominationExport( AuditLogger::for_site() );
	}

	public function test_REQ_031_nominations_export_requires_wiw_export_data(): void {
		$this->seed();
		$this->as_role( 'wiw_editor' );
		$this->assertFalse( current_user_can( NominationExport::CAP ), 'the Editor does not hold wiw_export_data' );
		try {
			$this->export()->csv();
			$this->fail( 'An Editor exported nominations.' );
		} catch ( \DomainException $refusal ) {
			$this->assertStringContainsString( 'not allowed', $refusal->getMessage() );
		}
		$this->as_role( 'administrator' );
		$this->assertTrue( current_user_can( NominationExport::CAP ) );
		$this->assertStringContainsString( 'Digital Pioneer', $this->export()->csv() );

		$this->assertSame( ActionGuard::REFUSED_NONCE, ActionGuard::check( NominationExport::ACTION, NominationExport::CAP, new Request( array() ) ) );
		$nonce = ActionGuard::nonce_for( NominationExport::ACTION );
		$this->assertNull( ActionGuard::check( NominationExport::ACTION, NominationExport::CAP, new Request( array( '_wpnonce' => $nonce ) ) ) );
		$this->as_role( 'wiw_editor' );
		$this->assertSame( ActionGuard::REFUSED_CAPABILITY, ActionGuard::check( NominationExport::ACTION, NominationExport::CAP, new Request( array( '_wpnonce' => ActionGuard::nonce_for( NominationExport::ACTION ) ) ) ) );
	}

	public function test_REQ_199_nominations_export_has_no_commercial_fields(): void {
		$this->seed();
		$this->as_role( 'administrator' );
		$csv    = $this->export()->csv();
		$header = strtolower( strtok( $csv, "\n" ) );
		foreach ( array( 'payment', 'price', 'amount', 'subscription', 'sponsor', 'customer', 'deal', 'invoice', 'kobo' ) as $commercial ) {
			$this->assertStringNotContainsString( $commercial, $header );
		}
		$this->assertSame( array_keys( NominationExport::COLUMNS ), str_getcsv( strtok( $csv, "\n" ) ), 'only the allowlisted columns' );
	}

	public function test_REQ_268_contact_email_not_in_nominations_export(): void {
		$this->seed();
		$this->as_role( 'administrator' );
		$csv = $this->export()->csv();
		$this->assertStringNotContainsString( 'press.contact', $csv );
		$this->assertStringNotContainsString( 'contact', strtolower( strtok( $csv, "\n" ) ) );
		$this->assertStringContainsString( 'nora.nominator@example.test', $csv, 'the nominator is a listed export column' );
		$this->assertStringContainsString( "'=HYPERLINK", $csv, 'a cell that starts like a formula is neutralised' );
		$this->assertSame( "'=1+1", NominationExport::cell( '=1+1' ) );
		$this->assertSame( 'plain', NominationExport::cell( 'plain' ) );

		global $wpdb;
		$audit = (string) wp_json_encode( $wpdb->get_results( "SELECT action, new_value, reason FROM {$wpdb->prefix}wiw_audit_log WHERE action = 'capability.use'", ARRAY_A ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		$this->assertStringContainsString( 'wiw_export_data', $audit, 'the export is audited' );
		$this->assertStringContainsString( '1 rows', $audit );
		$this->assertStringNotContainsString( 'press.contact', $audit );
		$this->assertStringNotContainsString( 'nora.nominator', $audit, 'the audit entry holds a count only' );
	}
}
