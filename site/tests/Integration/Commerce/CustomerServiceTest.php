<?php
/**
 * The customer record and service (REQ-205, REQ-188, REQ-112). Needs the WordPress test suite and the real customers table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Commerce;

use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Commerce\CustomerService;
use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Tests\TestCase;

final class CustomerServiceTest extends TestCase {

	private const PROBE = 'wiw_customer_audit_probe';

	/**
	 * Clock value.
	 *
	 * @var string
	 */
	private string $now = '2026-11-12 10:00:00';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		$wpdb->query( 'TRUNCATE TABLE ' . CustomerRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'TRUNCATE TABLE ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		delete_option( 'wiw_enquiry_sequence' );
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
	 * A service run as a user with a role.
	 *
	 * @param string $role Role.
	 */
	private function service( string $role = 'wiw_editor' ): CustomerService {
		$user = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $user );
		wp_set_current_user( $user );
		$audit = new AuditLogger( new WpdbConnection( self::PROBE ), fn (): string => $this->now, static fn (): int => $user );
		return new CustomerService(
			new CustomerRepository(),
			$audit,
			new SupportItems( new DueDates( array() ), 'test-key' ),
			fn (): \DateTimeImmutable => new \DateTimeImmutable( $this->now, new \DateTimeZone( 'UTC' ) )
		);
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

	public function test_REQ_205_customer_has_stable_external_id_and_change_timestamps(): void {
		$service = $this->service();
		$id      = $service->create(
			array(
				'name'          => 'Obi Holdings',
				'segment'       => 'finance',
				'contact_email' => 'ada@example.test',
			)
		);
		$repo    = new CustomerRepository();
		$first   = $repo->get( $id );
		$this->assertNotNull( $first );
		$this->assertTrue( wp_is_uuid( $first['external_id'] ) );
		$this->assertSame( '2026-11-12 10:00:00', $first['created_at'] );
		$this->now = '2026-11-13 08:00:00';
		$service->update( $id, array( 'segment' => 'media' ) );
		$second = $repo->get( $id );
		$this->assertSame( $first['external_id'], $second['external_id'], 'The external id never changes.' );
		$this->assertSame( '2026-11-12 10:00:00', $second['created_at'] );
		$this->assertSame( '2026-11-13 08:00:00', $second['updated_at'] );
		$this->assertSame( 'media', $second['segment'] );
		$service->update( $id, array( 'segment' => 'media' ) );
		$this->assertCount( 2, $this->audit_rows(), 'An edit that changes nothing writes no audit row.' );
	}

	public function test_REQ_205_customer_contact_in_pii_catalogue(): void {
		$this->assertSame(
			array(),
			( new PiiCatalogue() )->missing( array( 'wiw_customers.name', 'wiw_customers.contact_email', 'wiw_customers.contact_phone', 'wiw_customers.notes' ) )
		);
	}

	public function test_REQ_188_customer_changes_audited_without_pii(): void {
		$service = $this->service();
		$id      = $service->create(
			array(
				'name'          => 'Obi Holdings',
				'contact_email' => 'ada@example.test',
				'notes'         => 'Prefers calls after noon.',
			)
		);
		$service->update( $id, array( 'contact_phone' => '+234 800 000 0000' ) );
		$rows = $this->audit_rows();
		$this->assertSame( array( 'customer.create', 'customer.update' ), array_column( $rows, 'action' ) );
		$this->assertSame( 'customer', $rows[0]['subject_type'] );
		$this->assertSame( (string) $id, $rows[0]['subject_id'] );
		$dump = wp_json_encode( $rows );
		foreach ( array( 'Obi Holdings', 'ada@example.test', 'Prefers calls', '+234 800' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, (string) $dump );
		}
		$this->assertStringContainsString( 'contact_phone', (string) $rows[1]['new_value'] );
	}

	public function test_REQ_205_customer_is_created_from_an_enquiry_once(): void {
		$service = $this->service();
		$item    = ( new SupportItems( new DueDates( array() ), 'test-key' ) )->create(
			array(
				'type'    => ItemType::ENQUIRY,
				'name'    => 'Ada Obi',
				'email'   => 'ada@example.test',
				'message' => 'Hello there',
			)
		);
		$id      = $service->create_from_enquiry( (int) $item['id'] );
		$this->assertSame( $id, $service->create_from_enquiry( (int) $item['id'] ), 'A second call returns the same customer.' );
		$customer = ( new CustomerRepository() )->get( $id );
		$this->assertSame( 'Ada Obi', $customer['name'] );
		$this->assertSame( 'ada@example.test', $customer['contact_email'] );
		$correction = ( new SupportItems( new DueDates( array() ), 'test-key' ) )->create( array( 'type' => ItemType::CORRECTION ) );
		$this->expectException( \DomainException::class );
		$service->create_from_enquiry( (int) $correction['id'] );
	}

	public function test_REQ_205_a_user_without_the_capability_cannot_manage_customers(): void {
		$service = $this->service( 'subscriber' );
		$this->expectException( \DomainException::class );
		$service->create( array( 'name' => 'Nope' ) );
	}
}
