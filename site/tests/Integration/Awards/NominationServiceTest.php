<?php
/**
 * Staff-run nominations: add profile, stage, status, remove (REQ-267, REQ-202, REQ-034, REQ-226). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\NominationService;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\WpTestCase;

final class NominationServiceTest extends WpTestCase {

	private const PROBE = 'wiw_nominations_svc_audit_probe';

	/**
	 * Profile states by id.
	 *
	 * @var array<int, string>
	 */
	private array $states = array(
		101 => 'published_reviewed',
		102 => 'published_pending',
		103 => 'unpublished',
	);

	private int $actor = 0;

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		foreach ( AwardsTables::definitions() as $name => $definition ) {
			Schema::create_table( $name, $definition );
		}
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
		foreach ( array( AwardsTables::CATEGORIES, AwardsTables::NOMINATIONS, AwardsTables::CHANGES ) as $name ) {
			$wpdb->query( 'DELETE FROM ' . AwardsTables::table( $name ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function audit(): AuditLogger {
		return new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00', fn (): int => $this->actor );
	}

	private function service_as( string $role ): NominationService {
		$this->actor = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $this->actor );
		return new NominationService( $this->audit(), fn ( int $id ): ?string => $this->states[ $id ] ?? null );
	}

	private function category(): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::CATEGORIES ),
			array(
				'group_key'  => 'technology-innovation',
				'title'      => 'Digital Pioneer',
				'created_at' => '2026-11-12 10:00:00',
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Audit rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function audit_rows(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
	}

	public function test_REQ_267_add_profile_creates_a_nomination_linked_to_the_profile(): void {
		$service  = $this->service_as( 'wiw_editor' );
		$category = $this->category();
		$id       = $service->add_profile( 101, $category, 'Strong record', 'press@example.org' );
		$row      = $service->find( $id );
		$this->assertSame( '101', (string) $row['profile_id'] );
		$this->assertSame( (string) $category, (string) $row['category_id'] );
		$this->assertSame( 'staff', $row['source'] );

		foreach ( array( 999, 103 ) as $not_offered ) {
			try {
				$service->add_profile( $not_offered, $category );
				$this->fail( 'A profile that is not reviewed or pending was accepted.' );
			} catch ( \DomainException $refusal ) {
				$this->assertStringContainsString( 'existing', $refusal->getMessage() );
			}
		}
		$json = (string) wp_json_encode( $this->audit_rows() );
		$this->assertStringNotContainsString( 'press@example.org', $json );
		$this->assertStringNotContainsString( 'Strong record', $json );
	}

	public function test_REQ_034_new_staff_added_nomination_starts_nominated_and_not_live(): void {
		$service = $this->service_as( 'administrator' );
		$row     = $service->find( $service->add_profile( 102, $this->category() ) );
		$this->assertSame( 'nominated', $row['stage'] );
		$this->assertSame( 'not_live', $row['status'] );
	}

	public function test_REQ_202_stage_and_status_changes_record_the_approver_without_a_score(): void {
		$service = $this->service_as( 'wiw_editor' );
		$id      = $service->add_profile( 101, $this->category() );
		$service->set_status( $id, 'live' );
		$service->set_stage( $id, 'winner' );

		$row = $service->find( $id );
		$this->assertSame( 'winner', $row['stage'] );
		$this->assertSame( 'live', $row['status'] );
		$history = $service->history( $id );
		$this->assertCount( 2, $history );
		$this->assertSame( (string) $this->actor, (string) $history[0]['user_id'] );
		$this->assertSame( 'not_live', $history[0]['old_value'] );
		$actions = array_column( $this->audit_rows(), 'action' );
		$this->assertContains( 'nomination.status', $actions );
		$this->assertContains( 'nomination.stage', $actions );
		foreach ( $this->audit_rows() as $audit ) {
			$this->assertSame( (string) $this->actor, (string) $audit['actor_id'] );
		}
		$this->assertArrayNotHasKey( 'score', $row );

		$this->expectException( \DomainException::class );
		$service->set_stage( $id, 'finalist' );
	}

	public function test_REQ_267_nomination_cannot_be_set_live_for_a_pending_review_profile(): void {
		$service = $this->service_as( 'wiw_editor' );
		$id      = $service->add_profile( 102, $this->category() );
		try {
			$service->set_status( $id, 'live' );
			$this->fail( 'A pending-review profile went live.' );
		} catch ( \DomainException $refusal ) {
			$this->assertSame( 'not_live', $service->find( $id )['status'] );
		}
		$this->states[102] = 'published_reviewed';
		$service->set_status( $id, 'live' );
		$this->assertSame( 'live', $service->find( $id )['status'] );
	}

	public function test_REQ_202_remove_requires_confirmation_and_is_audited(): void {
		$service = $this->service_as( 'wiw_editor' );
		$id      = $service->add_profile( 101, $this->category() );
		try {
			$service->remove( $id, 'wrong' );
			$this->fail( 'Removed without confirmation.' );
		} catch ( \DomainException $refusal ) {
			$this->assertNotNull( $service->find( $id ) );
		}
		$service->remove( $id, $service->confirmation_token( $id ) );
		$this->assertNull( $service->find( $id ) );
		$this->assertContains( 'nomination.remove', array_column( $this->audit_rows(), 'action' ) );
		$this->assertSame( 'removal', $service->history( $id )[0]['change_type'] );
	}

	public function test_REQ_267_a_user_without_the_capability_cannot_change_nominations(): void {
		$service = $this->service_as( 'wiw_reviewer' );
		$this->expectException( \DomainException::class );
		$service->add_profile( 101, 1 );
	}

	public function test_REQ_202_there_is_no_ineligible_declined_shortlisted_or_finalist_stage(): void {
		$all = implode( ' ', array_merge( AwardsTables::STAGES, AwardsTables::STATUSES ) );
		$this->assertDoesNotMatchRegularExpression( '/ineligible|declined|shortlist|finalist/i', $all );
	}
}
