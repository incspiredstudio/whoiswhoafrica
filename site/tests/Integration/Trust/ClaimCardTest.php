<?php
/**
 * The Claim and profile manager card on the profile screen: claimed mark in the staff list and card, assign and unassign with an
 * audit row, an assignee picker that lists staff only, and no public trace (REQ-252, REQ-253, REQ-150). Needs the WordPress test
 * suite. Roles are reset in setUp because other tests commit the transaction.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Trust;

use WhoIsWho\Core\Directory\Admin\ProfilesScreen;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Core\Trust\ClaimCard;
use WhoIsWho\Core\Trust\ClaimDecision;
use WhoIsWho\Core\Trust\ProfileManager;
use WhoIsWho\Tests\WpTestCase;

final class ClaimCardTest extends WpTestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
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

	private function profile( string $name, bool $claimed ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		if ( $claimed ) {
			( new StateRepository() )->update(
				$id,
				array(
					'claimed'    => 1,
					'claimed_at' => '2026-10-01 09:00:00',
				)
			);
		}
		return $id;
	}

	private function card( int $profile_id ): string {
		ob_start();
		ClaimCard::render( get_post( $profile_id ) );
		return (string) ob_get_clean();
	}

	private function audit_row( int $profile_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wiw_audit_log WHERE action = %s AND subject_id = %s ORDER BY id DESC LIMIT 1", 'profile.assign_manager', (string) $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? $row : null;
	}

	public function test_REQ_252_claimed_profile_shows_checkmark_in_staff_list(): void {
		$this->profile( 'Tick Org Kqz', true );
		$this->profile( 'Plain Org Kqz', false );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) ) );
		$_GET = array(
			'page' => ProfilesScreen::SCREEN,
			'name' => 'Kqz',
		);
		ob_start();
		( new ProfilesScreen() )->render();
		$html = (string) ob_get_clean();
		$_GET = array();
		$this->assertSame( 1, substr_count( $html, '&#10003;' ), 'only the claimed profile has the mark' );
		$this->assertStringContainsString( 'Claimed', $card = $this->card( $this->posts[0] ) );
		$this->assertStringContainsString( '&#10003;', $card, 'the card carries the same mark' );
		$this->assertStringContainsString( '2026-10-01', $card, 'claim date shown' );
		$this->assertStringNotContainsString( 'Not claimed', $card );
		$this->assertStringContainsString( 'Not claimed', $this->card( $this->posts[1] ) );
	}

	public function test_REQ_253_assign_sets_manager_user_and_audits(): void {
		$id       = $this->profile( 'Assign Org Kqz', true );
		$reviewer = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		$staff    = self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) );
		wp_set_current_user( $reviewer );
		$service = new ProfileManager();
		$service->assign( $id, $staff, $reviewer );
		$this->assertSame( $staff, $service->assigned( $id ) );
		$row = $this->audit_row( $id );
		$this->assertNotNull( $row );
		$this->assertSame( (string) $reviewer, (string) $row['actor_id'] );
		$this->assertStringContainsString( (string) $staff, (string) $row['new_value'] );

		$other = self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) );
		$service->assign( $id, $other, $reviewer );
		$this->assertSame( $other, $service->assigned( $id ), 'one user per profile; assigning replaces' );
		$this->assertStringContainsString( (string) $staff, (string) $this->audit_row( $id )['old_value'] );

		$service->assign( $id, 0, $reviewer );
		$this->assertNull( $service->assigned( $id ), 'unassign' );
	}

	public function test_REQ_253_assign_refuses_unclaimed_profile_and_non_staff(): void {
		$reviewer = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		$staff    = self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) );
		$public   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $reviewer );
		$service = new ProfileManager();
		try {
			$service->assign( $this->profile( 'Open Org Kqz', false ), $staff, $reviewer );
			$this->fail( 'An unclaimed profile has no manager.' );
		} catch ( \DomainException $e ) {
			$this->assertStringContainsString( 'claimed', $e->getMessage() );
		}
		$claimed = $this->profile( 'Closed Org Kqz', true );
		try {
			$service->assign( $claimed, $public, $reviewer );
			$this->fail( 'A public account cannot be assigned.' );
		} catch ( \DomainException $e ) {
			$this->assertStringContainsString( 'staff', $e->getMessage() );
		}
		$this->assertNull( $service->assigned( $claimed ) );
		$this->assertArrayHasKey( $staff, $service->staff_choices() );
		$this->assertArrayNotHasKey( $public, $service->staff_choices() );
	}

	public function test_REQ_253_assign_control_only_for_claim_reviewers_on_claimed_profiles(): void {
		$claimed   = $this->profile( 'Ctl Org Kqz', true );
		$unclaimed = $this->profile( 'Ctl Open Kqz', false );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_editor' ) ) );
		$this->assertStringContainsString( 'name="manager_user_id"', $this->card( $claimed ) );
		$this->assertStringNotContainsString( 'name="manager_user_id"', $this->card( $unclaimed ) );

		$viewer = self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) );
		wp_set_current_user( $viewer );
		$this->assertFalse( user_can( $viewer, ClaimDecision::CAPABILITY ) );
		$this->assertStringNotContainsString( 'name="manager_user_id"', $this->card( $claimed ) );
		$this->assertStringContainsString( 'Claimed', $this->card( $claimed ), 'status still shows' );
		wp_set_current_user( 0 );
		$this->assertSame( '', $this->card( $claimed ), 'no output for a visitor' );
	}

	public function test_REQ_150_claimed_badge_not_in_public_output(): void {
		$id    = $this->profile( 'Pub Org Kqz', true );
		$staff = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		( new ProfileManager() )->assign( $id, $staff, $staff );
		$profile = PublicProfile::find( (string) get_post_field( 'post_name', $id ) );
		$this->assertNotNull( $profile );
		$this->assertArrayNotHasKey( 'assigned_user_id', get_object_vars( $profile ) );
		$this->assertArrayNotHasKey( 'manager_user_id', get_object_vars( $profile ) );
		wp_set_current_user( 0 );
		$this->assertSame( '', $this->card( $id ), 'the card renders nothing without staff capability' );
		$this->assertStringNotContainsString( 'wiw-claim-card', (string) apply_filters( 'the_content', get_post_field( 'post_content', $id ) ) );
	}
}
