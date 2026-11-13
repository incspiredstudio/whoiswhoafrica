<?php
/**
 * Assigned-profiles-only editing and its field limits (REQ-253, REQ-261, REQ-244). The Profile manager role used here exists only
 * in the tests: the plugin ships no such role and the Administrator creates it in production. Plain TestCase with the real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Trust;

use WhoIsWho\Core\Directory\Edit\EditRefused;
use WhoIsWho\Core\Directory\Edit\ProfileEditor;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Core\Trust\AssignedProfiles;
use WhoIsWho\Tests\TestCase;

final class AssignedProfilesTest extends TestCase {

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
				'user_login' => 'ap_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	/**
	 * A claimed profile; assigned to the user when one is given.
	 *
	 * @param int $manager User, or 0 for none.
	 */
	private function profile( int $manager ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Assigned test bank',
			)
		);
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => 'organisation' ) );
		update_post_meta( $id, 'wiw_summary', 'Original summary' );
		( new StateRepository() )->update(
			$id,
			array(
				'claimed'          => 1,
				'assigned_user_id' => $manager > 0 ? $manager : null,
			)
		);
		return $id;
	}

	public function test_REQ_253_assigned_user_can_edit_only_assigned_profiles(): void {
		$manager = $this->user( self::FIXTURE_ROLE );
		$mine    = $this->profile( $manager );
		$other   = $this->profile( 0 );

		$this->assertTrue( user_can( $manager, 'edit_post', $mine ) );
		$this->assertTrue( user_can( $manager, 'read_post', $mine ) );
		$this->assertFalse( user_can( $manager, 'edit_post', $other ) );
		$this->assertFalse( user_can( $manager, 'read_post', $other ) );
		$this->assertFalse( user_can( $manager, 'delete_post', $mine ), 'An assigned user never deletes a profile.' );

		$result = ( new ProfileEditor() )->edit( $mine, array( 'summary' => 'A clearer summary.' ), 'Updated by the profile manager', $manager );
		$this->assertFalse( $result['applied'], 'The change is held for review.' );
		$this->assertSame( 'Original summary', get_post_meta( $mine, 'wiw_summary', true ), 'The live profile is unchanged.' );

		$editor = $this->user( 'wiw_editor' );
		$this->assertTrue( user_can( $editor, 'edit_post', $other ), 'Staff are not limited by this rule.' );
	}

	public function test_REQ_253_assigned_user_gets_403_on_other_profile(): void {
		$manager = $this->user( self::FIXTURE_ROLE );
		$other   = $this->profile( $this->user( self::FIXTURE_ROLE ) );
		$this->assertFalse( user_can( $manager, 'edit_post', $other ), 'The edit screen answers 403.' );
		$this->expectException( EditRefused::class );
		( new ProfileEditor() )->edit( $other, array( 'summary' => 'Not mine.' ), 'Trying another profile', $manager );
	}

	public function test_REQ_261_assigned_user_cannot_change_status_slug_claimed_or_labels(): void {
		$manager = $this->user( self::FIXTURE_ROLE );
		$mine    = $this->profile( $manager );
		$editor  = new ProfileEditor();
		foreach ( array( 'status', 'slug', 'claimed', 'arrangements', 'labels', 'name', 'org_type', 'verified', 'featured' ) as $field ) {
			try {
				$editor->edit( $mine, array( $field => 'x' ), 'Trying a field I may not change', $manager );
				$this->fail( 'The field ' . $field . ' was accepted from an assigned user.' );
			} catch ( EditRefused $e ) {
				$this->assertNotSame( '', $e->getMessage() );
			}
		}
		try {
			$editor->edit( $mine, array( 'summary' => 'Fine.' ), 'Trying to set the lists', $manager, 0, array( Taxonomies::ORG_TYPE => array( 1 ) ) );
			$this->fail( 'Terms were accepted from an assigned user.' );
		} catch ( EditRefused $e ) {
			$this->assertNotSame( '', $e->getMessage() );
		}
		$state = ( new StateRepository() )->get( $mine );
		$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, $state['pub_state'] ?? '' );
		$this->assertSame( 1, (int) ( $state['claimed'] ?? 0 ) );
		foreach ( AssignedProfiles::FIELDS as $field ) {
			$this->assertTrue( AssignedProfiles::field_allowed( $field ) );
		}
		$this->assertFalse( AssignedProfiles::field_allowed( 'status' ) );
	}

	public function test_REQ_244_no_fourth_default_role_ships(): void {
		$config = require dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/config/capabilities.php';
		$this->assertSame( array( 'wiw_reviewer', 'wiw_editor' ), array_keys( $config['roles'] ), 'The plugin registers only Reviewer and Editor.' );
		$this->assertArrayHasKey( AssignedProfiles::CAPABILITY, $config['capabilities'] );
		foreach ( array( 'reviewer', 'editor' ) as $default ) {
			$this->assertNotContains( $default, $config['capabilities'][ AssignedProfiles::CAPABILITY ]['roles'], 'No default role holds the assigned capability.' );
		}
		remove_role( self::FIXTURE_ROLE );
		$wiw_roles = array_values( array_filter( array_keys( wp_roles()->roles ), static fn ( string $role ): bool => 0 === strpos( $role, 'wiw_' ) ) );
		sort( $wiw_roles );
		$this->assertSame( array( 'wiw_editor', 'wiw_reviewer' ), $wiw_roles );
	}
}
