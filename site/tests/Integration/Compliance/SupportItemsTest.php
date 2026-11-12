<?php
/**
 * The support items table and repository (REQ-089, REQ-158, REQ-270, REQ-271). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Tests\TestCase;

/**
 * Plain TestCase on purpose (real tables, see ProfileTablesTest); the table is emptied in setUp.
 */
final class SupportItemsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$wpdb->query( 'TRUNCATE TABLE ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
	}

	private function items(): SupportItems {
		return new SupportItems( new DueDates( array() ), 'test-key' );
	}

	private function utc( string $when ): \DateTimeImmutable {
		return new \DateTimeImmutable( $when, new \DateTimeZone( 'UTC' ) );
	}

	public function test_REQ_158_a_correction_item_gets_a_due_date_seven_days_out(): void {
		$row    = $this->items()->create(
			array(
				'type'       => ItemType::CORRECTION,
				'profile_id' => 12,
				'name'       => 'Ada',
				'email'      => 'ada@example.test',
				'message'    => 'My title is wrong.',
			),
			$this->utc( '2026-11-11 09:00:00' )
		);
		$stored = $this->items()->get( $row['id'] );
		$this->assertNotNull( $stored );
		$this->assertSame( '2026-11-18 09:00:00', $stored['due_at'] );
		$this->assertSame( ItemStatus::NEW, $stored['status'] );
		$this->assertSame( 'WIW-', substr( $stored['reference'], 0, 4 ) );
		$this->assertSame( $stored['id'], $this->items()->find_by_reference( $stored['reference'] )['id'] );
	}

	public function test_REQ_158_a_removal_item_gets_a_two_business_day_countdown(): void {
		$row = $this->items()->create( array( 'type' => ItemType::REMOVAL ), $this->utc( '2026-11-12 09:00:00' ) );
		$this->assertSame( '2026-11-16 09:00:00', $this->items()->get( $row['id'] )['due_at'] );
		$this->assertSame( '2026-11-13 09:00:00', $this->items()->get( $row['id'] )['ack_due_at'] );
	}

	public function test_REQ_158_item_past_due_is_flagged_breach(): void {
		$items = $this->items();
		$row   = $items->create( array( 'type' => ItemType::CORRECTION ), $this->utc( '2026-11-11 09:00:00' ) );
		$item  = $items->get( $row['id'] );
		$this->assertFalse( $items->is_breach( $item, $this->utc( '2026-11-11 12:00:00' ) ) );
		$this->assertTrue( $items->is_breach( $item, $this->utc( '2026-11-19 09:00:00' ) ) );
		$items->set_status( $row['id'], ItemStatus::RESOLVED, $this->utc( '2026-11-15 09:00:00' ) );
		$this->assertFalse( $items->is_breach( (array) $items->get( $row['id'] ), $this->utc( '2026-11-19 09:00:00' ) ), 'resolved items never breach' );
		$this->assertSame( '2026-11-15 09:00:00', $items->get( $row['id'] )['resolved_at'] );
		$this->assertSame( '2026-11-15 09:00:00', $items->get( $row['id'] )['acknowledged_at'] );
	}

	public function test_REQ_089_support_item_pii_in_catalogue(): void {
		$catalogue = new PiiCatalogue();
		foreach ( array( 'requester_name', 'requester_email', 'requester_relationship', 'message', 'ip_hash' ) as $column ) {
			$this->assertTrue( $catalogue->has( 'wiw_support_items.' . $column ), $column );
		}
		$this->assertSame( array(), $catalogue->validate() );
	}

	public function test_REQ_089_the_ip_is_stored_only_as_a_hash(): void {
		global $wpdb;
		$row = $this->items()->create(
			array(
				'type' => ItemType::REPORT,
				'ip'   => '203.0.113.9',
			)
		);
		$raw = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT CONCAT_WS("|", ip_hash, requester_name, message) FROM ' . SupportItems::table() . ' WHERE id = %d', $row['id'] ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL
		$this->assertStringNotContainsString( '203.0.113.9', $raw );
		$this->assertSame( 64, strlen( (string) $this->items()->get( $row['id'] )['ip_hash'] ) );
		$this->assertSame( $this->items()->hash_ip( '203.0.113.9' ), $this->items()->get( $row['id'] )['ip_hash'] );
		$this->assertNotSame( $this->items()->hash_ip( '203.0.113.10' ), $this->items()->hash_ip( '203.0.113.9' ) );
	}

	public function test_REQ_271_items_list_by_screen_types_and_an_unknown_type_is_refused(): void {
		$items = $this->items();
		$items->create( array( 'type' => ItemType::CORRECTION ) );
		$items->create( array( 'type' => ItemType::REMOVAL ) );
		$items->create( array( 'type' => ItemType::ENQUIRY ) );
		$this->assertCount( 1, $items->list_by_types( array( ItemType::CORRECTION ) ) );
		$this->assertCount( 2, $items->list_by_types( array( ItemType::REMOVAL, ItemType::ENQUIRY ) ) );
		$this->assertCount( 0, $items->list_by_types( array( 'bogus' ) ) );
		$this->expectException( \InvalidArgumentException::class );
		$items->create( array( 'type' => 'bogus' ) );
	}

	public function test_REQ_158_owner_and_email_confirmation_are_recorded(): void {
		$items = $this->items();
		$row   = $items->create( array( 'type' => ItemType::REPORT ) );
		$this->assertNull( $items->get( $row['id'] )['confirmed_at'] );
		$items->confirm_email( $row['id'], $this->utc( '2026-11-11 10:00:00' ) );
		$items->assign( $row['id'], 5 );
		$stored = $items->get( $row['id'] );
		$this->assertSame( '2026-11-11 10:00:00', $stored['confirmed_at'] );
		$this->assertSame( '5', (string) $stored['owner_id'] );
	}
}
