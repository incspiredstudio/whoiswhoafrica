<?php
/**
 * Cancel, refund note and internal notes on deals (REQ-086, REQ-115, REQ-187). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Commerce;

use WhoIsWho\Core\Commerce\ArrangementRepository;
use WhoIsWho\Core\Commerce\DealService;
use WhoIsWho\Core\Commerce\PlacementRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\TestCase;

final class DealCancelTest extends TestCase {

	private const PROBE = 'wiw_cancel_audit_probe';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		foreach ( array( ArrangementRepository::table(), PlacementRepository::table() ) as $table ) {
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

	/**
	 * A service run as a user with a role.
	 *
	 * @param string $role Role.
	 */
	private function service( string $role = 'administrator' ): DealService {
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
		return new DealService( new ArrangementRepository(), $audit, static fn (): \DateTimeImmutable => new \DateTimeImmutable( '2026-11-12 10:00:00', new \DateTimeZone( 'UTC' ) ) );
	}

	/**
	 * Audit actions.
	 *
	 * @return array<int, string>
	 */
	private function actions(): array {
		global $wpdb;
		return array_map( 'strval', (array) $wpdb->get_col( 'SELECT action FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id' ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
	}

	public function test_REQ_086_cancel_deal_ends_slot_and_is_audited(): void {
		$admin = $this->service();
		$deal  = $admin->create(
			array(
				'customer_id'  => 4,
				'amount_minor' => 100,
			)
		);
		$story = (int) wp_insert_post(
			array(
				'post_title'  => 'S',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);
		$place = ( new PlacementRepository() )->insert(
			array(
				'arrangement_id' => $deal,
				'story_id'       => $story,
				'sponsor_name'   => 'Obi',
				'slot'           => 'home',
				'starts_on'      => '2026-12-01',
				'ends_on'        => '2026-12-31',
			),
			new \DateTimeImmutable( '2026-11-12', new \DateTimeZone( 'UTC' ) )
		);
		$admin->cancel( $deal );
		$this->assertSame( '1', (string) ( new ArrangementRepository() )->get( $deal )['cancelled'] );
		$this->assertSame( '1', (string) ( new PlacementRepository() )->get( $place )['ended'], 'The slot is ended, not deleted.' );
		$this->assertContains( 'deal.cancel', $this->actions() );
	}

	public function test_REQ_115_refund_recorded_as_note_only(): void {
		$admin = $this->service();
		$deal  = $admin->create(
			array(
				'customer_id'  => 4,
				'amount_minor' => 100,
				'notes'        => 'First note',
			)
		);
		$admin->record_refund_note( $deal, 'Refunded in full by bank transfer.' );
		$row = ( new ArrangementRepository() )->get( $deal );
		$this->assertStringContainsString( 'First note', (string) $row['notes'] );
		$this->assertStringContainsString( '[Refund 2026-11-12] Refunded in full by bank transfer.', (string) $row['notes'] );
		$this->assertSame( '100', (string) $row['amount_minor'], 'The amount is unchanged.' );
		$this->assertContains( 'deal.refund_note', $this->actions() );
		$this->assertStringNotContainsString( 'Refunded in full', (string) wp_json_encode( $this->actions() ) );
		foreach ( array( '', '4111 1111 1111 1111' ) as $bad ) {
			try {
				$admin->record_refund_note( $deal, $bad );
				$this->fail( 'A bad refund note was accepted.' );
			} catch ( \DomainException $e ) {
				$this->assertNotSame( '', $e->getMessage() );
			}
		}
		$editor = $this->service( 'wiw_editor' );
		$this->expectException( \DomainException::class );
		$editor->record_refund_note( $deal, 'Nope' );
	}

	public function test_REQ_187_deal_notes_never_public(): void {
		$root  = dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/src';
		$found = array();
		foreach ( array( 'Content', 'Directory', 'Awards' ) as $dir ) {
			$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/' . $dir, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $files as $file ) {
				if ( $file instanceof \SplFileInfo && 'php' === $file->getExtension() ) {
					$code = (string) file_get_contents( $file->getPathname() );
					if ( str_contains( $code, 'wiw_arrangements' ) || str_contains( $code, 'ArrangementRepository' ) || str_contains( $code, 'wiw_customers' ) ) {
						$found[] = $file->getFilename();
					}
				}
			}
		}
		$this->assertSame( array(), $found, 'No public read model touches deals or customers.' );
		$admin = $this->service();
		$deal  = $admin->create(
			array(
				'customer_id'  => 4,
				'amount_minor' => 100,
				'notes'        => 'private words',
			)
		);
		$this->assertArrayHasKey( 'notes', $this->service( 'wiw_editor' )->view( ( new ArrangementRepository() )->get( $deal ) ), 'Staff see the notes.' );
		wp_set_current_user( 0 );
		$this->assertArrayNotHasKey( 'amount_minor', $admin->view( ( new ArrangementRepository() )->get( $deal ) ), 'A visitor sees no money field.' );
	}
}
