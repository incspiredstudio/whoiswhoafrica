<?php
/**
 * The Claims screen (REQ-252, REQ-256). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Trust;

use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Trust\Admin\ClaimsScreen;
use WhoIsWho\Core\Trust\ClaimDecision;
use WhoIsWho\Core\Trust\ClaimRepository;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class ClaimsScreenTest extends WpTestCase {

	private string $now = '2026-11-12 10:00:00';

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::create_table( ClaimRepository::TABLE, ClaimRepository::TABLE_DEFINITION );
	}

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		$wpdb->query( 'DELETE FROM ' . ClaimRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		delete_option( ClaimRepository::SEQUENCE_OPTION );
	}

	protected function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function screen(): ClaimsScreen {
		$claims = new ClaimRepository();
		return new ClaimsScreen( $claims, new ClaimDecision( $claims, new StateRepository(), Notifier::for_site() ), fn (): string => $this->now );
	}

	private function claim( string $received = '2026-11-10 10:00:00' ): int {
		return ( new ClaimRepository() )->create(
			array(
				'profile_id'   => 4242,
				'relationship' => 'representative',
				'name'         => 'Ada Obi',
				'role'         => 'Director of Comms',
				'work_email'   => 'ada@example.test',
				'phone'        => '+234 800 000 0000',
				'evidence'     => 'Staff page lists me.',
				'consent'      => true,
			),
			new \DateTimeImmutable( $received, new \DateTimeZone( 'UTC' ) )
		)->id;
	}

	private function as_role( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	public function test_REQ_252_claims_screen_shows_every_form_field(): void {
		$this->claim();
		$this->as_role( 'wiw_editor' );
		$html = $this->screen()->html();
		foreach ( array( 'CLM-0001', 'representative', 'Ada Obi', 'Director of Comms', 'ada@example.test', '+234 800 000 0000', 'Staff page lists me.', '2026-11-10 10:00:00', '2026-11-15 10:00:00', 'Consent given', 'None sent' ) as $expected ) {
			$this->assertStringContainsString( $expected, $html );
		}
		$this->assertStringContainsString( 'name="_wpnonce"', $html );
	}

	public function test_REQ_256_claims_screen_marks_overdue_claim(): void {
		$this->claim( '2026-11-01 10:00:00' );
		$this->as_role( 'wiw_editor' );
		$this->assertStringContainsString( 'Overdue', $this->screen()->html() );

		$this->now = '2026-11-11 10:00:00';
		$fresh     = $this->claim( '2026-11-10 10:00:00' );
		$this->assertGreaterThan( 0, $fresh );
		( new ClaimRepository() )->mark_reminded( $fresh, new \DateTimeImmutable( '2026-11-11 08:00:00', new \DateTimeZone( 'UTC' ) ) );
		$this->assertStringContainsString( '2026-11-11 08:00:00', $this->screen()->html() );
	}

	public function test_REQ_252_claims_screen_requires_wiw_review_claims(): void {
		$registry = new ScreenRegistry();
		$registry->register( ClaimsScreen::SCREEN, 'Claims', ClaimsScreen::CAP, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), ClaimsScreen::CAP, ClaimsScreen::CAP );
		AuthorisationMatrix::assert_statuses(
			$this,
			ClaimsScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 403,
				'editor'        => 200,
				'administrator' => 200,
				'granted'       => 200,
				'revoked'       => 403,
			)
		);
		$this->as_role( 'wiw_reviewer' );
		$this->expectException( \DomainException::class );
		$this->screen()->html();
	}

	public function test_REQ_252_rejecting_without_a_reason_shows_an_error_and_changes_nothing(): void {
		$id = $this->claim();
		$this->as_role( 'wiw_editor' );
		$message = $this->screen()->handle(
			new Request(
				array(
					'claim_id' => (string) $id,
					'decision' => 'reject',
					'reason'   => '  ',
				)
			),
			get_current_user_id()
		);
		$this->assertStringContainsString( 'needs a reason', $message );
		$this->assertSame( 'new', ( new ClaimRepository() )->find( $id )->status );
	}
}
