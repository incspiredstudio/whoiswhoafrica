<?php
/**
 * Settings service (REQ-188, REQ-194, REQ-052). Needs WordPress and a database; audit rows go to a scratch table.
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

final class SettingsServiceTest extends TestCase {

	private const PROBE = 'wiw_settings_audit_probe';

	/**
	 * Capabilities the fake current user holds.
	 *
	 * @var array<int, string>
	 */
	private array $held = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		$this->held = array();
		foreach ( array_keys( SettingsService::default_registry() ) as $key ) {
			delete_option( SettingsService::OPTION_PREFIX . $key );
		}
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		foreach ( array_keys( SettingsService::default_registry() ) as $key ) {
			delete_option( SettingsService::OPTION_PREFIX . $key );
		}
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

	public function test_REQ_188_setting_change_requires_capability_and_is_audited(): void {
		$service = $this->service();

		try {
			$service->set( SettingsService::NOMINATIONS_ENABLED, true );
			$this->fail( 'A user without the capability changed a setting.' );
		} catch ( \DomainException $e ) {
			$this->assertFalse( $service->nominations_enabled() );
			$this->assertSame( array(), $this->audit_rows() );
		}

		$this->held = array( 'wiw_toggle_nominations' );
		$service->set( SettingsService::NOMINATIONS_ENABLED, true, 'Opening season' );
		$this->assertTrue( $service->nominations_enabled() );

		$rows = $this->audit_rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'settings.change', $rows[0]['action'] );
		$this->assertSame( 'nominations_enabled', $rows[0]['subject_id'] );
		$this->assertSame( 'false', $rows[0]['old_value'] );
		$this->assertSame( 'true', $rows[0]['new_value'] );
		$this->assertSame( 'Opening season', $rows[0]['reason'] );

		// Setting the same value again changes nothing and writes nothing.
		$service->set( SettingsService::NOMINATIONS_ENABLED, true );
		$this->assertCount( 1, $this->audit_rows() );
	}

	public function test_REQ_194_settings_are_stored_with_autoload_off(): void {
		global $wpdb;
		$this->held = array( 'wiw_toggle_nominations' );
		$this->service()->set( SettingsService::NOMINATIONS_REASON, 'Closed until January' );

		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", SettingsService::OPTION_PREFIX . SettingsService::NOMINATIONS_REASON ) ); // phpcs:ignore WordPress.DB
		$this->assertContains( $autoload, array( 'no', 'off' ), 'Stored with autoload on: ' . (string) $autoload );

		// An unregistered key can neither be read nor written.
		foreach ( array( 'get', 'set' ) as $method ) {
			try {
				'get' === $method ? $this->service()->get( 'surprise' ) : $this->service()->set( 'surprise', 'x' );
				$this->fail( 'Unregistered key accepted.' );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertFalse( get_option( SettingsService::OPTION_PREFIX . 'surprise', false ) );
			}
		}
	}

	public function test_REQ_052_brand_values_cannot_be_stored_as_settings(): void {
		foreach ( array( 'primary_colour', 'heading_font', 'site_logo', 'brand_name' ) as $key ) {
			try {
				new SettingsService(
					array(
						$key => array(
							'type'       => 'string',
							'default'    => '',
							'capability' => 'manage_options',
							'audited'    => false,
						),
					)
				);
				$this->fail( "Brand-like key accepted: $key" );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertStringContainsString( 'tokens', $e->getMessage() );
			}
		}

		// A brand value cannot be smuggled into an allowed text setting either.
		$this->held = array( 'wiw_toggle_nominations' );
		foreach ( array( '#1a2b3c', 'rgb(1,2,3)', 'font-family: Foo' ) as $value ) {
			try {
				$this->service()->set( SettingsService::NOMINATIONS_REASON, $value );
				$this->fail( "Brand value accepted: $value" );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertSame( '', $this->service()->get( SettingsService::NOMINATIONS_REASON ) );
			}
		}
	}
}
