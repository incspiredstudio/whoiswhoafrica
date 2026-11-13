<?php
/**
 * Deal steps 3 and 4 (REQ-263, REQ-269, REQ-182, REQ-188). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Commerce;

use WhoIsWho\Core\Commerce\ArrangementRepository;
use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Commerce\PlacementRepository;
use WhoIsWho\Core\Commerce\PlacementService;
use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\TestCase;

final class PlacementServiceTest extends TestCase {

	private const PROBE = 'wiw_placement_audit_probe';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		EventPostType::register();
		foreach ( array( CustomerRepository::table(), ArrangementRepository::table(), PlacementRepository::table() ) as $table ) {
			$wpdb->query( 'TRUNCATE TABLE ' . $table ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		}
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( '2026-11-12 10:00:00', new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * A service run as an Editor.
	 *
	 * @param string $role Role.
	 */
	private function service( string $role = 'wiw_editor' ): PlacementService {
		$user = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $user );
		wp_set_current_user( $user );
		$audit = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00', static fn (): int => $user );
		return new PlacementService( new ArrangementRepository(), new PlacementRepository(), new CustomerRepository(), $audit, fn (): \DateTimeImmutable => $this->now() );
	}

	/**
	 * A deal with a customer; paid or not.
	 *
	 * @param bool $paid Whether the payment record is complete.
	 */
	private function deal( bool $paid = true ): int {
		$customer = ( new CustomerRepository() )->insert( array( 'name' => 'Obi Holdings' ), null, $this->now() );
		$fields   = array(
			'customer_id'  => $customer,
			'amount_minor' => 50000000,
			'agreed'       => true,
		);
		if ( $paid ) {
			$fields += array(
				'method'       => 'transfer',
				'reference'    => 'TRX-1',
				'received_on'  => '2026-11-10',
				'confirmed_by' => 1,
			);
		}
		return ( new ArrangementRepository() )->insert( $fields, $this->now() );
	}

	/**
	 * A story post.
	 *
	 * @param string $status Status.
	 */
	private function story( string $status = 'publish' ): int {
		return (int) wp_insert_post(
			array(
				'post_title'  => 'Story ' . wp_generate_password( 4, false ),
				'post_status' => $status,
				'post_type'   => 'post',
			)
		);
	}

	public function test_REQ_263_slot_requires_published_story(): void {
		$deal    = $this->deal();
		$service = $this->service();
		try {
			$service->schedule( $deal, 'home', '2026-12-01', '2026-12-31' );
			$this->fail( 'A slot was scheduled without a story.' );
		} catch ( \DomainException $e ) {
			$this->assertNotSame( '', $e->getMessage() );
		}
		$service->choose_story( $deal, $this->story( 'draft' ) );
		$this->expectException( \DomainException::class );
		$service->schedule( $deal, 'home', '2026-12-01', '2026-12-31' );
	}

	public function test_REQ_263_published_story_can_be_scheduled_and_ended_and_scheduled_again(): void {
		$deal    = $this->deal();
		$service = $this->service();
		$service->choose_story( $deal, $this->story() );
		$first = $service->schedule( $deal, 'stories', '2026-12-01', '2026-12-31' );
		$this->assertSame( array(), $first['warnings'] );
		$this->assertSame( 'Obi Holdings', ( new PlacementRepository() )->get( $first['placement_id'] )['sponsor_name'] );
		$service->end_now( $first['placement_id'] );
		$this->assertSame( '1', (string) ( new PlacementRepository() )->get( $first['placement_id'] )['ended'] );
		$again = $service->schedule( $deal, 'stories', '2026-12-05', '2026-12-31' );
		$this->assertGreaterThan( $first['placement_id'], $again['placement_id'] );
		$this->assertCount( 2, ( new PlacementRepository() )->for_arrangement( $deal ), 'Nothing is deleted.' );
	}

	public function test_REQ_263_overlapping_slot_on_same_page_is_refused(): void {
		$service = $this->service();
		$one     = $this->deal();
		$two     = $this->deal();
		$service->choose_story( $one, $this->story() );
		$service->choose_story( $two, $this->story() );
		$service->schedule( $one, 'home', '2026-12-01', '2026-12-31' );
		foreach ( array( array( 'home', '2026-12-31', '2027-01-05' ), array( 'both', '2026-11-20', '2026-12-02' ) ) as $clash ) {
			try {
				$service->schedule( $two, $clash[0], $clash[1], $clash[2] );
				$this->fail( 'Overlap accepted: ' . $clash[0] );
			} catch ( \DomainException $e ) {
				$this->assertNotSame( '', $e->getMessage() );
			}
		}
		$service->schedule( $two, 'stories', '2026-12-01', '2026-12-31' );
		$service->schedule( $two, 'home', '2027-01-01', '2027-01-31' );
		$this->assertCount( 2, ( new PlacementRepository() )->for_arrangement( $two ) );
	}

	public function test_REQ_263_unpaid_deal_can_be_scheduled_with_warning(): void {
		$deal    = $this->deal( false );
		$service = $this->service();
		$service->choose_story( $deal, $this->story() );
		$result = $service->schedule( $deal, 'home', '2026-12-01', '2026-12-31' );
		$this->assertSame( array( 'payment_not_recorded' ), $result['warnings'] );
	}

	public function test_REQ_182_event_cannot_be_chosen_for_a_slot(): void {
		$deal  = $this->deal();
		$event = (int) wp_insert_post(
			array(
				'post_title'  => 'An event',
				'post_status' => 'publish',
				'post_type'   => EventPostType::TYPE,
			)
		);
		$this->expectException( \DomainException::class );
		$this->service()->choose_story( $deal, $event );
	}

	public function test_REQ_269_create_story_links_story_to_deal_and_a_reviewer_cannot_place(): void {
		$deal  = $this->deal();
		$story = $this->story( 'draft' );
		$this->service()->choose_story( $deal, $story );
		$this->assertSame( (string) $story, (string) ( new ArrangementRepository() )->get( $deal )['story_id'] );
		$this->expectException( \DomainException::class );
		$this->service( 'wiw_reviewer' )->choose_story( $deal, $this->story() );
	}

	public function test_REQ_188_deal_steps_audited_without_pii(): void {
		$deal    = $this->deal();
		$service = $this->service();
		$service->choose_story( $deal, $this->story() );
		$placed = $service->schedule( $deal, 'both', '2026-12-01', '2026-12-31' );
		$service->end_now( $placed['placement_id'] );
		global $wpdb;
		$rows = (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$this->assertSame( array( 'deal.story', 'deal.placement', 'deal.placement_end' ), array_column( $rows, 'action' ) );
		$this->assertStringNotContainsString( 'Obi', (string) wp_json_encode( $rows ), 'No customer name in the audit log.' );
	}
}
