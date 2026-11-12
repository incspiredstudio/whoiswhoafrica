<?php
/**
 * Direct profile edit (REQ-125, REQ-188, REQ-232, REQ-143, REQ-244). Needs the WordPress test suite and the real tables, so it is
 * a plain TestCase that cleans up after itself.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Directory\Admin\ProfileEditScreen;
use WhoIsWho\Core\Directory\Edit\EditRefused;
use WhoIsWho\Core\Directory\Edit\ProfileEditor;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\ReviewService;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ProfileEditTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Users to remove.
	 *
	 * @var int[]
	 */
	private array $users = array();

	/**
	 * Highest version row id and audit row id before the test acted (ids of profiles are reused and both tables are append-only).
	 *
	 * @var int
	 */
	private int $version_floor = 0;

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
		if ( ! taxonomy_exists( Taxonomies::ORG_TYPE ) ) {
			( new Taxonomies() )->register();
		}
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
		$wpdb->query( 'DELETE FROM ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
		foreach ( $this->users as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->posts = array();
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function profile( string $state = PubState::PUBLISHED_REVIEWED, array $state_values = array() ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Edit test bank',
			)
		);
		$this->posts[] = $id;
		global $wpdb;
		$this->version_floor = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(id),0) FROM ' . VersionRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
		$this->audit_floor   = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}wiw_audit_log" ); // phpcs:ignore WordPress.DB
		( new StateMachine() )->enter( $id, PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => 'organisation' ) );
		if ( PubState::IMPORTED_NOT_PUBLIC !== $state ) {
			( new ReviewService() )->approve( $id, $this->user( 'wiw_reviewer' ) );
		}
		update_post_meta( $id, 'wiw_org_type', 'Commercial bank' );
		update_post_meta( $id, 'wiw_hq_city', 'Lagos' );
		if ( array() !== $state_values ) {
			( new StateRepository() )->update( $id, $state_values );
		}
		return $id;
	}

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'edit_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	/**
	 * Version rows of a profile written since the profile was made (ids are reused between tests and the table is append-only).
	 *
	 * @param int $id Profile.
	 * @return array<int, array<string, mixed>>
	 */
	private function versions( int $id ): array {
		return array_values( array_filter( ( new VersionRepository() )->for_profile( $id ), fn ( $row ): bool => (int) $row['id'] > $this->version_floor ) );
	}

	private function audit_rows( string $action, int $id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wiw_audit_log WHERE action = %s AND subject_id = %s AND id > %d", $action, (string) $id, $this->audit_floor ) ); // phpcs:ignore WordPress.DB
	}

	public function test_REQ_125_edit_writes_version_with_reason(): void {
		$id     = $this->profile();
		$editor = $this->user( 'wiw_editor' );
		$result = ( new ProfileEditor() )->edit( $id, array( 'hq_city' => 'Abuja' ), 'Corrected after the press release', $editor );

		$this->assertTrue( $result['applied'] );
		$this->assertSame( 'Abuja', get_post_meta( $id, 'wiw_hq_city', true ), 'an unclaimed profile changes at once' );
		$versions = $this->versions( $id );
		$this->assertCount( 1, $versions );
		$this->assertSame( (string) $editor, (string) $versions[0]['author_id'] );
		$this->assertSame( 'edit', $versions[0]['created_via'] );
		$this->assertSame( 'Corrected after the press release', $versions[0]['reason'] );
		$this->assertNotEmpty( $versions[0]['created_at'] );
		$this->assertSame( array( 'hq_city' => 'Lagos' ), json_decode( (string) $versions[0]['old_value'], true ) );
		$this->assertSame( array( 'hq_city' => 'Abuja' ), json_decode( (string) $versions[0]['new_value'], true ) );
	}

	public function test_REQ_125_edit_without_reason_is_refused(): void {
		$id     = $this->profile();
		$editor = $this->user( 'wiw_editor' );
		try {
			( new ProfileEditor() )->edit( $id, array( 'hq_city' => 'Abuja' ), '   ', $editor );
			$this->fail( 'expected a refusal' );
		} catch ( EditRefused $e ) {
			$this->assertStringContainsString( 'reason', $e->getMessage() );
		}
		$this->assertSame( 'Lagos', get_post_meta( $id, 'wiw_hq_city', true ) );
		$this->assertSame( array(), $this->versions( $id ) );
	}

	public function test_REQ_188_profile_edit_writes_audit_row(): void {
		$id = $this->profile();
		( new ProfileEditor() )->edit( $id, array( 'hq_city' => 'Kano' ), 'Fix', $this->user( 'wiw_editor' ) );
		$this->assertSame( 1, $this->audit_rows( 'profile.edit', $id ) );
	}

	public function test_REQ_232_edit_closes_linked_correction(): void {
		$id   = $this->profile();
		$item = ( new SupportItems() )->create(
			array(
				'type'       => ItemType::CORRECTION,
				'profile_id' => $id,
				'name'       => 'A',
				'email'      => 'a@example.org',
				'message'    => 'Wrong city',
			)
		);
		( new ProfileEditor() )->edit( $id, array( 'hq_city' => 'Kano' ), 'Per ' . $item['reference'], $this->user( 'wiw_editor' ), (int) $item['id'] );
		$this->assertSame( ItemStatus::RESOLVED, ( new SupportItems() )->get( (int) $item['id'] )['status'] );
	}

	public function test_REQ_232_a_correction_on_another_profile_is_refused(): void {
		$id    = $this->profile();
		$other = $this->profile();
		$item  = ( new SupportItems() )->create(
			array(
				'type'       => ItemType::CORRECTION,
				'profile_id' => $other,
				'name'       => 'A',
				'email'      => 'a@example.org',
				'message'    => 'x',
			)
		);
		$this->expectException( EditRefused::class );
		( new ProfileEditor() )->edit( $id, array( 'hq_city' => 'Kano' ), 'Fix', $this->user( 'wiw_editor' ), (int) $item['id'] );
	}

	public function test_REQ_143_a_field_outside_the_allowlist_is_refused(): void {
		$id = $this->profile();
		foreach ( array( 'email', 'phone', 'not_a_field' ) as $key ) {
			try {
				( new ProfileEditor() )->edit( $id, array( $key => 'x' ), 'Fix', $this->user( 'wiw_editor' ) );
				$this->fail( "expected a refusal for {$key}" );
			} catch ( EditRefused $e ) {
				$this->assertSame( '', (string) get_post_meta( $id, 'wiw_' . $key, true ) );
			}
		}
		$this->assertSame( array(), $this->versions( $id ) );
	}

	public function test_REQ_125_a_required_field_cannot_be_blanked(): void {
		$id = $this->profile();
		$this->expectException( EditRefused::class );
		( new ProfileEditor() )->edit( $id, array( 'name' => '' ), 'Fix', $this->user( 'wiw_editor' ) );
	}

	public function test_REQ_125_the_name_is_edited_in_the_title(): void {
		$id = $this->profile();
		( new ProfileEditor() )->edit( $id, array( 'name' => 'Renamed bank' ), 'Rebrand', $this->user( 'wiw_editor' ) );
		$this->assertSame( 'Renamed bank', get_the_title( $id ) );
	}

	public function test_REQ_244_reviewer_cannot_edit_profile_fields(): void {
		$id       = $this->profile();
		$reviewer = $this->user( 'wiw_reviewer' );
		$this->assertFalse( user_can( $reviewer, 'edit_wiw_profiles' ) ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
		try {
			( new ProfileEditor() )->edit( $id, array( 'hq_city' => 'Kano' ), 'Fix', $reviewer );
			$this->fail( 'expected a refusal' );
		} catch ( EditRefused $e ) {
			$this->assertSame( 'Lagos', get_post_meta( $id, 'wiw_hq_city', true ) );
		}
		// The action itself is refused too, before any handler runs.
		wp_set_current_user( $reviewer );
		$request = new Request( array( '_wpnonce' => ActionGuard::nonce_for( ProfileEditScreen::SAVE ) ) );
		$this->assertSame( ActionGuard::REFUSED_CAPABILITY, ActionGuard::check( ProfileEditScreen::SAVE, ProfileEditScreen::CAPABILITY, $request ) );
	}

	public function test_REQ_125_an_edit_by_the_assigned_user_of_a_claimed_profile_waits_for_review(): void {
		$manager = $this->user( 'wiw_editor' );
		$id      = $this->profile(
			PubState::PUBLISHED_REVIEWED,
			array(
				'claimed'          => 1,
				'assigned_user_id' => $manager,
			)
		);
		$result  = ( new ProfileEditor() )->edit( $id, array( 'hq_city' => 'Abuja' ), 'Manager edit', $manager );
		$this->assertFalse( $result['applied'] );
		$this->assertSame( 'Lagos', get_post_meta( $id, 'wiw_hq_city', true ), 'the public value does not change' );
		$pending = array_values( array_filter( ( new VersionRepository() )->pending_for_profile( $id ), fn ( $row ): bool => (int) $row['id'] > $this->version_floor ) );
		$this->assertCount( 1, $pending );
		$this->assertSame( 'revision', $pending[0]['created_via'] );
	}

	public function test_REQ_125_terms_are_assigned_and_recorded(): void {
		$id   = $this->profile();
		$term = wp_insert_term( 'Commercial Bank Edit', Taxonomies::ORG_TYPE );
		$tid  = (int) $term['term_id'];
		( new ProfileEditor() )->edit( $id, array(), 'Classify', $this->user( 'wiw_editor' ), 0, array( Taxonomies::ORG_TYPE => array( $tid ) ) );
		$this->assertSame( array( $tid ), array_map( 'intval', wp_get_object_terms( $id, Taxonomies::ORG_TYPE, array( 'fields' => 'ids' ) ) ) );
		$this->assertCount( 1, $this->versions( $id ) );
		wp_delete_term( $tid, Taxonomies::ORG_TYPE );
	}

	public function test_REQ_125_the_screen_reads_only_allowlisted_fields(): void {
		$request = new Request(
			array(
				'f_hq_city' => 'Abuja',
				'f_email'   => 'x@example.org',
				'f_mandate' => '',
			)
		);
		$changes = ProfileEditScreen::changes_from( $request, 'organisation' );
		$this->assertSame( 'Abuja', $changes['hq_city'] );
		$this->assertArrayNotHasKey( 'email', $changes );
	}
}
