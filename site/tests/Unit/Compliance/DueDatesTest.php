<?php
/**
 * Response targets as dates (REQ-158): one business day to acknowledge, seven days for a correction, two business days
 * for a removal, all counted in West Africa Time.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit\Compliance;

use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\Reference;
use WhoIsWho\Tests\TestCase;

final class DueDatesTest extends TestCase {

	private function utc( string $when ): \DateTimeImmutable {
		return new \DateTimeImmutable( $when, new \DateTimeZone( 'UTC' ) );
	}

	public function test_REQ_158_correction_due_date_is_seven_days(): void {
		$due = ( new DueDates( array() ) )->resolve_due( ItemType::CORRECTION, $this->utc( '2026-11-11 09:00:00' ) );
		$this->assertSame( '2026-11-18 09:00:00', $due->format( 'Y-m-d H:i:s' ) );
	}

	public function test_REQ_158_removal_due_in_two_business_days(): void {
		$dates = new DueDates( array() );
		// Wednesday 11 November 2026 plus two business days is Friday.
		$this->assertSame( '2026-11-13', $dates->resolve_due( ItemType::REMOVAL, $this->utc( '2026-11-11 09:00:00' ) )->format( 'Y-m-d' ) );
		// Thursday plus two business days skips the weekend and lands on Monday.
		$this->assertSame( '2026-11-16', $dates->resolve_due( ItemType::REMOVAL, $this->utc( '2026-11-12 09:00:00' ) )->format( 'Y-m-d' ) );
		// Saturday counts from Monday: Tuesday.
		$this->assertSame( '2026-11-17', $dates->resolve_due( ItemType::REMOVAL, $this->utc( '2026-11-14 09:00:00' ) )->format( 'Y-m-d' ) );
	}

	public function test_REQ_158_acknowledge_is_one_business_day(): void {
		$dates = new DueDates( array() );
		$this->assertSame( '2026-11-16', $dates->acknowledge_due( $this->utc( '2026-11-13 09:00:00' ) )->format( 'Y-m-d' ), 'Friday to Monday' );
		$this->assertSame( '2026-11-12', $dates->acknowledge_due( $this->utc( '2026-11-11 09:00:00' ) )->format( 'Y-m-d' ) );
	}

	public function test_REQ_158_business_days_are_counted_in_west_africa_time(): void {
		$dates = new DueDates( array() );
		// 23:30 UTC on Thursday is 00:30 WAT on Friday, so one business day later is Monday.
		$due = $dates->acknowledge_due( $this->utc( '2026-11-12 23:30:00' ) );
		$this->assertSame( '2026-11-16', $due->format( 'Y-m-d' ) );
	}

	public function test_REQ_158_listed_holidays_are_skipped(): void {
		$dates = new DueDates( array( '2026-11-12' ) );
		$this->assertSame( '2026-11-13', $dates->acknowledge_due( $this->utc( '2026-11-11 09:00:00' ) )->format( 'Y-m-d' ) );
	}

	public function test_REQ_158_item_past_due_is_flagged_breach(): void {
		$due = $this->utc( '2026-11-18 09:00:00' );
		$ack = $this->utc( '2026-11-12 09:00:00' );
		$this->assertTrue( DueDates::is_breach( ItemStatus::IN_PROGRESS, $this->utc( '2026-11-18 09:00:01' ), $due, $ack ) );
		$this->assertFalse( DueDates::is_breach( ItemStatus::IN_PROGRESS, $this->utc( '2026-11-17 09:00:00' ), $due, $ack ) );
		$this->assertTrue( DueDates::is_breach( ItemStatus::NEW, $this->utc( '2026-11-13 09:00:00' ), $due, $ack ), 'not acknowledged in time' );
		$this->assertFalse( DueDates::is_breach( ItemStatus::ACKNOWLEDGED, $this->utc( '2026-11-13 09:00:00' ), $due, $ack ) );
		$this->assertFalse( DueDates::is_breach( ItemStatus::RESOLVED, $this->utc( '2027-01-01 09:00:00' ), $due, $ack ), 'a closed item is never in breach' );
	}

	public function test_REQ_158_default_calendar_loads_from_config(): void {
		$this->assertSame( '2026-11-12', ( new DueDates() )->acknowledge_due( $this->utc( '2026-11-11 09:00:00' ) )->format( 'Y-m-d' ) );
	}

	public function test_REQ_270_item_types_map_to_the_right_screen(): void {
		$this->assertSame( 'corrections', ItemType::screen( ItemType::CORRECTION ) );
		$this->assertSame( 'requests', ItemType::screen( ItemType::REMOVAL ) );
		$this->assertSame( 'requests', ItemType::screen( ItemType::REPORT ) );
		$this->assertSame( 'requests', ItemType::screen( ItemType::DATA_REQUEST ) );
		$this->assertSame( 'enquiries', ItemType::screen( ItemType::ENQUIRY ) );
	}

	public function test_REQ_089_reference_has_no_lookalike_letters(): void {
		for ( $i = 0; $i < 50; $i++ ) {
			$this->assertTrue( Reference::is_valid( Reference::generate() ) );
		}
		$this->assertFalse( Reference::is_valid( 'WIW-0OIL1111' ) );
	}
}
