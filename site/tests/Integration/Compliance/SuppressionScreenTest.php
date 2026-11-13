<?php
/**
 * The Suppression list screen (REQ-207, REQ-147, REQ-244): viewing by capability, editing only with wiw_edit_suppression. Needs the
 * WordPress test suite and the real tables, so it is a plain TestCase.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Admin\Suppression\SuppressionScreen;
use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

final class SuppressionScreenTest extends TestCase {

	/**
	 * Users to remove.
	 *
	 * @var int[]
	 */
	private array $users = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		$wpdb->query( 'DELETE FROM ' . Suppression::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . Suppression::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function as_role( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'user_email' => wp_generate_password( 8, false ) . '@example.test',
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		wp_set_current_user( 0 );
		wp_set_current_user( $id );
		return $id;
	}

	private function service(): Suppression {
		return new Suppression( AuditLogger::for_site() );
	}

	public function test_REQ_207_reviewer_can_view_but_not_edit_suppression(): void {
		$admin = $this->as_role( 'administrator' );
		$this->service()->add_manual( 'Hidden Person Zed', 'individual', array(), 'Asked to be left out' );
		$this->assertGreaterThan( 0, $admin );

		foreach ( array( 'wiw_reviewer', 'wiw_editor' ) as $role ) {
			$this->as_role( $role );
			$html = ( new SuppressionScreen() )->html( new Request( array() ) );
			$this->assertStringContainsString( 'Hidden Person Zed', $html, "{$role} sees the list" );
			$this->assertStringContainsString( 'Asked to be left out', $html, 'the reason is shown to staff' );
			$this->assertStringNotContainsString( '<form', $html, "{$role} gets no edit controls" );
			$request = new Request( array( '_wpnonce' => ActionGuard::nonce_for( SuppressionScreen::ADD ) ) );
			$this->assertSame( ActionGuard::REFUSED_CAPABILITY, ActionGuard::check( SuppressionScreen::ADD, SuppressionScreen::EDIT_CAP, $request ) );
			$this->assertSame( ActionGuard::REFUSED_CAPABILITY, ActionGuard::check( SuppressionScreen::REMOVE, SuppressionScreen::EDIT_CAP, $request ) );
			$this->assert_cannot_add( $role );
		}
	}

	/**
	 * The service itself refuses a manual add from a user without the capability.
	 *
	 * @param string $role Role in force.
	 */
	private function assert_cannot_add( string $role ): void {
		try {
			$this->service()->add_manual( 'Nope', 'individual', array(), 'x' );
			$this->fail( "{$role} must not add an entry" );
		} catch ( \DomainException $e ) {
			$this->assertNotSame( '', $e->getMessage() );
		}
	}

	public function test_REQ_207_administrator_can_add_manual_entry(): void {
		$this->as_role( 'administrator' );
		$html = ( new SuppressionScreen() )->html( new Request( array() ) );
		$this->assertStringContainsString( SuppressionScreen::ADD, $html, 'the add form is shown' );

		$request = new Request( array( '_wpnonce' => ActionGuard::nonce_for( SuppressionScreen::ADD ) ) );
		$this->assertNull( ActionGuard::check( SuppressionScreen::ADD, SuppressionScreen::EDIT_CAP, $request ) );
		$id = $this->service()->add_manual( 'Manual Entry Qux', 'organisation', array(), 'Legal advice' );
		$this->assertStringContainsString( 'Manual Entry Qux', ( new SuppressionScreen() )->html( new Request( array() ) ) );

		$this->service()->remove_manual( $id, 'Entered in error' );
		$this->assertNull( $this->service()->get( $id ) );
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT reason FROM {$wpdb->prefix}wiw_audit_log WHERE action = 'suppression.remove' AND subject_id = %s ORDER BY id DESC LIMIT 1", (string) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$this->assertSame( 'Entered in error', $row['reason'] ?? '' );
	}

	public function test_REQ_244_a_user_without_the_view_capability_is_refused(): void {
		$this->as_role( 'subscriber' );
		$this->expectException( \DomainException::class );
		( new SuppressionScreen() )->html( new Request( array() ) );
	}

	public function test_REQ_207_snapshot_download_button_shows_only_with_the_export_capability(): void {
		$this->as_role( 'administrator' );
		$html = ( new SuppressionScreen() )->html( new Request( array() ) );
		$this->assertStringContainsString( 'Download snapshot for acquisition', $html );
		$this->assertStringContainsString( 'action=wiw_suppression_snapshot', html_entity_decode( $html ) );
		$this->assertStringContainsString( 'personal data', strtolower( $html ), 'the file is labelled as personal data' );

		$this->as_role( 'wiw_reviewer' );
		$html = ( new SuppressionScreen() )->html( new Request( array() ) );
		$this->assertStringNotContainsString( 'Download snapshot', $html );
	}
}
