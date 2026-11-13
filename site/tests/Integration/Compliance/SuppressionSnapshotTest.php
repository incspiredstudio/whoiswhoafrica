<?php
/**
 * The suppression snapshot for acquisition (REQ-207, contract section 11). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Suppression\SnapshotExport;
use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\TestCase;

/**
 * Plain TestCase on purpose (real tables); the table is emptied in setUp.
 */
final class SuppressionSnapshotTest extends TestCase {

	private const PROBE = 'wiw_suppression_snapshot_probe';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$wpdb->query( 'TRUNCATE TABLE ' . Suppression::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
	}

	protected function tearDown(): void {
		global $wpdb;
		wp_set_current_user( 0 );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		parent::tearDown();
	}

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		return $id;
	}

	private function audit( int $actor ): AuditLogger {
		return new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-11 10:00:00', static fn (): int => $actor );
	}

	private function export_service( int $actor ): SnapshotExport {
		$audit = $this->audit( $actor );
		return new SnapshotExport( new Suppression( $audit ), $audit );
	}

	/**
	 * Audit rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function rows(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public function test_REQ_207_snapshot_has_no_reason_text(): void {
		$admin = $this->user( 'administrator' );
		wp_set_current_user( $admin );
		( new Suppression( $this->audit( $admin ) ) )->add_manual( 'Real Person', 'individual', array( 'cac' => 'RC-1' ), 'Private reason: asked by phone' );

		$file = $this->export_service( $admin )->export( $admin );
		$data = json_decode( $file['json'], true );
		$this->assertIsArray( $data );
		$this->assertSame( array( 'entries', 'generated_at', 'snapshot_id' ), $this->sorted_keys( $data ) );
		$this->assertSame( $file['snapshot_id'], $data['snapshot_id'] );
		$this->assertCount( 1, $data['entries'] );
		$this->assertSame( array( 'entity_type', 'external_ids', 'name', 'suppression_id' ), $this->sorted_keys( $data['entries'][0] ) );
		$this->assertSame( 'Real Person', $data['entries'][0]['name'] );
		$this->assertSame( array( 'cac' => 'rc-1' ), $data['entries'][0]['external_ids'] );
		$this->assertStringNotContainsString( 'Private reason', $file['json'] );
		$this->assertStringNotContainsString( 'manual', $file['json'] );
	}

	public function test_REQ_207_snapshot_download_is_audited(): void {
		$admin = $this->user( 'administrator' );
		wp_set_current_user( $admin );
		( new Suppression( $this->audit( $admin ) ) )->add_manual( 'Real Person', 'individual', array(), 'x' );
		$this->export_service( $admin )->export( $admin );
		$rows = array_values( array_filter( $this->rows(), static fn ( array $r ): bool => 'suppression.snapshot_export' === $r['action'] ) );
		$this->assertCount( 1, $rows );
		$this->assertStringNotContainsString( 'Real Person', (string) wp_json_encode( $rows[0] ), 'the audit row holds a count, never a name' );
	}

	public function test_REQ_207_only_a_user_with_the_export_capability_downloads_the_snapshot(): void {
		foreach ( array( 'wiw_editor', 'wiw_reviewer' ) as $role ) {
			$user = $this->user( $role );
			try {
				$this->export_service( $user )->export( $user );
				$this->fail( $role . ' must not download the snapshot' );
			} catch ( \DomainException $e ) {
				$this->assertStringContainsString( 'wiw_export_data', $e->getMessage() );
			}
		}
		$this->assertSame( array(), $this->rows(), 'a refused download writes nothing' );
	}

	/**
	 * Sorted keys of an array.
	 *
	 * @param array<string, mixed> $value Array.
	 * @return string[]
	 */
	private function sorted_keys( array $value ): array {
		$keys = array_map( 'strval', array_keys( $value ) );
		sort( $keys );
		return $keys;
	}
}
