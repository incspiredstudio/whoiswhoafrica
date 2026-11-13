<?php
/**
 * Deal status, progress and the counting rule (REQ-269, REQ-022, REQ-180). Pure logic, no database.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit\Commerce;

use PHPUnit\Framework\TestCase;
use WhoIsWho\Core\Commerce\DealStatus;

final class DealStatusTest extends TestCase {

	private const TODAY = '2026-12-10';

	/**
	 * A fully paid deal.
	 *
	 * @param array<string, mixed> $over Overrides.
	 * @return array<string, mixed>
	 */
	private function deal( array $over = array() ): array {
		return array_merge(
			array(
				'agreed'       => 1,
				'cancelled'    => 0,
				'amount_minor' => 50000000,
				'currency'     => 'NGN',
				'method'       => 'transfer',
				'reference'    => 'TRX-1',
				'received_on'  => '2026-12-01',
				'confirmed_by' => 3,
				'story_id'     => 7,
			),
			$over
		);
	}

	/**
	 * A placement.
	 *
	 * @param string $from  Start.
	 * @param string $to    End.
	 * @param int    $ended Ended flag.
	 * @return array<string, mixed>
	 */
	private function place( string $from, string $to, int $ended = 0 ): array {
		return array(
			'starts_on' => $from,
			'ends_on'   => $to,
			'ended'     => $ended,
		);
	}

	public function test_REQ_269_deal_status_follows_steps(): void {
		$this->assertSame( 'needs_payment', DealStatus::of( $this->deal( array( 'reference' => '' ) ), array(), self::TODAY ) );
		$this->assertSame( 'needs_story', DealStatus::of( $this->deal( array( 'story_id' => null ) ), array(), self::TODAY ) );
		$this->assertSame( 'ready_to_schedule', DealStatus::of( $this->deal(), array(), self::TODAY ) );
		$this->assertSame( 'scheduled', DealStatus::of( $this->deal(), array( $this->place( '2026-12-20', '2026-12-31' ) ), self::TODAY ) );
		$this->assertSame( 'live', DealStatus::of( $this->deal(), array( $this->place( '2026-12-01', '2026-12-31' ) ), self::TODAY ) );
		$this->assertSame( 'ended', DealStatus::of( $this->deal(), array( $this->place( '2026-11-01', '2026-11-30' ) ), self::TODAY ) );
		$this->assertSame( 'ended', DealStatus::of( $this->deal(), array( $this->place( '2026-12-01', '2026-12-31', 1 ) ), self::TODAY ) );
		$this->assertSame( 'cancelled', DealStatus::of( $this->deal( array( 'cancelled' => 1 ) ), array( $this->place( '2026-12-01', '2026-12-31' ) ), self::TODAY ) );
		$this->assertContains( DealStatus::of( $this->deal(), array(), self::TODAY ), DealStatus::STATUSES );
	}

	public function test_REQ_022_incomplete_payment_record_does_not_count(): void {
		$this->assertTrue( DealStatus::counts( $this->deal() ) );
		foreach ( array_keys( DealStatus::PAYMENT_FIELDS ) as $column ) {
			$deal = $this->deal( array( $column => null ) );
			$this->assertFalse( DealStatus::counts( $deal ), $column );
			$this->assertArrayHasKey( $column, DealStatus::missing_payment_fields( $deal ) );
		}
		$this->assertFalse( DealStatus::counts( $this->deal( array( 'agreed' => 0 ) ) ) );
		$this->assertFalse( DealStatus::counts( $this->deal( array( 'cancelled' => 1 ) ) ) );
		$this->assertFalse( DealStatus::counts( $this->deal( array( 'confirmed_by' => 0 ) ) ) );
	}

	public function test_REQ_022_removing_required_field_stops_deal_counting(): void {
		$deal = $this->deal();
		$this->assertSame( 1, DealStatus::counted_total( array( $deal ) ) );
		$deal['received_on'] = '';
		$this->assertSame( 0, DealStatus::counted_total( array( $deal ) ) );
		$this->assertSame( array( 'received_on' => 'Date received' ), DealStatus::missing_payment_fields( $deal ) );
	}

	public function test_REQ_269_next_step_for_each_status(): void {
		$none = DealStatus::progress(
			$this->deal(
				array(
					'agreed'    => 0,
					'reference' => '',
					'story_id'  => null,
				)
			),
			array()
		);
		$this->assertCount( 4, $none['steps'] );
		$this->assertSame( 'deal_agreed', $none['next'] );
		$this->assertSame(
			'payment_recorded',
			DealStatus::progress(
				$this->deal(
					array(
						'reference' => '',
						'story_id'  => null,
					)
				),
				array()
			)['next']
		);
		$this->assertSame( 'story_ready', DealStatus::progress( $this->deal( array( 'story_id' => null ) ), array() )['next'] );
		$this->assertSame( 'placement', DealStatus::progress( $this->deal(), array() )['next'] );
		$this->assertNull( DealStatus::progress( $this->deal(), array( $this->place( '2026-12-01', '2026-12-31' ) ) )['next'] );
	}

	public function test_REQ_269_payment_not_recorded_flag_for_scheduled_and_live_unpaid_deals(): void {
		$unpaid = $this->deal( array( 'method' => '' ) );
		$this->assertTrue( DealStatus::payment_not_recorded( $unpaid, array( $this->place( '2026-12-01', '2026-12-31' ) ), self::TODAY ) );
		$this->assertTrue( DealStatus::payment_not_recorded( $unpaid, array( $this->place( '2026-12-20', '2026-12-31' ) ), self::TODAY ) );
		$this->assertFalse( DealStatus::payment_not_recorded( $unpaid, array(), self::TODAY ) );
		$this->assertFalse( DealStatus::payment_not_recorded( $this->deal(), array( $this->place( '2026-12-01', '2026-12-31' ) ), self::TODAY ) );
	}

	public function test_REQ_180_revenue_counts_only_counted_deals(): void {
		$deals = array(
			$this->deal(),
			$this->deal( array( 'amount_minor' => 20000000 ) ),
			$this->deal(
				array(
					'reference'    => '',
					'amount_minor' => 99900000,
				)
			),
			$this->deal( array( 'cancelled' => 1 ) ),
			$this->deal( array( 'currency' => 'USD' ) ),
		);
		$this->assertSame( 70000000, DealStatus::confirmed_revenue( $deals ) );
		$this->assertSame( 3, DealStatus::counted_total( $deals ) );
	}
}
