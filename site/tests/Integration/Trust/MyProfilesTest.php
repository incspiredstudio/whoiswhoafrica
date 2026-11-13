<?php
/**
 * The My profiles list and the save that files a pending revision (REQ-261, REQ-253). The Profile manager role exists only in
 * the tests. Plain TestCase with the real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Trust;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\Revisions\RevisionService;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Core\Directory\Edit\EditRefused;
use WhoIsWho\Core\Trust\Admin\MyProfilesScreen;
use WhoIsWho\Core\Trust\AssignedProfiles;
use WhoIsWho\Core\Trust\MyProfiles;
use WhoIsWho\Tests\TestCase;

final class MyProfilesTest extends TestCase {

	private const FIXTURE_ROLE = 'wiw_test_profile_manager';

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
		remove_role( self::FIXTURE_ROLE );
		add_role(
			self::FIXTURE_ROLE,
			'Profile manager (test)',
			array(
				'read'                       => true,
				'upload_files'               => true,
				AssignedProfiles::CAPABILITY => true,
			)
		);
		// The versions table is append-only and test profiles are deleted, so a reused post id would inherit old pending rows:
		// start the ids above every profile that ever had a version.
		global $wpdb;
		$top = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(profile_id),0) FROM ' . VersionRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
		$wpdb->query( 'ALTER TABLE ' . $wpdb->posts . ' AUTO_INCREMENT = ' . ( $top + 1000 ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
		remove_role( self::FIXTURE_ROLE );
		$this->posts = array();
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * A user with a role.
	 *
	 * @param string $role Role.
	 */
	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'mp_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	/**
	 * A claimed profile assigned to a user.
	 *
	 * @param int    $manager User.
	 * @param string $title   Title.
	 * @param bool   $is_public  Whether it is published and reviewed.
	 */
	private function profile( int $manager, string $title, bool $is_public = true ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $title,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		if ( $is_public ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		} else {
			$machine->transition( $id, PubState::UNPUBLISHED, 'hide for the test', 1 );
		}
		update_post_meta( $id, 'wiw_summary', 'Original summary' );
		( new StateRepository() )->update(
			$id,
			array(
				'claimed'          => 1,
				'assigned_user_id' => $manager,
			)
		);
		return $id;
	}

	public function test_REQ_261_my_profiles_lists_only_assigned_profiles(): void {
		$manager = $this->user( self::FIXTURE_ROLE );
		$other   = $this->user( self::FIXTURE_ROLE );
		$mine    = $this->profile( $manager, 'Mine Bank' );
		$this->profile( $manager, 'Hidden Bank', false );
		$this->profile( $other, 'Not Mine Bank' );

		$list = ( new MyProfiles() )->for_user( $manager );
		$this->assertSame( array( $mine ), array_column( $list, 'id' ), 'Only an assigned profile that is published or pending appears.' );
		$this->assertSame( MyProfiles::PUBLISHED, $list[0]['status'] );

		wp_set_current_user( $manager );
		$html = ( new MyProfilesScreen() )->html( new Request( array() ), $manager );
		$this->assertStringContainsString( 'Mine Bank', $html );
		$this->assertStringNotContainsString( 'Not Mine Bank', $html );
		$this->assertStringNotContainsString( 'Hidden Bank', $html );

		$form = ( new MyProfilesScreen() )->html( new Request( array( 'profile' => (string) ( $this->profile( $other, 'Third Bank' ) ) ) ), $manager );
		$this->assertStringNotContainsString( 'Third Bank', $form, 'Opening another profile shows the list, never that profile.' );
	}

	public function test_REQ_261_save_submits_for_review_and_live_profile_unchanged(): void {
		$manager = $this->user( self::FIXTURE_ROLE );
		$id      = $this->profile( $manager, 'Review Bank' );
		wp_set_current_user( $manager );
		$screen = new MyProfilesScreen();

		$screen->save(
			$id,
			new Request(
				array(
					'summary' => 'A better summary.',
					'status'  => 'hidden',
				)
			),
			$manager
		);
		$this->assertSame( 'Original summary', get_post_meta( $id, 'wiw_summary', true ), 'The live profile does not change on save.' );
		$list = ( new MyProfiles() )->for_user( $manager );
		$this->assertSame( MyProfiles::PENDING, $list[0]['status'] );
		$state = ( new StateRepository() )->get( $id );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, $state['pub_state'], 'Status is not something the form can change.' );

		// A reviewer returns it with a note: the author sees the note, and the live profile still has the old text.
		$reviewer = $this->user( 'wiw_reviewer' );
		$service  = new RevisionService( null, static function (): void {} );
		$pending  = $service->pending( $id );
		$this->assertCount( 1, $pending );
		$service->reject( (int) $pending[0]['id'], 'Please cite a source.', $reviewer );
		$list = ( new MyProfiles() )->for_user( $manager );
		$this->assertSame( MyProfiles::PUBLISHED, $list[0]['status'] );
		$this->assertSame( 'Please cite a source.', $list[0]['note'], 'A returned edit shows its note in My profiles.' );
		$this->assertStringContainsString( 'Please cite a source.', $screen->html( new Request( array() ), $manager ) );
		$this->assertSame( 'Original summary', get_post_meta( $id, 'wiw_summary', true ) );
	}

	public function test_REQ_261_a_profile_not_assigned_to_the_user_cannot_be_saved(): void {
		$manager = $this->user( self::FIXTURE_ROLE );
		$other   = $this->profile( $this->user( self::FIXTURE_ROLE ), 'Other Bank' );
		wp_set_current_user( $manager );
		$this->expectException( EditRefused::class );
		( new MyProfilesScreen() )->save( $other, new Request( array( 'summary' => 'Nope.' ) ), $manager );
	}
}
