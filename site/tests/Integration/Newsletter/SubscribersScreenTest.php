<?php
/**
 * The Subscribers screen (REQ-058, REQ-061, REQ-051). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Newsletter;

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Content\Newsletter\Admin\SubscribersScreen;
use WhoIsWho\Core\Content\Newsletter\SubscriberRepository;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class SubscribersScreenTest extends WpTestCase {

	private const PROBE = 'wiw_subscribers_audit_probe';

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::create_table( ConsentRecorder::TABLE, ConsentRecorder::TABLE_DEFINITION );
	}

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		$wpdb->query( 'DELETE FROM ' . SubscriberRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function screen_as( string $role ): SubscribersScreen {
		$user = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user );
		$audit = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00', static fn (): int => $user );
		$repo  = new SubscriberRepository( static fn (): string => '2026-11-12 09:00:00', new SettingsService( SettingsService::default_registry() ) );
		return new SubscribersScreen( $repo, $audit );
	}

	private function seed(): void {
		$repo = new SubscriberRepository( static fn (): string => '2026-11-12 09:00:00', new SettingsService( SettingsService::default_registry() ) );
		$repo->confirm( (string) $repo->add_pending( 'active@example.test' )['token'] );
		$repo->add_pending( 'pending@example.test' );
		$repo->confirm( (string) $repo->add_pending( 'gone@example.test' )['token'] );
		$repo->unsubscribe( 'gone@example.test' );
		$repo->add_pending( 'formula@example.test' );
	}

	public function test_REQ_058_subscribers_screen_requires_capability(): void {
		$registry = new ScreenRegistry();
		$registry->register( SubscribersScreen::SCREEN, 'Subscribers', SubscribersScreen::CAP, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), SubscribersScreen::CAP, SubscribersScreen::CAP );
		AuthorisationMatrix::assert_statuses(
			$this,
			SubscribersScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 403,
				'editor'        => 200,
				'administrator' => 200,
				'granted'       => 200,
				'revoked'       => 403,
			)
		);
		$this->expectException( \DomainException::class );
		$this->screen_as( 'wiw_reviewer' )->html();
	}

	public function test_REQ_061_subscribers_screen_lists_unsubscribed(): void {
		$this->seed();
		$screen = $this->screen_as( 'wiw_editor' );
		$counts = $screen->counts();
		$this->assertSame( 1, $counts['confirmed'] );
		$this->assertSame( 2, $counts['pending'] );
		$this->assertSame( 1, $counts['unsubscribed'] );

		$html = $screen->html( 'unsubscribed' );
		$this->assertStringContainsString( 'gone@example.test', $html );
		$this->assertStringNotContainsString( 'active@example.test', $html );
		$this->assertStringContainsString( '2026-11-12 09:00:00', $html, 'Consent and withdrawal dates are shown.' );
		$this->assertStringContainsString( 'active@example.test', $screen->html( 'confirmed' ) );
		$this->assertStringNotContainsString( 'type="text"', $html, 'Addresses are not editable.' );
	}

	public function test_REQ_051_subscriber_export_is_audited(): void {
		global $wpdb;
		$this->seed();
		$editor = $this->screen_as( 'wiw_editor' );
		$this->assertStringNotContainsString( 'admin-post.php', $editor->html(), 'An Editor is not offered the export.' );
		try {
			$editor->export_csv( 'confirmed' );
			$this->fail( 'An Editor exported subscribers.' );
		} catch ( \DomainException $refusal ) {
			$this->assertSame( 0, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . self::PROBE ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		}

		$admin = $this->screen_as( 'administrator' );
		$this->assertStringContainsString( 'admin-post.php', $admin->html() );
		$csv = $admin->export_csv( 'confirmed' );
		$this->assertStringContainsString( 'active@example.test', $csv );
		$this->assertStringNotContainsString( 'pending@example.test', $csv );

		$rows = (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE, ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		$this->assertCount( 1, $rows );
		$this->assertSame( 'subscribers.export', $rows[0]['action'] );
		$this->assertStringNotContainsString( '@', (string) wp_json_encode( $rows ), 'The audit entry holds no address.' );
	}

	public function test_REQ_051_export_neutralises_spreadsheet_formulas(): void {
		$repo = new SubscriberRepository( static fn (): string => '2026-11-12 09:00:00', new SettingsService( SettingsService::default_registry() ) );
		$repo->add_pending( 'x@example.test', '=HYPERLINK("http://evil")' );
		$csv = $this->screen_as( 'administrator' )->export_csv( 'pending' );
		$this->assertStringContainsString( '"\'=HYPERLINK(""http://evil"")"', $csv );
	}
}
