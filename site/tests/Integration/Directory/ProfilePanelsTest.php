<?php
/**
 * The Profile screen panels (REQ-060, REQ-018, REQ-161, REQ-187, REQ-125). Plain TestCase with the real tables; cleans up after itself.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Compliance\Support\ProfileEnquirySwitch;
use WhoIsWho\Core\Directory\Admin\ProfilePanels;
use WhoIsWho\Core\Directory\Contact\ContactSwitches;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Directory\Url\ProfileRoute;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Notes\NotesService;
use WhoIsWho\Core\Review\ReviewService;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ProfilePanelsTest extends TestCase {

	/**
	 * Posts.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Users.
	 *
	 * @var int[]
	 */
	private array $users = array();

	/**
	 * Audit floor.
	 *
	 * @var int
	 */
	private int $audit_floor = 0;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! post_type_exists( ProfileType::POST_TYPE ) ) {
			( new ProfileType() )->register();
		}
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
		global $wpdb;
		$this->audit_floor = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}wiw_audit_log" ); // phpcs:ignore WordPress.DB
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( NotesService::table(), array( 'notable_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( $this->users as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->posts = array();
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'panel_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	private function profile(): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Panel test bank Zqx',
			)
		);
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => 'organisation' ) );
		( new ReviewService() )->approve( $id, $this->user( 'wiw_reviewer' ) );
		update_post_meta( $id, 'wiw_hq_city', 'Lagos' );
		return $id;
	}

	private function audit_rows( string $action, int $id ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wiw_audit_log WHERE action = %s AND subject_id = %s AND id > %d", $action, (string) $id, $this->audit_floor ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public function test_REQ_060_contact_switch_default_private_and_audited(): void {
		$id = $this->profile();
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$switches = new ContactSwitches();
		$switches->set_value( $id, 'email', 'office@example.test' );

		$profile = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $profile );
		$this->assertSame( array(), $profile->switched_on_details, 'a stored detail is private by default' );
		$this->assertSame( array(), $switches->public_details( $id ) );

		$switches->set_public( $id, 'email', true, 'Owner asked, ENQ-0001' );
		$this->assertSame( array( 'email' => 'office@example.test' ), $switches->public_details( $id ) );
		$this->assertSame( array( 'email' => 'office@example.test' ), PublicProfile::find_by_id( $id )->switched_on_details );
		$rows = $this->audit_rows( 'profile.contact_switch', $id );
		$this->assertCount( 1, $rows, 'the change is audited' );
		$this->assertSame( 'Owner asked, ENQ-0001', $rows[0]['reason'] );
		$this->assertStringNotContainsString( 'office@example.test', wp_json_encode( $rows ), 'the audit row holds no personal data' );
	}

	public function test_REQ_060_switching_a_detail_on_needs_a_reason_and_the_capability(): void {
		$id = $this->profile();
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$switches = new ContactSwitches();
		$switches->set_value( $id, 'phone', '+234 800 000 0000' );
		try {
			$switches->set_public( $id, 'phone', true, '  ' );
			$this->fail( 'expected a refusal' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( array(), $switches->public_details( $id ) );
		}
		wp_set_current_user( $this->user( 'wiw_reviewer' ) );
		$this->expectException( \RuntimeException::class );
		$switches->set_public( $id, 'phone', true, 'Because' );
	}

	public function test_REQ_018_enquiry_switch_off_hides_form(): void {
		$id = $this->profile();
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$this->assertTrue( PublicProfile::find_by_id( $id )->enquiries_open );
		( new ProfileEnquirySwitch() )->set_open( $id, false, 'Owner asked' );
		$this->assertFalse( PublicProfile::find_by_id( $id )->enquiries_open );
		$this->assertStringContainsString( 'name="open"', ( new ProfilePanels() )->html( $id, (array) ( new StateRepository() )->get( $id ) ) );
	}

	public function test_REQ_006_changing_the_slug_leaves_a_redirect_and_is_audited(): void {
		$id  = $this->profile();
		$old = (string) get_post_field( 'post_name', $id );
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$new = ProfilePanels::change_slug( $id, 'renamed-panel-bank-zqx', 'Rebrand ENQ-0002' );
		$this->assertSame( 'renamed-panel-bank-zqx', $new );
		$result = ( new ProfileRoute() )->resolve( $old );
		$this->assertSame( 'redirect', $result['action'] );
		$this->assertCount( 1, $this->audit_rows( 'profile.slug_change', $id ) );
		$this->expectException( \RuntimeException::class );
		ProfilePanels::change_slug( $id, 'another-slug-zqx', '' );
	}

	public function test_REQ_161_the_fields_panel_shows_sources_and_has_no_edit_control(): void {
		$id = $this->profile();
		( new ProvenanceRepository() )->record(
			$id,
			array(
				'field'        => 'hq_city',
				'source_url'   => 'https://source.example.test/page',
				'retrieved_at' => '2026-10-01',
				'confidence'   => 'high',
				'method'       => 'import',
			)
		);
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$html  = ( new ProfilePanels() )->html( $id, (array) ( new StateRepository() )->get( $id ) );
		$start = strpos( $html, 'Fields and their sources' );
		$end   = strpos( $html, 'Source sync' );
		$this->assertIsInt( $start );
		$section = substr( $html, $start, (int) $end - $start );
		$this->assertStringContainsString( 'https://source.example.test/page', $section );
		$this->assertStringContainsString( '2026-10-01', $section );
		$this->assertStringNotContainsString( '<input', $section );
		$this->assertStringNotContainsString( '<textarea', $section );
	}

	public function test_REQ_161_changed_source_is_flagged_not_applied(): void {
		$id = $this->profile();
		( new VersionRepository() )->append( $id, 0, 'import', '', array( 'hq_city' => 'Lagos' ), array( 'hq_city' => 'Kano' ), 'pending' );
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$this->assertSame( 'Lagos', get_post_meta( $id, 'wiw_hq_city', true ), 'the reviewed value is not overwritten' );
		$flagged = array_filter( ( new ProfilePanels() )->flagged_changes( $id ), static fn ( array $r ): bool => '' === (string) $r['reason'] );
		$this->assertCount( 1, $flagged );
		$this->assertStringContainsString( 'A source changed', ( new ProfilePanels() )->html( $id, (array) ( new StateRepository() )->get( $id ) ) );
	}

	public function test_REQ_187_internal_notes_never_public(): void {
		$id = $this->profile();
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		NotesService::for_site()->add( 'profile', $id, 'Secret owner phone call note', ProfilePanels::NOTES_CAP );
		$html = ( new ProfilePanels() )->html( $id, (array) ( new StateRepository() )->get( $id ) );
		$this->assertStringContainsString( 'Secret owner phone call note', $html, 'staff see it' );
		wp_set_current_user( 0 );
		$profile = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $profile );
		$this->assertStringNotContainsString( 'Secret owner', (string) wp_json_encode( $profile ), 'no public read model carries a note' );
	}

	public function test_REQ_125_history_lists_author_time_and_reason(): void {
		$id     = $this->profile();
		$editor = $this->user( 'wiw_editor' );
		( new VersionRepository() )->append( $id, $editor, 'edit', 'Fixed the city', array( 'hq_city' => 'Lagos' ), array( 'hq_city' => 'Abuja' ) );
		wp_set_current_user( $editor );
		$html = ( new ProfilePanels() )->html( $id, (array) ( new StateRepository() )->get( $id ) );
		$this->assertStringContainsString( 'Fixed the city', $html );
		$this->assertStringContainsString( (string) get_userdata( $editor )->display_name, $html );
		$this->assertStringContainsString( gmdate( 'Y-m-d' ), $html );
	}
}
