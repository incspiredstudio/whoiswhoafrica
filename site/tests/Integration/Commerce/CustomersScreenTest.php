<?php
/**
 * The Customers screen (REQ-205, REQ-177, REQ-187). Needs the WordPress test suite and the real customers and support tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Commerce;

use WhoIsWho\Core\Commerce\Admin\CustomersScreen;
use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Commerce\CustomerService;
use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\TestCase;

final class CustomersScreenTest extends TestCase {

	private const PROBE = 'wiw_customers_screen_probe';

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
	 * The screen, run as a user with a role.
	 *
	 * @param string $role Role.
	 */
	private function screen( string $role = 'administrator' ): CustomersScreen {
		$user = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $user );
		wp_set_current_user( $user );
		$audit = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-13 10:00:00', static fn (): int => $user );
		$items = new SupportItems( new DueDates( array() ), 'test-key' );
		$repo  = new CustomerRepository();
		return new CustomersScreen( $repo, new CustomerService( $repo, $audit, $items ), $items );
	}

	/**
	 * Make an enquiry.
	 *
	 * @return array<string, mixed>
	 */
	private function enquiry(): array {
		return ( new SupportItems( new DueDates( array() ), 'test-key' ) )->create(
			array(
				'type'         => ItemType::ENQUIRY,
				'name'         => 'Ada Obi',
				'organisation' => 'Obi Holdings',
				'email'        => 'ada@example.test',
				'message'      => 'Hello there',
			)
		);
	}

	public function test_REQ_205_customer_created_from_enquiry_links_back(): void {
		$screen  = $this->screen();
		$enquiry = $this->enquiry();
		$this->assertCount( 1, $screen->open_enquiries() );
		$id       = $screen->create_from_enquiry( (int) $enquiry['id'] );
		$customer = ( new CustomerRepository() )->get( $id );
		$this->assertSame( (int) $enquiry['id'], (int) $customer['enquiry_id'] );
		$this->assertSame( 'Obi Holdings', $customer['name'] );
		$this->assertSame( 'ada@example.test', $customer['contact_email'] );
		$this->assertSame( array(), $screen->open_enquiries(), 'An enquiry with a customer is not offered again.' );
		$html = $screen->html( new Request( array( 'customer' => (string) $id ) ) );
		$this->assertStringContainsString( (string) $enquiry['reference'], $html );
		$this->assertStringContainsString( 'wiw-enquiries', $html );
	}

	public function test_REQ_205_customers_list_shows_external_id_name_and_segment_and_searches(): void {
		$screen = $this->screen();
		$repo   = new CustomerRepository();
		$at     = new \DateTimeImmutable( '2026-11-13 10:00:00', new \DateTimeZone( 'UTC' ) );
		$a      = $repo->insert(
			array(
				'name'    => 'Alpha Bank',
				'segment' => 'finance',
			),
			null,
			$at
		);
		$repo->insert(
			array(
				'name'    => 'Beta Media',
				'segment' => 'media',
			),
			null,
			$at
		);
		$html = $screen->html( new Request( array() ) );
		$this->assertStringContainsString( 'External ID', $html );
		$this->assertStringContainsString( 'Alpha Bank', $html );
		$this->assertStringContainsString( 'finance', $html );
		$this->assertStringContainsString( (string) ( $repo->get( $a )['external_id'] ?? 'x' ), $html );
		$found = $screen->rows( 'media' );
		$this->assertCount( 1, $found );
		$this->assertSame( 'Beta Media', $found[0]['name'] );
	}

	public function test_REQ_177_customers_screen_requires_capability(): void {
		$screen = $this->screen( 'wiw_reviewer' );
		$this->expectException( \DomainException::class );
		$screen->rows();
	}
}
