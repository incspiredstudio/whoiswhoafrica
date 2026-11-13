<?php
/**
 * Personal-data exports are for holders of wiw_export_data only, and each one is audited (REQ-069, REQ-087). Needs the WordPress
 * test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Export\ExportGate;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\TestCase;

/**
 * Plain TestCase on purpose: users are real rows and the audit table is a scratch copy.
 */
final class ExportGateTest extends TestCase {

	private const PROBE = 'wiw_exportgate_audit_probe';

	/**
	 * Gate under test.
	 *
	 * @var ExportGate|null
	 */
	private ?ExportGate $gate = null;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		wp_set_current_user( 0 );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		$this->gate = new ExportGate(
			new AuditLogger(
				new WpdbConnection( self::PROBE ),
				static fn (): string => '2026-11-12 10:00:00',
				static fn (): int => (int) get_current_user_id()
			)
		);
		$this->gate->register();
	}

	protected function tearDown(): void {
		global $wpdb;
		if ( null !== $this->gate ) {
			$this->gate->unregister();
		}
		wp_set_current_user( 0 );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		parent::tearDown();
	}

	private function user( string $role, bool $with_export_cap ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'expgate' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$user = new \WP_User( $id );
		if ( $with_export_cap ) {
			$user->add_cap( 'wiw_export_data' );
			$user->add_cap( 'export' );
		} else {
			$user->remove_cap( 'wiw_export_data' );
		}
		return $id;
	}

	/**
	 * Audit rows with a given action.
	 *
	 * @param string $action Action.
	 * @return array<int, array<string, mixed>>
	 */
	private function rows( string $action ): array {
		global $wpdb;
		$all = (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB
		return array_values( array_filter( $all, static fn ( array $r ): bool => $action === $r['action'] ) );
	}

	public function test_REQ_069_reviewer_cannot_export_personal_data(): void {
		$reviewer = $this->user( 'wiw_reviewer', false );
		// Even a user who was handed the core export capability may not use it without wiw_export_data.
		( new \WP_User( $reviewer ) )->add_cap( 'export' );
		( new \WP_User( $reviewer ) )->add_cap( 'export_others_personal_data' );
		wp_set_current_user( $reviewer );

		$this->assertFalse( current_user_can( 'export' ) );
		$this->assertFalse( current_user_can( 'export_others_personal_data' ) );

		$refused = false;
		add_filter(
			'wp_die_handler',
			static fn () => static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);
		try {
			do_action( 'export_wp', array( 'content' => 'all' ) );
		} catch ( \RuntimeException $e ) {
			$refused = true;
		}
		$this->assertTrue( $refused, 'the core export download is refused for a user without wiw_export_data' );
		$this->assertSame( array(), $this->rows( 'export.download' ), 'a refused export is not an export' );
	}

	public function test_REQ_069_reviewer_and_editor_menus_show_no_export(): void {
		foreach ( array( 'wiw_reviewer', 'wiw_editor' ) as $role ) {
			$id = $this->user( $role, false );
			$this->assertFalse( user_can( $id, 'export' ), $role . ' has no Tools > Export' );
			$this->assertFalse( user_can( $id, ExportGate::CAPABILITY ), $role . ' holds no export capability' );
		}
	}

	public function test_REQ_069_administrator_export_is_audited(): void {
		$admin = $this->user( 'administrator', true );
		wp_set_current_user( $admin );
		$this->assertTrue( current_user_can( 'export' ) );

		do_action( 'export_wp', array( 'content' => 'wiw_profile' ) );

		$rows = $this->rows( 'export.download' );
		$this->assertCount( 1, $rows );
		$this->assertSame( (string) $admin, $rows[0]['actor_id'] );
		$this->assertSame( 'wp_export', $rows[0]['subject_type'] );
		$this->assertSame( 'wiw_profile', $rows[0]['subject_id'] );
		$this->assertSame( array( 'capability' => 'wiw_export_data' ), json_decode( (string) $rows[0]['new_value'], true ) );
	}

	public function test_REQ_069_export_audit_row_holds_no_personal_data(): void {
		$admin = $this->user( 'administrator', true );
		wp_set_current_user( $admin );
		do_action(
			'export_wp',
			array(
				'content' => 'all',
				'author'  => 'someone@example.org',
				'search'  => 'Ada Lovelace',
			)
		);
		$rows = $this->rows( 'export.download' );
		$this->assertCount( 1, $rows );
		$text = wp_json_encode( $rows[0] );
		$this->assertStringNotContainsString( 'someone@example.org', (string) $text );
		$this->assertStringNotContainsString( 'Lovelace', (string) $text );
		$this->assertSame( 'all', $rows[0]['subject_id'] );
	}

	public function test_REQ_087_list_table_export_helper_refuses_and_audits(): void {
		$reviewer = $this->user( 'wiw_reviewer', false );
		wp_set_current_user( $reviewer );
		$this->assertFalse( $this->gate->authorise( 'nominations', 12 ) );
		$this->assertSame( array(), $this->rows( 'export.download' ) );

		$admin = $this->user( 'administrator', true );
		wp_set_current_user( $admin );
		$this->assertTrue( $this->gate->authorise( 'nominations', 12 ) );
		$rows = $this->rows( 'export.download' );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'nominations', $rows[0]['subject_id'] );
		$this->assertSame(
			array(
				'capability' => 'wiw_export_data',
				'rows'       => 12,
			),
			json_decode( (string) $rows[0]['new_value'], true )
		);
	}
}
