<?php
/**
 * The deal and placement tables (REQ-179, REQ-112, REQ-199, REQ-205). Needs the WordPress test suite and the real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Commerce;

use WhoIsWho\Core\Commerce\ArrangementRepository;
use WhoIsWho\Core\Commerce\Money;
use WhoIsWho\Core\Commerce\PlacementRepository;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Tests\TestCase;

final class DealTablesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$wpdb->query( 'TRUNCATE TABLE ' . ArrangementRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'TRUNCATE TABLE ' . PlacementRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
	}

	private function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( '2026-11-12 10:00:00', new \DateTimeZone( 'UTC' ) );
	}

	public function test_REQ_179_only_sponsored_post_deal_type_exists(): void {
		$this->assertSame( array( 'sponsored_post' ), ArrangementRepository::TYPES );
		$repo = new ArrangementRepository();
		$id   = $repo->insert( array( 'customer_id' => 5 ), $this->now() );
		$this->assertSame( 'sponsored_post', $repo->get( $id )['type'] );
		$this->expectException( \InvalidArgumentException::class );
		$repo->insert(
			array(
				'customer_id' => 5,
				'type'        => 'sponsored_profile',
			),
			$this->now()
		);
	}

	public function test_REQ_179_type_cannot_be_changed_by_update(): void {
		$repo = new ArrangementRepository();
		$id   = $repo->insert( array( 'customer_id' => 5 ), $this->now() );
		$this->expectException( \InvalidArgumentException::class );
		$repo->update( $id, array( 'type' => 'other' ), $this->now() );
	}

	public function test_REQ_112_amount_stored_as_integer_minor_units(): void {
		global $wpdb;
		$repo = new ArrangementRepository();
		$id   = $repo->insert(
			array(
				'customer_id'  => 5,
				'amount_minor' => '50000000',
				'currency'     => 'ngn',
			),
			$this->now()
		);
		$row  = $repo->get( $id );
		$this->assertSame( '50000000', (string) $row['amount_minor'] );
		$this->assertSame( 'NGN', $row['currency'] );
		$money = ArrangementRepository::money( $row );
		$this->assertTrue( $money->equals( new Money( 50000000, 'NGN' ) ) );
		$type = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s', ArrangementRepository::table(), 'amount_minor' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertSame( 'bigint', $type );
		$this->assertNull( ArrangementRepository::money( $repo->get( $repo->insert( array( 'customer_id' => 6 ), $this->now() ) ) ) );
	}

	public function test_REQ_112_decimal_amounts_and_negative_amounts_are_refused(): void {
		$repo = new ArrangementRepository();
		foreach ( array( '12.50', -5, 'abc' ) as $bad ) {
			try {
				$repo->insert(
					array(
						'customer_id'  => 5,
						'amount_minor' => $bad,
					),
					$this->now()
				);
				$this->fail( 'Amount accepted: ' . $bad );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertNotSame( '', $e->getMessage() );
			}
		}
	}

	public function test_REQ_205_deal_has_stable_external_id_and_placement_stores_slot_and_dates(): void {
		$repo = new ArrangementRepository();
		$id   = $repo->insert(
			array(
				'customer_id' => 5,
				'agreed'      => true,
			),
			$this->now()
		);
		$row  = $repo->get( $id );
		$this->assertTrue( wp_is_uuid( $row['external_id'] ) );
		$this->assertSame( array(), $repo->update( $id, array( 'agreed' => true ), $this->now() ), 'No change, no write.' );
		$this->assertSame( array( 'cancelled' ), $repo->update( $id, array( 'cancelled' => true ), $this->now() ) );
		$places = new PlacementRepository();
		$pid    = $places->insert(
			array(
				'arrangement_id' => $id,
				'story_id'       => 9,
				'sponsor_name'   => 'Obi Holdings',
				'slot'           => 'both',
				'starts_on'      => '2026-12-01',
				'ends_on'        => '2026-12-31',
			),
			$this->now()
		);
		$this->assertSame( 'both', $places->get( $pid )['slot'] );
		$this->assertSame( '0', (string) $places->get( $pid )['ended'] );
		$this->assertCount( 1, $places->for_arrangement( $id ) );
		$places->mark_ended( $pid, $this->now() );
		$this->assertSame( '1', (string) $places->get( $pid )['ended'] );
	}

	public function test_REQ_205_placement_refuses_bad_slot_and_reversed_dates(): void {
		$places = new PlacementRepository();
		$base   = array(
			'arrangement_id' => 1,
			'story_id'       => 9,
			'sponsor_name'   => 'X',
			'slot'           => 'home',
			'starts_on'      => '2026-12-01',
			'ends_on'        => '2026-12-31',
		);
		foreach ( array( array( 'slot' => 'sidebar' ), array( 'ends_on' => '2026-11-01' ), array( 'sponsor_name' => '' ) ) as $change ) {
			try {
				$places->insert( array_merge( $base, $change ), $this->now() );
				$this->fail( 'Accepted a bad placement.' );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertNotSame( '', $e->getMessage() );
			}
		}
	}

	public function test_REQ_199_no_schema_link_from_deals_to_awards(): void {
		global $wpdb;
		$tables = array( ArrangementRepository::table(), PlacementRepository::table() );
		$refs   = (array) $wpdb->get_col( 'SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL AND TABLE_NAME IN (\'' . implode( "','", array_map( 'esc_sql', $tables ) ) . '\')' ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed names.
		$this->assertSame( array(), $refs, 'No foreign key at all.' );
		$columns = (array) $wpdb->get_col( 'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (\'' . implode( "','", array_map( 'esc_sql', $tables ) ) . '\')' ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed names.
		foreach ( $columns as $column ) {
			$this->assertDoesNotMatchRegularExpression( '/nomination|winner|profile|edition|category/', (string) $column );
		}
	}

	public function test_REQ_021_new_columns_are_in_the_pii_catalogue(): void {
		$this->assertSame(
			array(),
			( new PiiCatalogue() )->missing( array( 'wiw_arrangements.notes', 'wiw_arrangements.reference', 'wiw_arrangements.amount_minor', 'wiw_placements.sponsor_name' ) )
		);
	}
}
