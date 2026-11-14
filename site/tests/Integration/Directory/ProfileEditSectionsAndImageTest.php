<?php
/**
 * The Edit profile screen's narrative sections and the photo or logo of an existing profile (REQ-125, REQ-188, REQ-232, REQ-244,
 * REQ-162, REQ-203; issue 806). Needs the WordPress test suite and the real tables, so it is a plain TestCase that cleans up after
 * itself.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Admin\ProfileEditScreen;
use WhoIsWho\Core\Directory\Edit\EditRefused;
use WhoIsWho\Core\Directory\Edit\ProfileEditor;
use WhoIsWho\Core\Directory\Edit\ProfileImageEditor;
use WhoIsWho\Core\Directory\ImageMeta;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
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
use WhoIsWho\Core\Review\Revisions\RevisionService;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ProfileEditSectionsAndImageTest extends TestCase {

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
	 * Highest version row id before the test acted.
	 *
	 * @var int
	 */
	private int $version_floor = 0;

	/**
	 * Highest audit row id before the test acted.
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
		foreach ( $this->users as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->posts = array();
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * A profile in a state, of a type.
	 *
	 * @param string               $type         Entity type.
	 * @param string               $state        Publication state.
	 * @param array<string, mixed> $state_values Extra state columns.
	 */
	private function profile( string $type = 'individual', string $state = PubState::PUBLISHED_REVIEWED, array $state_values = array() ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Fictional Edit Person',
			)
		);
		$this->posts[] = $id;
		global $wpdb;
		$this->version_floor = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(id),0) FROM ' . VersionRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
		$this->audit_floor   = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}wiw_audit_log" ); // phpcs:ignore WordPress.DB
		( new StateMachine() )->enter( $id, PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => $type ) );
		if ( PubState::IMPORTED_NOT_PUBLIC !== $state ) {
			( new ReviewService() )->approve( $id, $this->user( 'wiw_reviewer' ) );
		}
		if ( array() !== $state_values ) {
			( new StateRepository() )->update( $id, $state_values );
		}
		return $id;
	}

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'eds_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	/**
	 * A media library item of a type.
	 *
	 * @param string $mime Mime type.
	 */
	private function attachment( string $mime = 'image/jpeg' ): int {
		$id            = (int) wp_insert_attachment(
			array(
				'post_title'     => 'Fictional portrait',
				'post_mime_type' => $mime,
				'post_status'    => 'inherit',
			),
			'fictional-portrait.' . ( 'image/png' === $mime ? 'png' : ( 'image/gif' === $mime ? 'gif' : 'jpg' ) )
		);
		$this->posts[] = $id;
		return $id;
	}

	/**
	 * A valid credit.
	 *
	 * @return array<string, string>
	 */
	private function credit(): array {
		return array(
			'licence'    => 'CC-BY-SA-4.0',
			'creator'    => 'Fictional Photographer',
			'source_url' => 'https://commons.example.org/wiki/File:Fictional.jpg',
		);
	}

	private function audit_rows( string $action, int $id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wiw_audit_log WHERE action = %s AND subject_id = %s AND id > %d", $action, (string) $id, $this->audit_floor ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Version rows of a profile written since the profile was made.
	 *
	 * @param int $id Profile.
	 * @return array<int, array<string, mixed>>
	 */
	private function versions( int $id ): array {
		return array_values( array_filter( ( new VersionRepository() )->for_profile( $id ), fn ( $row ): bool => (int) $row['id'] > $this->version_floor ) );
	}

	private function screen_html( int $id, string $role = 'wiw_editor' ): string {
		wp_set_current_user( $this->user( $role ) );
		return ( new ProfileEditScreen() )->html( new Request( array( 'profile_id' => (string) $id ) ) );
	}

	public function test_REQ_125_sections_summary_and_mandate_are_textareas_with_a_character_counter(): void {
		$person = $this->screen_html( $this->profile( 'individual' ) );
		foreach ( array( 'bio', 'achievement', 'influence', 'legacy' ) as $key ) {
			$this->assertMatchesRegularExpression( '#<textarea[^>]*name="f_' . $key . '"[^>]*maxlength="6000"[^>]*data-wiw-count="wiw-count-f_' . $key . '"#', $person, "{$key} is a multi-line box limited to 6000" );
			$this->assertStringNotContainsString( '<input type="text" name="f_' . $key . '"', $person );
			$this->assertMatchesRegularExpression( '#id="wiw-count-f_' . $key . '"[^>]*>0 / 6000 characters<#', $person );
		}
		$org = $this->screen_html( $this->profile( 'organisation' ) );
		$this->assertMatchesRegularExpression( '#<textarea[^>]*name="f_summary"[^>]*maxlength="2000"#', $org );
		$this->assertMatchesRegularExpression( '#<textarea[^>]*name="f_mandate"#', $org );
		$this->assertMatchesRegularExpression( '#<textarea[^>]*name="f_achievement"[^>]*maxlength="6000"#', $org );
	}

	public function test_REQ_125_field_labels_are_plain_words_from_the_config(): void {
		$person = $this->screen_html( $this->profile( 'individual' ) );
		$this->assertStringContainsString( '>Current title<', $person );
		$this->assertStringContainsString( '>Public roles on record<', $person );
		$this->assertStringContainsString( '<label for="wiw-f-current_title">', $person, 'each label points at its field' );
		$org = $this->screen_html( $this->profile( 'organisation' ) );
		$this->assertStringContainsString( '>Head office city<', $org );
		$this->assertStringContainsString( '>Organisation type *<', $org );
		foreach ( array( 'Hq city', 'Org type', 'Current_title', 'Year_founded' ) as $machine ) {
			$this->assertStringNotContainsString( '>' . $machine, $org . $person );
		}
	}

	public function test_REQ_125_paragraph_breaks_survive_the_form_and_the_save(): void {
		$id      = $this->profile( 'individual' );
		$text    = "First paragraph about the work.\r\n\r\nSecond paragraph, after a blank line.";
		$changes = ProfileEditScreen::changes_from(
			new Request(
				array(
					'f_achievement' => $text,
					'f_influence'   => "One.\n\nTwo.",
					'f_legacy'      => '',
				)
			),
			'individual'
		);
		$this->assertSame( "First paragraph about the work.\n\nSecond paragraph, after a blank line.", $changes['achievement'] );
		$this->assertSame( "One.\n\nTwo.", $changes['influence'] );
		( new ProfileEditor() )->edit( $id, $changes, 'Sections written', $this->user( 'wiw_editor' ) );
		$this->assertSame( "First paragraph about the work.\n\nSecond paragraph, after a blank line.", get_post_meta( $id, 'wiw_achievement', true ) );
		$html = $this->screen_html( $id );
		$this->assertStringContainsString( "First paragraph about the work.\n\nSecond paragraph", $html, 'the box shows the text with its blank line' );
	}

	public function test_REQ_125_a_section_longer_than_6000_characters_is_refused(): void {
		$id = $this->profile( 'individual' );
		try {
			( new ProfileEditor() )->edit( $id, array( 'legacy' => str_repeat( 'é', 6001 ) ), 'Too long', $this->user( 'wiw_editor' ) );
			$this->fail( 'expected a refusal' );
		} catch ( EditRefused $e ) {
			$this->assertSame( '', (string) get_post_meta( $id, 'wiw_legacy', true ) );
		}
		( new ProfileEditor() )->edit( $id, array( 'legacy' => str_repeat( 'é', 6000 ) ), 'Exactly the limit', $this->user( 'wiw_editor' ) );
		$this->assertSame( 6000, mb_strlen( (string) get_post_meta( $id, 'wiw_legacy', true ) ) );
	}

	public function test_REQ_162_staff_set_a_photo_with_its_credit_audited_and_versioned(): void {
		$id     = $this->profile( 'individual' );
		$image  = $this->attachment();
		$editor = $this->user( 'wiw_editor' );
		$result = ( new ProfileImageEditor() )->set( $id, $image, $this->credit(), 'Photo from Commons', $editor );

		$this->assertTrue( $result['applied'] );
		$this->assertSame( (string) $image, (string) get_post_meta( $id, PublicProfile::IMAGE_META, true ) );
		$this->assertSame( 'CC-BY-SA-4.0', get_post_meta( $image, ImageMeta::LICENCE, true ) );
		$this->assertSame( 'https://creativecommons.org/licenses/by-sa/4.0/', get_post_meta( $image, ImageMeta::LICENCE_URL, true ) );
		$this->assertSame( 'Fictional Photographer', get_post_meta( $image, ImageMeta::CREATOR, true ) );
		$this->assertSame( 'Fictional Photographer', get_post_meta( $image, ImageMeta::ATTRIBUTION, true ) );
		$this->assertSame( 'https://commons.example.org/wiki/File:Fictional.jpg', get_post_meta( $image, ImageMeta::SOURCE_URL, true ) );
		$this->assertSame( 'photo', get_post_meta( $image, ImageMeta::KIND, true ) );
		$this->assertSame( 1, $this->audit_rows( 'profile.image_change', $id ) );
		$versions = $this->versions( $id );
		$this->assertCount( 1, $versions );
		$this->assertSame( 'edit', $versions[0]['created_via'] );
		$this->assertSame( 'Photo from Commons', $versions[0]['reason'] );
		$this->assertSame( $image, json_decode( (string) $versions[0]['new_value'], true )['image_id'] );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, ( new StateRepository() )->get( $id )['pub_state'], 'like a text edit, a reviewed profile stays reviewed' );
	}

	public function test_REQ_162_an_organisation_gets_a_logo_and_png_and_webp_are_accepted(): void {
		$id = $this->profile( 'organisation' );
		foreach ( array( 'image/png', 'image/webp' ) as $mime ) {
			$image = $this->attachment( $mime );
			( new ProfileImageEditor() )->set( $id, $image, $this->credit(), 'Logo ' . $mime, $this->user( 'wiw_editor' ) );
			$this->assertSame( (string) $image, (string) get_post_meta( $id, PublicProfile::IMAGE_META, true ) );
			$this->assertSame( 'logo', get_post_meta( $image, ImageMeta::KIND, true ) );
		}
	}

	public function test_REQ_203_an_image_needs_an_allowed_licence_a_credit_a_source_and_an_allowed_type(): void {
		$id     = $this->profile( 'individual' );
		$editor = $this->user( 'wiw_editor' );
		$good   = $this->attachment();
		$cases  = array(
			'licence not on the list' => array( $good, array( 'licence' => 'CC-BY-NC-4.0' ) + $this->credit() ),
			'no licence'              => array( $good, array( 'licence' => '' ) + $this->credit() ),
			'no creator'              => array( $good, array( 'creator' => '  ' ) + $this->credit() ),
			'no source'               => array( $good, array( 'source_url' => '' ) + $this->credit() ),
			'source not a web page'   => array( $good, array( 'source_url' => 'javascript:alert(1)' ) + $this->credit() ),
			'a GIF'                   => array( $this->attachment( 'image/gif' ), $this->credit() ),
			'not an attachment'       => array( $id, $this->credit() ),
			'no image'                => array( 0, $this->credit() ),
		);
		foreach ( $cases as $label => $case ) {
			try {
				( new ProfileImageEditor() )->set( $id, $case[0], $case[1], 'Try', $editor );
				$this->fail( "expected a refusal: {$label}" );
			} catch ( EditRefused $e ) {
				$this->assertSame( '', (string) get_post_meta( $id, PublicProfile::IMAGE_META, true ), $label );
			}
		}
		$this->assertSame( '', (string) get_post_meta( $good, ImageMeta::LICENCE, true ), 'a refused change writes no credit' );
		$this->assertSame( 0, $this->audit_rows( 'profile.image_change', $id ) );
	}

	public function test_REQ_125_an_image_change_needs_a_reason(): void {
		$id = $this->profile( 'individual' );
		$this->expectException( EditRefused::class );
		( new ProfileImageEditor() )->set( $id, $this->attachment(), $this->credit(), ' ', $this->user( 'wiw_editor' ) );
	}

	public function test_REQ_162_staff_change_the_credit_and_remove_the_image(): void {
		$id     = $this->profile( 'individual' );
		$image  = $this->attachment();
		$editor = $this->user( 'wiw_editor' );
		$images = new ProfileImageEditor();
		$images->set( $id, $image, $this->credit(), 'Add', $editor );
		$images->set( $id, $image, array( 'creator' => 'Corrected Name' ) + $this->credit(), 'Credit corrected', $editor );
		$this->assertSame( 'Corrected Name', get_post_meta( $image, ImageMeta::ATTRIBUTION, true ) );
		try {
			$images->set( $id, $image, array( 'creator' => 'Corrected Name' ) + $this->credit(), 'Again', $editor );
			$this->fail( 'expected "nothing changed"' );
		} catch ( EditRefused $e ) {
			$this->assertStringContainsString( 'Nothing changed', $e->getMessage() );
		}
		$images->remove( $id, 'Owner asked', $editor );
		$this->assertSame( '', (string) get_post_meta( $id, PublicProfile::IMAGE_META, true ) );
		$this->assertNotNull( get_post( $image ), 'the media library item itself stays' );
		$this->assertSame( 3, $this->audit_rows( 'profile.image_change', $id ) );
		$this->assertCount( 3, $this->versions( $id ) );
	}

	public function test_REQ_244_a_reviewer_cannot_change_the_image(): void {
		$id       = $this->profile( 'individual' );
		$reviewer = $this->user( 'wiw_reviewer' );
		try {
			( new ProfileImageEditor() )->set( $id, $this->attachment(), $this->credit(), 'Try', $reviewer );
			$this->fail( 'expected a refusal' );
		} catch ( EditRefused $e ) {
			$this->assertSame( '', (string) get_post_meta( $id, PublicProfile::IMAGE_META, true ) );
		}
		wp_set_current_user( $reviewer );
		$request = new Request( array( '_wpnonce' => ActionGuard::nonce_for( ProfileEditScreen::IMAGE ) ) );
		$this->assertSame( ActionGuard::REFUSED_CAPABILITY, ActionGuard::check( ProfileEditScreen::IMAGE, ProfileEditScreen::CAPABILITY, $request ) );
		$editor = $this->user( 'wiw_editor' );
		wp_set_current_user( $editor );
		$this->assertSame( ActionGuard::REFUSED_NONCE, ActionGuard::check( ProfileEditScreen::IMAGE, ProfileEditScreen::CAPABILITY, new Request( array( '_wpnonce' => 'bad' ) ) ) );
	}

	public function test_REQ_125_the_assigned_manager_of_a_claimed_profile_proposes_an_image_for_review(): void {
		$manager = $this->user( 'wiw_editor' );
		$id      = $this->profile(
			'individual',
			PubState::PUBLISHED_REVIEWED,
			array(
				'claimed'          => 1,
				'assigned_user_id' => $manager,
			)
		);
		$image   = $this->attachment();
		$result  = ( new ProfileImageEditor() )->set( $id, $image, $this->credit(), 'Manager photo', $manager );
		$this->assertFalse( $result['applied'] );
		$this->assertSame( '', (string) get_post_meta( $id, PublicProfile::IMAGE_META, true ), 'the public image does not change before review' );
		$this->assertSame( '', (string) get_post_meta( $image, ImageMeta::LICENCE, true ), 'nor its credit' );
		$this->assertSame( 1, $this->audit_rows( 'profile.revision_submitted', $id ) );
		$pending = array_values( array_filter( ( new VersionRepository() )->pending_for_profile( $id ), fn ( $row ): bool => (int) $row['id'] > $this->version_floor ) );
		$this->assertCount( 1, $pending );

		( new RevisionService( null, static function (): void {} ) )->approve( (int) $pending[0]['id'], $this->user( 'wiw_reviewer' ) );
		$this->assertSame( (string) $image, (string) get_post_meta( $id, PublicProfile::IMAGE_META, true ) );
		$this->assertSame( 'CC-BY-SA-4.0', get_post_meta( $image, ImageMeta::LICENCE, true ) );
		$this->assertSame( 'Fictional Photographer', get_post_meta( $image, ImageMeta::ATTRIBUTION, true ) );
	}

	public function test_REQ_162_the_screen_offers_the_media_picker_and_the_licence_list(): void {
		$id    = $this->profile( 'individual' );
		$image = $this->attachment();
		( new ProfileImageEditor() )->set( $id, $image, $this->credit(), 'Add', $this->user( 'wiw_editor' ) );
		$html = $this->screen_html( $id );
		$this->assertStringContainsString( 'name="action" value="' . ProfileEditScreen::IMAGE . '"', $html );
		$this->assertStringContainsString( 'class="button wiw-add-image-pick"', $html );
		$this->assertStringContainsString( '<option value="CC-BY-SA-4.0" selected=\'selected\'>', $html );
		$this->assertStringNotContainsString( 'CC-BY-NC', $html );
		$this->assertStringContainsString( 'value="Fictional Photographer"', $html );
		$this->assertStringContainsString( 'value="https://commons.example.org/wiki/File:Fictional.jpg"', $html );
		$this->assertStringContainsString( 'name="op" value="remove"', $html );
		$this->assertStringContainsString( '>Photo<', $html );
		$org = $this->screen_html( $this->profile( 'organisation' ) );
		$this->assertStringContainsString( '>Logo<', $org );
		$this->assertStringNotContainsString( 'name="op" value="remove"', $org, 'nothing to remove when there is no logo' );
	}
}
