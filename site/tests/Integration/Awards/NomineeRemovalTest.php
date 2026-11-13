<?php
/**
 * An upheld removal request hides the nominee's nominations and suppresses the name (REQ-198, REQ-147, REQ-207). Needs the
 * WordPress test suite and the real profile, awards and suppression tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\NominationDuplicates;
use WhoIsWho\Core\Awards\NominationService;
use WhoIsWho\Core\Awards\NomineeRemoval;
use WhoIsWho\Core\Awards\PublicNominee;
use WhoIsWho\Core\Awards\PublicWinner;
use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\WpTestCase;

final class NomineeRemovalTest extends WpTestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		foreach ( array( AwardsTables::NOMINATIONS, AwardsTables::CATEGORIES, AwardsTables::CHANGES ) as $name ) {
			$wpdb->query( 'DELETE FROM ' . AwardsTables::table( $name ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		}
		$wpdb->query( 'DELETE FROM ' . Suppression::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function profile( string $name ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'individual' ) );
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		return $id;
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

	private function nomination( int $profile_id, int $category_id, string $stage, string $status = 'live', string $name = '' ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::NOMINATIONS ),
			array(
				'category_id'  => $category_id,
				'profile_id'   => $profile_id > 0 ? $profile_id : null,
				'nominee_name' => '' === $name ? null : $name,
				'stage'        => $stage,
				'status'       => $status,
				'source'       => 'staff',
				'created_at'   => '2026-11-12 10:00:00',
				'updated_at'   => '2026-11-12 10:00:00',
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function removal(): NomineeRemoval {
		$audit = AuditLogger::for_site();
		return new NomineeRemoval( new NominationService( $audit ), new Suppression( $audit ) );
	}

	public function test_REQ_198_upheld_removal_of_a_nominee_adds_to_suppression_list(): void {
		$cat     = $this->category();
		$profile = $this->profile( 'Removed Person Zq' );
		$other   = $this->profile( 'Bystander Zq' );
		$live    = $this->nomination( $profile, $cat, 'nominated' );
		$winner  = $this->nomination( $profile, $cat, 'winner' );
		$typed   = $this->nomination( 0, $cat, 'nominated', 'live', 'Removed Person Zq' );
		$safe    = $this->nomination( $other, $cat, 'nominated' );
		$this->assertNotNull( PublicNominee::find( PublicNominee::ref_for( $live ) ) );
		$this->assertCount( 1, PublicWinner::all() );

		$hidden = $this->removal()->handle( 4242, $profile, 'Removed Person Zq', 'individual' );
		$this->assertSame( 3, $hidden, 'both profile nominations and the typed-name one are hidden' );

		$service = new NominationService( AuditLogger::for_site() );
		foreach ( array( $live, $winner, $typed ) as $id ) {
			$this->assertSame( 'not_live', $service->find( $id )['status'] );
		}
		$this->assertSame( 'live', $service->find( $safe )['status'], 'another nominee is untouched' );
		$this->assertNotNull( ( new Suppression( AuditLogger::for_site() ) )->match( 'removed  PERSON zq', 'individual' ) );
		$this->assertNotNull( ( new NominationDuplicates() )->check( 'Removed Person Zq', '', $cat ), 'a new nomination of the name shows the duplicate warning' );
		$this->assertSame( NominationDuplicates::SUPPRESSED, ( new NominationDuplicates() )->check( 'Removed Person Zq', '', $cat + 1 ), 'in another category the suppression list is what warns' );

		$again = $this->removal()->handle( 4242, $profile, 'Removed Person Zq', 'individual' );
		$this->assertSame( 0, $again, 'running it twice changes nothing more' );
		$this->assertCount( 1, ( new Suppression( AuditLogger::for_site() ) )->snapshot(), 'one suppression entry, not two' );

		global $wpdb;
		$history = $wpdb->get_col( $wpdb->prepare( 'SELECT new_value FROM ' . AwardsTables::table( AwardsTables::CHANGES ) . ' WHERE nomination_id = %d', $live ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		$this->assertContains( 'not_live', $history );
		$audit = (string) wp_json_encode( $wpdb->get_results( 'SELECT action, new_value FROM ' . $wpdb->prefix . 'wiw_audit_log WHERE action = \'nomination.removal_hide\'', ARRAY_A ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		$this->assertStringContainsString( 'nomination.removal_hide', $audit );
		$this->assertStringContainsString( '4242', $audit, 'the audit entry names the support item' );
	}

	public function test_REQ_198_the_compliance_action_drives_the_listener(): void {
		$cat     = $this->category();
		$profile = $this->profile( 'Action Person Zq' );
		$id      = $this->nomination( $profile, $cat, 'nominated' );
		NomineeRemoval::hooks();
		do_action( NomineeRemoval::ACTION, 77, $profile, 'Action Person Zq', 'individual', array() );
		remove_all_actions( NomineeRemoval::ACTION );
		$this->assertSame( 'not_live', ( new NominationService( AuditLogger::for_site() ) )->find( $id )['status'] );
	}

	public function test_REQ_207_suppressed_nominee_never_reappears_on_nominee_pages(): void {
		$cat     = $this->category();
		$profile = $this->profile( 'Gone Person Zq' );
		$id      = $this->nomination( $profile, $cat, 'nominated' );
		$this->removal()->handle( 9, $profile, 'Gone Person Zq', 'individual' );
		$ref = PublicNominee::ref_for( $id );
		$this->assertNull( PublicNominee::find( $ref ) );
		$this->assertSame( array(), PublicNominee::live() );
		$this->assertSame( array(), PublicWinner::all() );

		// Staff cannot put it back live while the name is suppressed.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_editor' ) ) );
		$service = new NominationService( AuditLogger::for_site() );
		try {
			$service->set_status( $id, 'live' );
			$this->fail( 'A suppressed nominee was set Live.' );
		} catch ( \DomainException $refusal ) {
			$this->assertStringContainsString( 'suppression list', $refusal->getMessage() );
		}
		$this->assertNull( PublicNominee::find( $ref ) );
	}
}
