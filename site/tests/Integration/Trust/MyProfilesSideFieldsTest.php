<?php
/**
 * Contact details and the photograph go through pending revisions for an assigned user (REQ-261, REQ-253). The live profile does
 * not change until a Reviewer approves; the audit trail never holds a contact value. The Profile manager role exists only here.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Trust;

use WhoIsWho\Core\Directory\Contact\ContactSwitches;
use WhoIsWho\Core\Directory\Edit\EditRefused;
use WhoIsWho\Core\Directory\Edit\ProfileEditor;
use WhoIsWho\Core\Directory\Edit\SideFields;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
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
use WhoIsWho\Core\Trust\Admin\MyProfilesScreen;
use WhoIsWho\Core\Trust\AssignedProfiles;
use WhoIsWho\Tests\TestCase;

final class MyProfilesSideFieldsTest extends TestCase {

	private const FIXTURE_ROLE = 'wiw_test_profile_manager_side';

	/**
	 * Posts (profiles and attachments).
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
				AssignedProfiles::CAPABILITY => true,
			)
		);
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
				'user_login' => 'sf_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	/**
	 * A reviewed, claimed profile assigned to a user, with a contact email shown publicly and no photograph.
	 *
	 * @param int $manager User.
	 */
	private function profile( int $manager ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Side Fields Bank',
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		( new StateRepository() )->update(
			$id,
			array(
				'claimed'          => 1,
				'assigned_user_id' => $manager,
			)
		);
		wp_set_current_user( 1 );
		$contact = new ContactSwitches();
		$contact->set_value( $id, 'email', 'old@example.test' );
		$contact->set_public( $id, 'email', true, 'test setup' );
		wp_set_current_user( 0 );
		return $id;
	}

	/**
	 * A picture in the media library uploaded by a user.
	 *
	 * @param int $author Author.
	 */
	private function picture( int $author ): int {
		$id            = (int) wp_insert_attachment(
			array(
				'post_title'     => 'Portrait',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
				'post_author'    => $author,
			),
			'portrait.jpg',
			0
		);
		$this->posts[] = $id;
		return $id;
	}

	/**
	 * The audit rows written for a profile, as one string.
	 *
	 * @param int $profile_id Profile.
	 */
	private function audit_text( int $profile_id ): string {
		global $wpdb;
		return (string) wp_json_encode( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'wiw_audit_log WHERE subject_type = %s AND subject_id = %s', 'profile', (string) $profile_id ), ARRAY_A ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
	}

	public function test_REQ_261_contact_change_is_pending_then_applied_on_approval_and_the_audit_holds_no_contact_value(): void {
		$manager = $this->user( self::FIXTURE_ROLE );
		$id      = $this->profile( $manager );
		wp_set_current_user( $manager );
		( new MyProfilesScreen() )->save( $id, new Request( array( 'contact_email' => 'new@example.test' ) ), $manager );

		$this->assertSame( 'old@example.test', ( new ContactSwitches() )->all( $id )['email']['value'], 'The live contact detail does not change on save.' );
		$reviewer = $this->user( 'wiw_reviewer' );
		$service  = new RevisionService( null, static function (): void {} );
		$pending  = $service->pending( $id );
		$this->assertCount( 1, $pending );
		$this->assertStringContainsString( 'new@example.test', (string) $pending[0]['new_value'], 'The reviewer sees the proposed value in the revision.' );
		$this->assertStringNotContainsString( 'example.test', $this->audit_text( $id ), 'The audit row for the submission names the change but not the value.' );

		$service->approve( (int) $pending[0]['id'], $reviewer );
		$after = ( new ContactSwitches() )->all( $id );
		$this->assertSame( 'new@example.test', $after['email']['value'] );
		$this->assertTrue( $after['email']['public'], 'The public switch stays with staff and is not touched by an approval.' );
		$this->assertStringNotContainsString( 'example.test', $this->audit_text( $id ), 'The approval audit row holds no contact value either.' );
	}

	public function test_REQ_261_a_returned_change_leaves_the_contact_detail_and_the_photograph_as_they_were(): void {
		$manager = $this->user( self::FIXTURE_ROLE );
		$id      = $this->profile( $manager );
		$picture = $this->picture( $manager );
		$editor  = new ProfileEditor();
		wp_set_current_user( $manager );
		$editor->edit(
			$id,
			array(
				'contact_phone'   => '+234 800 000 0000',
				SideFields::IMAGE => $picture,
			),
			'Change proposed by the profile manager',
			$manager
		);
		$reviewer = $this->user( 'wiw_reviewer' );
		$service  = new RevisionService( null, static function (): void {} );
		$pending  = $service->pending( $id );
		$this->assertCount( 1, $pending );
		$service->reject( (int) $pending[0]['id'], 'Please use a clearer photograph.', $reviewer );
		$this->assertSame( '', ( new ContactSwitches() )->all( $id )['phone']['value'] );
		$this->assertSame( '', (string) get_post_meta( $id, PublicProfile::IMAGE_META, true ), 'The photograph is not live until approved.' );
	}

	public function test_REQ_253_an_approved_photograph_becomes_the_profile_image_and_the_author_cannot_approve_it(): void {
		$manager = $this->user( self::FIXTURE_ROLE );
		$id      = $this->profile( $manager );
		$picture = $this->picture( $manager );
		wp_set_current_user( $manager );
		( new ProfileEditor() )->edit( $id, array( SideFields::IMAGE => $picture ), 'Change proposed by the profile manager', $manager );
		$this->assertSame( '', (string) get_post_meta( $id, PublicProfile::IMAGE_META, true ) );

		$service = new RevisionService( null, static function (): void {} );
		$pending = $service->pending( $id );
		$this->assertCount( 1, $pending );
		try {
			$service->approve( (int) $pending[0]['id'], $manager );
			$this->fail( 'The author approved their own change.' );
		} catch ( \Throwable $refusal ) {
			$this->assertSame( '', (string) get_post_meta( $id, PublicProfile::IMAGE_META, true ) );
		}
		$service->approve( (int) $pending[0]['id'], $this->user( 'wiw_reviewer' ) );
		$this->assertSame( $picture, (int) get_post_meta( $id, PublicProfile::IMAGE_META, true ) );
	}

	public function test_REQ_261_a_photograph_that_is_not_the_users_own_upload_or_not_a_picture_is_refused(): void {
		$manager = $this->user( self::FIXTURE_ROLE );
		$id      = $this->profile( $manager );
		$theirs  = $this->picture( $this->user( self::FIXTURE_ROLE ) );
		wp_set_current_user( $manager );
		foreach ( array( $theirs, 999999 ) as $bad ) {
			try {
				( new ProfileEditor() )->edit( $id, array( SideFields::IMAGE => $bad ), 'Change proposed by the profile manager', $manager );
				$this->fail( 'A photograph the user did not upload was accepted.' );
			} catch ( EditRefused $refusal ) {
				$this->assertSame( 'That value is not acceptable for the field.', $refusal->getMessage() );
			}
		}
		$this->assertSame( array(), ( new RevisionService() )->pending( $id ) );
	}

	public function test_REQ_261_a_bad_email_is_refused_and_staff_cannot_use_the_side_keys_through_the_profile_editor(): void {
		$manager = $this->user( self::FIXTURE_ROLE );
		$id      = $this->profile( $manager );
		wp_set_current_user( $manager );
		$this->expectException( EditRefused::class );
		try {
			( new ProfileEditor() )->edit( $id, array( 'contact_email' => 'not an email' ), 'Change proposed by the profile manager', $manager );
		} finally {
			$admin = $this->user( 'administrator' );
			wp_set_current_user( $admin );
			try {
				( new ProfileEditor() )->edit( $id, array( 'contact_phone' => '123' ), 'Staff edit', $admin );
				$this->fail( 'Staff changed a contact detail through the field editor.' );
			} catch ( EditRefused $refusal ) {
				$this->assertStringContainsString( 'own panels', $refusal->getMessage() );
			}
		}
	}

	public function test_REQ_261_the_form_offers_the_contact_fields_and_a_photograph_upload_and_clearing_a_detail_is_a_change(): void {
		$manager = $this->user( self::FIXTURE_ROLE );
		$id      = $this->profile( $manager );
		wp_set_current_user( $manager );
		$html = ( new MyProfilesScreen() )->html( new Request( array( 'profile' => (string) $id ) ), $manager );
		foreach ( array( 'name="contact_email"', 'name="contact_phone"', 'name="contact_address"', 'name="image"', 'multipart/form-data', 'old@example.test' ) as $needle ) {
			$this->assertStringContainsString( $needle, $html, $needle );
		}
		( new MyProfilesScreen() )->save( $id, new Request( array( 'contact_email' => '' ) ), $manager );
		$pending = ( new RevisionService() )->pending( $id );
		$this->assertCount( 1, $pending );
		$this->assertSame( 'old@example.test', ( new ContactSwitches() )->all( $id )['email']['value'], 'Clearing waits for approval too.' );
	}
}
