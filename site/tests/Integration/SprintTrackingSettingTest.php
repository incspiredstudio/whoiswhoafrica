<?php
/**
 * The Sprint tracking setting (REQ-272, REQ-188): on by default, Administrator only, every change audited, nothing lost when off.
 * Needs WordPress and a database; audit rows go to a scratch table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\TestCase;

final class SprintTrackingSettingTest extends TestCase {

	private const PROBE = 'wiw_sprint_audit_probe';

	/**
	 * Capabilities the fake current user holds.
	 *
	 * @var array<int, string>
	 */
	private array $held = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		$this->held = array();
		delete_option( SettingsService::OPTION_PREFIX . SettingsService::SPRINT_TRACKING );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		delete_option( SettingsService::OPTION_PREFIX . SettingsService::SPRINT_TRACKING );
		delete_option( SettingsService::OPTION_PREFIX . 'sprint_target_profiles' );
		parent::tearDown();
	}

	private function service(): SettingsService {
		$audit = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-11 10:00:00', static fn (): int => 9 );
		return new SettingsService( SettingsService::default_registry(), $audit, fn ( string $cap ): bool => in_array( $cap, $this->held, true ) );
	}

	/**
	 * Audit rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function audit_rows(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public function test_REQ_272_sprint_tracking_default_on_and_admin_only(): void {
		$service = $this->service();
		$this->assertTrue( $service->sprint_tracking_enabled(), 'Default is on.' );
		$this->assertTrue( wiw_sprint_tracking_enabled() );
		try {
			$service->set( SettingsService::SPRINT_TRACKING, false );
			$this->fail( 'A user without the capability changed the setting.' );
		} catch ( \DomainException $e ) {
			$this->assertTrue( $service->sprint_tracking_enabled() );
		}
		$this->held = array( 'wiw_review_records', 'wiw_manage_register' );
		try {
			$service->set( SettingsService::SPRINT_TRACKING, false );
			$this->fail( 'A staff role without the administrator capability changed the setting.' );
		} catch ( \DomainException $e ) {
			$this->assertTrue( $service->sprint_tracking_enabled() );
		}
		$this->held = array( 'manage_options' );
		$service->set( SettingsService::SPRINT_TRACKING, false );
		$this->assertFalse( $service->sprint_tracking_enabled() );
	}

	public function test_REQ_272_toggle_audited(): void {
		$this->held = array( 'manage_options' );
		$service    = $this->service();
		$service->set( SettingsService::SPRINT_TRACKING, false, 'Sprint finished' );
		$rows = $this->audit_rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'settings.change', $rows[0]['action'] );
		$this->assertSame( 'sprint_tracking', $rows[0]['subject_id'] );
		$this->assertSame( 'true', $rows[0]['old_value'] );
		$this->assertSame( 'false', $rows[0]['new_value'] );
		$this->assertSame( 'Sprint finished', $rows[0]['reason'] );
	}

	public function test_REQ_272_off_deletes_nothing_and_turning_it_back_on_restores_the_view(): void {
		$this->held = array( 'manage_options' );
		$service    = $this->service();
		$service->set( 'sprint_target_profiles', 500 );
		$service->set( SettingsService::SPRINT_TRACKING, false );
		$this->assertSame( 500, $service->get( 'sprint_target_profiles' ), 'The thresholds are kept while tracking is off.' );
		$service->set( SettingsService::SPRINT_TRACKING, true );
		$this->assertTrue( $service->sprint_tracking_enabled() );
	}

	public function test_REQ_272_the_screen_offers_the_switch_only_to_an_administrator(): void {
		$on = wiw_theme_settings_html( true, true, 'abc123' );
		$this->assertStringContainsString( 'Turn sprint tracking off', $on );
		$this->assertStringContainsString( 'name="_wpnonce" value="abc123"', $on );
		$this->assertStringContainsString( 'name="action" value="wiw_save_theme_settings"', $on );
		$off = wiw_theme_settings_html( false, true, 'abc123' );
		$this->assertStringContainsString( 'Turn sprint tracking on', $off );
		$readonly = wiw_theme_settings_html( true, false, 'abc123' );
		$this->assertStringNotContainsString( '<form', $readonly );
		$this->assertStringContainsString( 'Only an Administrator can change this.', $readonly );
	}
}
