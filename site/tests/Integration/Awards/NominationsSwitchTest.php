<?php
/**
 * The Nominations flag: off by default, toggled only by an Administrator, every change audited (REQ-194, REQ-259, REQ-267).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\NominationsSwitch;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\TestCase;

final class NominationsSwitchTest extends TestCase {

	private const PROBE = 'wiw_nominations_audit_probe';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		delete_option( SettingsService::OPTION_PREFIX . SettingsService::NOMINATIONS_ENABLED );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		delete_option( SettingsService::OPTION_PREFIX . SettingsService::NOMINATIONS_ENABLED );
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

	private function switch_for( int $actor ): NominationsSwitch {
		wp_set_current_user( 0 );
		wp_set_current_user( $actor );
		$audit    = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-11 10:00:00', static fn (): int => $actor );
		$settings = new SettingsService( SettingsService::default_registry(), $audit );
		return new NominationsSwitch( $settings );
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

	public function test_REQ_194_nominations_flag_defaults_off_and_only_administrator_toggles_with_audit(): void {
		$admin  = $this->user( 'administrator' );
		$editor = $this->user( 'wiw_editor' );

		$this->assertFalse( $this->switch_for( $editor )->is_on(), 'Off on a fresh install.' );

		try {
			$this->switch_for( $editor )->apply( true, 'trying' );
			$this->fail( 'An Editor changed the flag.' );
		} catch ( \DomainException $e ) {
			$this->assertFalse( $this->switch_for( $editor )->is_on() );
			$this->assertSame( array(), $this->rows() );
		}

		$this->switch_for( $admin )->apply( true, 'Season opens' );
		$this->assertTrue( $this->switch_for( $admin )->is_on() );
		$this->switch_for( $admin )->apply( false );
		$this->assertFalse( $this->switch_for( $admin )->is_on() );

		$rows = $this->rows();
		$this->assertCount( 2, $rows );
		$this->assertSame( (string) $admin, (string) $rows[0]['actor_id'] );
		$this->assertSame( '2026-11-11 10:00:00', $rows[0]['at'] );
		$this->assertSame( 'Season opens', $rows[0]['reason'] );
		$this->assertSame( 'nominations_enabled', $rows[0]['subject_id'] );
	}

	public function test_REQ_194_the_screen_post_is_refused_for_an_editor(): void {
		$editor = $this->user( 'wiw_editor' );
		$this->switch_for( $editor );
		$this->assertFalse( current_user_can( NominationsSwitch::TOGGLE_CAP ) );
		$this->assertTrue( current_user_can( NominationsSwitch::SCREEN_CAP ) );
		$admin = $this->user( 'administrator' );
		$this->switch_for( $admin );
		$this->assertTrue( current_user_can( NominationsSwitch::TOGGLE_CAP ) );
	}

	public function test_REQ_267_editor_sees_the_toggle_read_only(): void {
		$editor = $this->user( 'wiw_editor' );
		$html   = $this->switch_for( $editor )->html( false );
		$this->assertStringContainsString( 'Nominations are off', $html );
		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringNotContainsString( 'type="submit"', $html );

		$admin = $this->user( 'administrator' );
		$html  = $this->switch_for( $admin )->html( true );
		$this->assertStringContainsString( 'Nominations are on', $html );
		$this->assertStringContainsString( '<form', $html );
		$this->assertStringContainsString( 'name="reason"', $html );
	}

	public function test_REQ_194_nominations_is_the_only_feature_flag(): void {
		$bools = array();
		foreach ( SettingsService::default_registry() as $key => $definition ) {
			if ( 'bool' === $definition['type'] && 0 === preg_match( '/^import_/', $key ) ) {
				$bools[] = $key;
			}
		}
		$this->assertSame( array( SettingsService::NOMINATIONS_ENABLED ), $bools );
		foreach ( array_keys( SettingsService::default_registry() ) as $key ) {
			$this->assertDoesNotMatchRegularExpression( '/feature|flag|beta|experiment/i', $key );
		}
	}
}
