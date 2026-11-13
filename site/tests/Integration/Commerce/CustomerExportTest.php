<?php
/**
 * The customers and enquiries CSV export (REQ-077, REQ-205, REQ-069, REQ-087). Needs the WordPress test suite and the real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Commerce;

use WhoIsWho\Core\Commerce\CustomerExport;
use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\TestCase;

final class CustomerExportTest extends TestCase {

	private const PROBE = 'wiw_customer_export_probe';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		$wpdb->query( 'TRUNCATE TABLE ' . CustomerRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'TRUNCATE TABLE ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
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
	 * An export run as a user with a role.
	 *
	 * @param string $role Role.
	 */
	private function export( string $role ): CustomerExport {
		$user = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $user );
		wp_set_current_user( $user );
		return new CustomerExport( new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-13 10:00:00', static fn (): int => $user ) );
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
	 * Seed one enquiry and one customer made from it.
	 *
	 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
	 */
	private function seed(): array {
		$items   = new SupportItems( new DueDates( array() ), 'test-key' );
		$enquiry = $items->create(
			array(
				'type'         => ItemType::ENQUIRY,
				'name'         => '=Ada Obi',
				'organisation' => 'Obi Holdings',
				'email'        => 'ada@example.test',
			)
		);
		$repo    = new CustomerRepository();
		$id      = $repo->insert( array( 'name' => 'Obi Holdings' ), (int) $enquiry['id'], new \DateTimeImmutable( '2026-11-12 10:00:00', new \DateTimeZone( 'UTC' ) ) );
		$row     = $repo->get( $id );
		$this->assertNotNull( $row );
		return array( $enquiry, $row );
	}

	public function test_REQ_077_customer_export_requires_capability_and_is_logged(): void {
		$this->seed();
		foreach ( array( 'wiw_editor', 'wiw_reviewer' ) as $role ) {
			$export = $this->export( $role );
			try {
				$export->customers_csv();
				$this->fail( 'A ' . $role . ' ran the customers export.' );
			} catch ( \DomainException $e ) {
				$this->assertSame( 'You are not allowed to export data.', $e->getMessage() );
			}
		}
		$this->assertSame( array(), $this->audit_rows(), 'A refused export writes no audit row.' );

		$export = $this->export( 'administrator' );
		$export->customers_csv();
		$export->enquiries_csv();
		$rows = $this->audit_rows();
		$this->assertCount( 2, $rows );
		$this->assertSame( 'capability.use', $rows[0]['action'] );
		$this->assertSame( 'customers', $rows[0]['subject_id'] );
		$this->assertSame( 'enquiries', $rows[1]['subject_id'] );
		$dump = (string) wp_json_encode( $rows );
		foreach ( array( 'Obi Holdings', 'ada@example.test' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $dump, 'The audit row holds a count, never a row.' );
		}
	}

	public function test_REQ_205_export_includes_external_id_and_change_timestamp(): void {
		list( $enquiry, $customer ) = $this->seed();
		$export                     = $this->export( 'administrator' );

		$lines = array_map( 'str_getcsv', array_filter( explode( "\n", $export->customers_csv() ) ) );
		$this->assertContains( 'External ID', $lines[0] );
		$this->assertContains( 'Changed', $lines[0] );
		$row = array_combine( $lines[0], $lines[1] );
		$this->assertSame( $customer['external_id'], $row['External ID'] );
		$this->assertSame( $customer['updated_at'], $row['Changed'] );
		$this->assertSame( $enquiry['external_id'], $row['Enquiry ID'], 'The customer carries the external id of its enquiry.' );

		$lines = array_map( 'str_getcsv', array_filter( explode( "\n", $export->enquiries_csv() ) ) );
		$row   = array_combine( $lines[0], $lines[1] );
		$this->assertSame( $enquiry['external_id'], $row['External ID'] );
		$this->assertSame( $enquiry['updated_at'], $row['Changed'] );
		$this->assertSame( "'=Ada Obi", $row['Name'], 'A cell that starts like a formula is neutralised.' );
	}
}
