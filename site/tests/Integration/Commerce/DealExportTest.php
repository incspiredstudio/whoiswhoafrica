<?php
/**
 * The sponsored story deals CSV export (REQ-087, REQ-069, REQ-077). Needs the WordPress test suite and the real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Commerce;

use WhoIsWho\Core\Commerce\ArrangementRepository;
use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Commerce\DealExport;
use WhoIsWho\Core\Commerce\DealService;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\TestCase;

final class DealExportTest extends TestCase {

	private const PROBE = 'wiw_deal_export_probe';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		$wpdb->query( 'TRUNCATE TABLE ' . ArrangementRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'TRUNCATE TABLE ' . CustomerRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Sign in as a new user with a role and return an audit logger writing to the probe table.
	 *
	 * @param string $role Role.
	 */
	private function as_role( string $role ): AuditLogger {
		$user = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $user );
		wp_set_current_user( $user );
		return new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-13 10:00:00', static fn (): int => $user );
	}

	/**
	 * Audit rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function audit_rows(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
	}

	/**
	 * Two deals: one with a complete payment record and one with only the amount agreed.
	 *
	 * @return array{0: int, 1: int} The complete deal and the incomplete one.
	 */
	private function seed(): array {
		$audit     = $this->as_role( 'administrator' );
		$customers = new CustomerRepository();
		$at        = new \DateTimeImmutable( '2026-11-12 10:00:00', new \DateTimeZone( 'UTC' ) );
		$evil      = $customers->insert( array( 'name' => '=HYPERLINK("x")' ), null, $at );
		$plain     = $customers->insert( array( 'name' => 'Obi Holdings' ), null, $at );
		$deals     = new DealService( new ArrangementRepository(), $audit, static fn (): \DateTimeImmutable => $at );
		$complete  = $deals->create(
			array(
				'customer_id'  => $evil,
				'amount_minor' => 50000000,
				'currency'     => 'NGN',
			)
		);
		$deals->record_payment(
			$complete,
			array(
				'method'      => 'transfer',
				'reference'   => 'TRX-9',
				'received_on' => '2026-11-10',
			)
		);
		$partial = $deals->create(
			array(
				'customer_id'  => $plain,
				'amount_minor' => 1200000,
				'currency'     => 'NGN',
			)
		);
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		return array( $complete, $partial );
	}

	/**
	 * Parse a CSV into rows keyed by header.
	 *
	 * @param string $csv CSV text.
	 * @return array<int, array<string, string>>
	 */
	private function rows( string $csv ): array {
		$lines  = array_map( 'str_getcsv', array_filter( explode( "\n", $csv ) ) );
		$header = array_shift( $lines );
		return array_map( static fn ( array $line ): array => array_combine( $header, $line ), $lines );
	}

	public function test_REQ_087_deal_export_requires_capability_and_is_logged(): void {
		$this->seed();
		foreach ( array( 'wiw_editor', 'wiw_reviewer' ) as $role ) {
			$audit = $this->as_role( $role );
			try {
				( new DealExport( $audit ) )->csv();
				$this->fail( 'A ' . $role . ' ran the deals export.' );
			} catch ( \DomainException $e ) {
				$this->assertSame( 'You are not allowed to export data.', $e->getMessage() );
			}
		}
		$this->assertSame( array(), $this->audit_rows(), 'A refused export writes no audit row.' );

		$audit = $this->as_role( 'administrator' );
		( new DealExport( $audit ) )->csv();
		$rows = $this->audit_rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'capability.use', $rows[0]['action'] );
		$this->assertSame( 'deals', $rows[0]['subject_id'] );
		$dump = (string) wp_json_encode( $rows );
		foreach ( array( 'Obi Holdings', 'TRX-9', 'HYPERLINK' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $dump, 'The audit row holds a count, never a row.' );
		}
		$this->assertStringContainsString( '2 rows', $dump );
	}

	public function test_REQ_087_export_includes_counting_status(): void {
		list( $complete, $partial ) = $this->seed();
		$audit                      = $this->as_role( 'administrator' );
		$rows                       = $this->rows( ( new DealExport( $audit ) )->csv() );
		$this->assertCount( 2, $rows );
		$repo = new ArrangementRepository();
		$by   = array();
		foreach ( $rows as $row ) {
			$by[ $row['Deal ID'] ] = $row;
		}
		$full = $by[ (string) $repo->get( $complete )['external_id'] ];
		$this->assertSame( 'Yes', $full['Counts toward the target'] );
		$this->assertSame( '', $full['Missing'] );
		$this->assertSame( '50000000', $full['Amount (minor units)'] );
		$this->assertSame( 'NGN', $full['Currency'] );
		$this->assertSame( "'=HYPERLINK(\"x\")", $full['Customer'], 'A cell that starts like a formula is neutralised.' );

		$part = $by[ (string) $repo->get( $partial )['external_id'] ];
		$this->assertSame( 'No', $part['Counts toward the target'] );
		$this->assertSame( 'needs_payment', $part['Status'] );
		foreach ( array( 'Method', 'Reference', 'Date received', 'Confirmed by' ) as $label ) {
			$this->assertStringContainsString( $label, $part['Missing'] );
		}
	}

	public function test_REQ_087_missing_names_not_agreed_and_cancelled(): void {
		$this->assertSame( array( 'Deal not agreed', 'Cancelled' ), array_slice( DealExport::missing( array( 'cancelled' => 1 ) ), 0, 2 ) );
		$complete = array(
			'agreed'       => 1,
			'amount_minor' => 5,
			'currency'     => 'NGN',
			'method'       => 'cash',
			'reference'    => 'r',
			'received_on'  => '2026-11-10',
			'confirmed_by' => 3,
		);
		$this->assertSame( array(), DealExport::missing( $complete ) );
	}
}
