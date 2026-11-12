<?php
/**
 * The suppression list (REQ-207, REQ-147). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Tests\TestCase;

/**
 * Plain TestCase on purpose (real tables, see ProfileTablesTest); the table is emptied in setUp.
 */
final class SuppressionTest extends TestCase {

	private const PROBE = 'wiw_suppression_audit_probe';

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

	private function service( int $actor = 0 ): Suppression {
		wp_set_current_user( 0 );
		wp_set_current_user( $actor );
		return new Suppression( new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-11 10:00:00', static fn (): int => $actor ) );
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

	public function test_REQ_207_suppression_matches_by_id_or_normalised_name(): void {
		$service = $this->service();
		$service->add_from_removal( 'Ada  Obi-Eze', 'individual', array( 'CAC' => 'RC-123' ), 7 );

		$by_id = $service->match( 'Someone Else Entirely', 'organisation', array( 'cac' => 'rc-123' ) );
		$this->assertSame( 'external_id', $by_id['by'] ?? null, 'an external id matches on its own, in any case' );

		$by_name = $service->match( 'ADA OBI EZE', 'individual' );
		$this->assertSame( 'name', $by_name['by'] ?? null, 'the normalised name and type match' );

		$different_id = $service->match( 'ada obi-eze', 'individual', array( 'cac' => 'RC-999' ) );
		$this->assertNotNull( $different_id, 'a name match with a different external id still counts until a person decides' );

		$this->assertNull( $service->match( 'Ada Obi-Eze', 'organisation' ), 'another entity type does not match on name' );
		$this->assertNull( $service->match( 'Someone Else', 'individual', array( 'cac' => 'RC-124' ) ) );
		$this->assertNull( $service->match( '', 'individual' ) );
		$this->assertSame( 'cafe and co', Suppression::normalise_name( 'Café & Co.' ) );
	}

	public function test_REQ_207_only_administrator_edits_suppression_manually(): void {
		$admin = $this->user( 'administrator' );
		foreach ( array( 'wiw_editor', 'wiw_reviewer' ) as $role ) {
			$service = $this->service( $this->user( $role ) );
			try {
				$service->add_manual( 'Blocked Person', 'individual', array(), 'x' );
				$this->fail( $role . ' must not add an entry by hand' );
			} catch ( \DomainException $e ) {
				$this->assertStringContainsString( 'wiw_edit_suppression', $e->getMessage() );
			}
		}
		$id = $this->service( $admin )->add_manual( 'Real Person', 'individual', array( 'src' => '1' ), 'Asked by phone' );
		$this->assertGreaterThan( 0, $id );

		$editor = $this->service( $this->user( 'wiw_editor' ) );
		try {
			$editor->remove_manual( $id );
			$this->fail( 'an Editor must not remove an entry' );
		} catch ( \DomainException $e ) {
			$this->assertNotNull( $this->service()->get( $id ) );
		}
		$this->service( $admin )->remove_manual( $id );
		$this->assertNull( $this->service()->get( $id ) );
	}

	public function test_REQ_207_automatic_additions_need_no_manual_capability_and_are_audited_without_the_name(): void {
		$editor  = $this->user( 'wiw_editor' );
		$service = $this->service( $editor );
		$id      = $service->add_from_removal( 'Auto Person', 'individual', array(), 31 );
		$this->assertSame( 'removal', $service->get( $id )['source'] );
		$service->add_declined_nominee( 'Nominee Org', 'organisation' );

		$rows = $this->rows();
		$this->assertCount( 2, $rows );
		$this->assertSame( 'suppression.add', $rows[0]['action'] );
		$this->assertSame( (string) $id, $rows[0]['subject_id'] );
		$this->assertStringNotContainsString( 'Auto Person', wp_json_encode( $rows ) );
	}

	public function test_REQ_207_manual_changes_are_audited_and_the_reason_stays_off_the_audit_log(): void {
		$admin   = $this->user( 'administrator' );
		$service = $this->service( $admin );
		$id      = $service->add_manual( 'Kept Person', 'individual', array(), 'Secret reason text' );
		$service->remove_manual( $id );
		$rows = $this->rows();
		$this->assertSame( array( 'suppression.add', 'suppression.remove' ), array_column( $rows, 'action' ) );
		$this->assertStringNotContainsString( 'Secret reason text', wp_json_encode( $rows ) );
		$this->assertStringNotContainsString( 'Kept Person', wp_json_encode( $rows ) );
	}

	public function test_REQ_207_suppression_cannot_be_bypassed_by_import(): void {
		$service = $this->service();
		$service->add_from_removal( 'Removed Person', 'individual', array( 'cac' => 'RC-5' ), 1 );
		$this->assertSame(
			'SUPPRESSED',
			$service->refusal_for_import(
				array(
					'name'         => 'removed person',
					'entity_type'  => 'individual',
					'external_ids' => array(),
				)
			)
		);
		$this->assertSame(
			'SUPPRESSED',
			$service->refusal_for_import(
				array(
					'name'         => 'A new spelling',
					'entity_type'  => 'individual',
					'external_ids' => array( 'cac' => 'RC-5' ),
				)
			)
		);
		$this->assertNull(
			$service->refusal_for_import(
				array(
					'name'         => 'Someone New',
					'entity_type'  => 'individual',
					'external_ids' => array(),
				)
			)
		);
	}

	public function test_REQ_207_reason_text_is_not_part_of_any_export(): void {
		$admin   = $this->user( 'administrator' );
		$service = $this->service( $admin );
		$service->add_manual( 'Snapshot Person', 'individual', array( 'cac' => 'RC-8' ), 'Never leaves the site' );
		$snapshot = $service->snapshot();
		$this->assertCount( 1, $snapshot );
		$this->assertSame( array( 'suppression_id', 'entity_type', 'name', 'external_ids' ), array_keys( $snapshot[0] ) );
		$this->assertSame( array( 'cac' => 'rc-8' ), $snapshot[0]['external_ids'] );
		$this->assertStringNotContainsString( 'Never leaves', wp_json_encode( $snapshot ) );

		$entry = ( new PiiCatalogue() )->find( 'wiw_suppression.reason' );
		$this->assertSame( 'exclude', $entry['export'] ?? null );
	}

	public function test_REQ_207_suppression_not_purged_by_retention(): void {
		$catalogue = new PiiCatalogue();
		foreach ( array( 'name', 'name_key', 'id_keys', 'reason' ) as $column ) {
			$entry = $catalogue->find( 'wiw_suppression.' . $column );
			$this->assertNotNull( $entry, $column );
			$this->assertSame( 'permanent', $entry['retention'] );
			$this->assertSame( 'keep', $entry['erase'], 'the retention job must keep it' );
		}
	}

	public function test_REQ_207_bad_input_is_refused(): void {
		$service = $this->service();
		$this->expectException( \InvalidArgumentException::class );
		$service->add_from_removal( '   ', 'individual', array(), 1 );
	}
}
