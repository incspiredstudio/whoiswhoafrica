<?php
/**
 * The sprint thresholds settings and screen (REQ-272, REQ-181). Needs the WordPress test suite. Plain TestCase with a probe audit
 * table so the real log is never touched.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Platform\Settings\SprintScreen;
use WhoIsWho\Tests\TestCase;

final class SprintThresholdsTest extends TestCase {

	private const PROBE = 'wiw_sprint_audit_probe';

	/**
	 * Whether the fake user is an Administrator.
	 *
	 * @var bool
	 */
	private bool $admin = true;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		foreach ( array( SettingsService::SPRINT_TRACKING, SprintScreen::COUNT_KEY, SprintScreen::REVENUE_KEY ) as $key ) {
			delete_option( SettingsService::OPTION_PREFIX . $key );
		}
		$this->admin = true;
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		parent::tearDown();
	}

	private function settings(): SettingsService {
		return new SettingsService(
			SettingsService::default_registry(),
			new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00', static fn (): int => 5 ),
			fn ( string $capability ): bool => $this->admin && 'manage_options' === $capability
		);
	}

	/**
	 * Audit rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function audit_rows(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- probe table.
	}

	public function test_REQ_272_sprint_tracking_defaults_on_and_hides_screen_when_off(): void {
		$settings = $this->settings();
		$this->assertTrue( $settings->sprint_tracking_enabled(), 'on by default' );
		$registry = new ScreenRegistry();
		SprintScreen::register( $registry, $settings );
		$this->assertArrayHasKey( SprintScreen::SCREEN, $registry->screens() );
		$this->assertSame( 'manage_options', $registry->screens()[ SprintScreen::SCREEN ]['capability'] );

		( new SprintScreen( $settings ) )->save( 12, 7500000 );
		$settings->set( SettingsService::SPRINT_TRACKING, false );
		$off = new ScreenRegistry();
		SprintScreen::register( $off, $settings );
		$this->assertArrayNotHasKey( SprintScreen::SCREEN, $off->screens(), 'hidden while tracking is off' );
		$this->assertSame( 12, $settings->get( SprintScreen::COUNT_KEY ), 'the stored thresholds are kept' );
		$this->assertSame( 750000000, $settings->get( SprintScreen::REVENUE_KEY ) );
	}

	public function test_REQ_272_the_helper_follows_the_setting(): void {
		$this->assertTrue( function_exists( 'sprint_tracking_on' ), 'the plugin registers the helper' );
		$this->assertTrue( sprint_tracking_on() );
		update_option( SettingsService::OPTION_PREFIX . SettingsService::SPRINT_TRACKING, '0', false );
		$this->assertFalse( sprint_tracking_on() );
	}

	public function test_REQ_181_thresholds_are_audited_and_administrator_only(): void {
		$screen = new SprintScreen( $this->settings() );
		$this->assertSame( 10, $this->settings()->get( SprintScreen::COUNT_KEY ), 'ten counted deals by default' );
		$screen->save( 15, 6000000 );
		$rows = $this->audit_rows();
		$this->assertCount( 2, $rows, 'each changed threshold is audited' );
		$this->assertSame( 'settings.change', $rows[0]['action'] );

		$this->admin = false;
		$this->expectException( \DomainException::class );
		( new SprintScreen( $this->settings() ) )->save( 20, 6000000 );
	}

	public function test_REQ_181_revenue_threshold_is_integer_kobo(): void {
		$settings = $this->settings();
		$this->assertSame( 500000000, $settings->get( SprintScreen::REVENUE_KEY ), 'NGN 5m is 500,000,000 kobo' );
		( new SprintScreen( $settings ) )->save( 10, 6000000 );
		$stored = get_option( SettingsService::OPTION_PREFIX . SprintScreen::REVENUE_KEY );
		$this->assertSame( '600000000', (string) $stored );
		$this->assertIsInt( $settings->get( SprintScreen::REVENUE_KEY ) );
		$html = ( new SprintScreen( $settings ) )->html();
		$this->assertStringContainsString( 'NGN', $html );
		$this->assertStringContainsString( 'value="6000000"', $html );
		$this->assertStringNotContainsString( 'progress', strtolower( $html ), 'no progress bar is created' );
	}

	public function test_REQ_181_a_threshold_must_be_a_whole_number(): void {
		$this->expectException( \InvalidArgumentException::class );
		( new SprintScreen( $this->settings() ) )->save( -1, 5000000 );
	}
}
