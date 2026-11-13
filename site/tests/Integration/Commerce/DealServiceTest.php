<?php
/**
 * Money field access and deal validation (REQ-269, REQ-179, REQ-226, REQ-086, REQ-239). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Commerce;

use WhoIsWho\Core\Commerce\ArrangementRepository;
use WhoIsWho\Core\Commerce\CardGuard;
use WhoIsWho\Core\Commerce\DealService;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\TestCase;

final class DealServiceTest extends TestCase {

	private const PROBE = 'wiw_deal_audit_probe';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		$wpdb->query( 'TRUNCATE TABLE ' . ArrangementRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
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
	 * @param string        $role  Role.
	 * @param callable|null $state Profile state lookup.
	 */
	private function service( string $role = 'administrator', ?callable $state = null ): DealService {
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
		return new DealService( new ArrangementRepository(), $audit, static fn (): \DateTimeImmutable => new \DateTimeImmutable( '2026-11-12 10:00:00', new \DateTimeZone( 'UTC' ) ), $state );
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

	public function test_REQ_269_editor_sees_no_money_fields_and_cannot_record_payment(): void {
		$admin = $this->service();
		$id    = $admin->create(
			array(
				'customer_id'  => 4,
				'amount_minor' => 50000000,
				'currency'     => 'NGN',
			)
		);
		$admin->record_payment(
			$id,
			array(
				'method'      => 'transfer',
				'reference'   => 'TRX-9',
				'received_on' => '2026-11-10',
			)
		);
		$row = ( new ArrangementRepository() )->get( $id );

		$editor = $this->service( 'wiw_editor' );
		$view   = $editor->view( $row );
		foreach ( DealService::MONEY_FIELDS as $field ) {
			$this->assertArrayNotHasKey( $field, $view );
		}
		$this->assertArrayHasKey( 'story_id', $view );
		foreach ( array(
			fn () => $editor->record_payment( $id, array( 'method' => 'cash' ) ),
			fn () => $editor->create(
				array(
					'customer_id'  => 4,
					'amount_minor' => 1,
				)
			),
			fn () => $editor->update( $id, array( 'amount_minor' => 1 ) ),
			fn () => $editor->cancel( $id ),
		) as $attempt ) {
			try {
				$attempt();
				$this->fail( 'An Editor changed money.' );
			} catch ( \DomainException $e ) {
				$this->assertNotSame( '', $e->getMessage() );
			}
		}
		$this->assertSame( '50000000', (string) ( new ArrangementRepository() )->get( $id )['amount_minor'] );
		$this->assertSame( '0', (string) ( new ArrangementRepository() )->get( $id )['cancelled'] );
		$this->assertSame( array( 'story_id' ), $editor->update( $id, array( 'story_id' => 12 ) ), 'An Editor may set the story.' );
	}

	public function test_REQ_269_new_deal_needs_a_customer_and_an_amount(): void {
		$admin = $this->service();
		foreach ( array( array( 'amount_minor' => 100 ), array( 'customer_id' => 4 ) ) as $fields ) {
			try {
				$admin->create( $fields );
				$this->fail( 'Deal created without customer or amount.' );
			} catch ( \DomainException $e ) {
				$this->assertNotSame( '', $e->getMessage() );
			}
		}
		$id = $admin->create(
			array(
				'customer_id'  => 4,
				'amount_minor' => 100,
			)
		);
		$this->assertSame( '1', (string) ( new ArrangementRepository() )->get( $id )['agreed'] );
	}

	public function test_REQ_179_card_like_number_in_reference_is_rejected(): void {
		$this->assertTrue( CardGuard::looks_like_card( '4111 1111 1111 1111' ) );
		$this->assertTrue( CardGuard::looks_like_card( 'card 5500-0000-0000-0004 ok' ) );
		$this->assertFalse( CardGuard::looks_like_card( 'TRX-2026-11-0042' ) );
		$this->assertFalse( CardGuard::looks_like_card( '123456789012' ) );
		$admin = $this->service();
		$id    = $admin->create(
			array(
				'customer_id'  => 4,
				'amount_minor' => 100,
			)
		);
		$this->expectException( \DomainException::class );
		$admin->record_payment(
			$id,
			array(
				'method'      => 'card',
				'reference'   => '4111111111111111',
				'received_on' => '2026-11-10',
			)
		);
	}

	public function test_REQ_226_pending_profile_cannot_be_linked_to_deal(): void {
		$states = array(
			1 => 'published_pending',
			2 => 'published_reviewed',
			3 => 'imported_not_public',
		);
		$admin  = $this->service( 'administrator', static fn ( int $id ): ?string => $states[ $id ] ?? null );
		foreach ( array( 1, 3, 99 ) as $profile ) {
			try {
				$admin->create(
					array(
						'customer_id'  => 4,
						'amount_minor' => 100,
						'profile_id'   => $profile,
					)
				);
				$this->fail( 'Linked profile ' . $profile );
			} catch ( \DomainException $e ) {
				$this->assertNotSame( '', $e->getMessage() );
			}
		}
		$this->assertSame( array(), ( new ArrangementRepository() )->all(), 'Nothing was stored.' );
		$id = $admin->create(
			array(
				'customer_id'  => 4,
				'amount_minor' => 100,
				'profile_id'   => 2,
			)
		);
		$this->assertArrayNotHasKey( 'profile_id', ( new ArrangementRepository() )->get( $id ), 'A deal has no profile column.' );
	}

	public function test_REQ_086_amount_change_is_audited(): void {
		$admin = $this->service();
		$id    = $admin->create(
			array(
				'customer_id'  => 4,
				'amount_minor' => 100,
			)
		);
		$admin->update( $id, array( 'amount_minor' => 250 ) );
		$admin->record_payment(
			$id,
			array(
				'method'      => 'transfer',
				'reference'   => 'SECRET-REF-77',
				'received_on' => '2026-11-10',
			)
		);
		$admin->cancel( $id );
		$actions = array_column( $this->audit_rows(), 'action' );
		foreach ( array( 'deal.create', 'deal.amount_change', 'deal.payment', 'deal.cancel' ) as $action ) {
			$this->assertContains( $action, $actions );
		}
		$dump = wp_json_encode( $this->audit_rows() );
		$this->assertStringNotContainsString( 'SECRET-REF-77', (string) $dump, 'The reference text is not in the audit log.' );
		$this->assertStringContainsString( '250', (string) $dump );
	}

	public function test_REQ_239_money_capability_fixed_to_administrator(): void {
		$this->assertContains( DealService::MONEY_CAP, ( new CapabilityCatalogue() )->fixed() );
		$editor = $this->service( 'wiw_editor' );
		$user   = wp_get_current_user();
		$user->add_cap( DealService::MONEY_CAP );
		$this->assertFalse( current_user_can( DealService::MONEY_CAP ), 'The fixed capability cannot be granted to an Editor.' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
		unset( $editor );
		$this->service( 'administrator' );
		$this->assertTrue( current_user_can( DealService::MONEY_CAP ) ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
	}
}
