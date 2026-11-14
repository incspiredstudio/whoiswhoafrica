<?php
/**
 * Edit profile classification (REQ-009, REQ-125, REQ-143): the Industry picker, Organisation type, Country and City replace the
 * old "Lists" multi-selects and text fields; a save goes through ProfileEditor with a version and an audit row and sets
 * _wiw_primary_industry, _wiw_primary_country and the terms; a staff edit writes a manual provenance row so the fact stays public;
 * the panels are renamed and the dead Arrangements panel is gone. Plain TestCase on the real tables; cleans up after itself.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Classification;
use WhoIsWho\Core\Directory\Admin\ClassificationPicker;
use WhoIsWho\Core\Directory\Admin\ProfileEditScreen;
use WhoIsWho\Core\Directory\Admin\ProfilePanels;
use WhoIsWho\Core\Directory\Edit\EditRefused;
use WhoIsWho\Core\Directory\Edit\ClassificationInput;
use WhoIsWho\Core\Directory\Edit\ProfileEditor;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\ReviewService;
use WhoIsWho\Core\Review\Revisions\RevisionService;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ClassificationEditTest extends TestCase {

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
	 * Terms to remove, last made first: [term id, taxonomy].
	 *
	 * @var array<int, array{0: int, 1: string}>
	 */
	private array $terms = array();

	/**
	 * Terms of the fixture tree.
	 *
	 * @var array<string, int>
	 */
	private array $t = array();

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
		( new Taxonomies() )->register();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
		global $wpdb;
		$this->audit_floor = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}wiw_audit_log" ); // phpcs:ignore WordPress.DB
		$suffix            = strtolower( wp_generate_password( 6, false, false ) );
		$existing          = get_term_by( 'slug', Classification::unclassified_slug(), Taxonomies::INDUSTRY );
		$this->t['unc']    = $existing instanceof \WP_Term ? (int) $existing->term_id : $this->term( 'Unclassified', Taxonomies::INDUSTRY, 0, '', Classification::unclassified_slug() );
		$this->t['energy'] = $this->term( 'Energy ' . $suffix, Taxonomies::INDUSTRY );
		$this->t['oil']    = $this->term( 'Oil ' . $suffix, Taxonomies::INDUSTRY, $this->t['energy'] );
		$this->t['power']  = $this->term( 'Power ' . $suffix, Taxonomies::INDUSTRY, $this->t['energy'] );
		$this->t['bank']   = $this->term( 'Company ' . $suffix, Taxonomies::ORG_TYPE );
		$this->t['ng']     = $this->term( 'Testnigeria ' . $suffix, Taxonomies::PLACE, 0, Taxonomies::KIND_COUNTRY );
		$this->t['gh']     = $this->term( 'Testghana ' . $suffix, Taxonomies::PLACE, 0, Taxonomies::KIND_COUNTRY );
		$this->t['lagos']  = $this->term( 'Testlagos ' . $suffix, Taxonomies::PLACE, $this->t['ng'], Taxonomies::KIND_CITY );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( array_reverse( $this->terms ) as list( $term, $taxonomy ) ) {
			wp_delete_term( $term, $taxonomy );
		}
		foreach ( $this->users as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->posts = array();
		$this->users = array();
		$this->terms = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function term( string $name, string $taxonomy, int $parent_id = 0, string $kind = '', string $slug = '' ): int {
		$args = array( 'parent' => $parent_id );
		if ( '' !== $slug ) {
			$args['slug'] = $slug;
		}
		$made = wp_insert_term( $name, $taxonomy, $args );
		$this->assertIsArray( $made, $name );
		$id = (int) $made['term_id'];
		if ( '' !== $kind ) {
			update_term_meta( $id, Taxonomies::META_KIND, $kind );
		}
		$this->terms[] = array( $id, $taxonomy );
		return $id;
	}

	private function profile( string $type = 'organisation', bool $published = true ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Classified test body',
			)
		);
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => $type ) );
		( new ProvenanceRepository() )->record(
			$id,
			array(
				'field'        => 'name',
				'source_url'   => 'https://source.example.test/body',
				'retrieved_at' => '2026-10-01',
				'confidence'   => 'high',
				'method'       => 'import',
			)
		);
		if ( $published ) {
			( new ReviewService() )->approve( $id, $this->user( 'wiw_reviewer' ) );
		}
		return $id;
	}

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'cls_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	/**
	 * A full classification.
	 *
	 * @param array<string, mixed> $over Overrides.
	 * @return array<string, mixed>
	 */
	private function wanted( array $over = array() ): array {
		return array_merge(
			array(
				'primary_industry' => $this->t['oil'],
				'industries'       => array( $this->t['power'] ),
				'org_type'         => $this->t['bank'],
				'country'          => $this->t['ng'],
				'city'             => $this->t['lagos'],
			),
			$over
		);
	}

	private function ids( int $id, string $taxonomy ): array {
		$ids = array_map( 'intval', wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'ids' ) ) );
		sort( $ids );
		return $ids;
	}

	private function audit_rows( string $action, int $id ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wiw_audit_log WHERE id > %d AND action = %s AND subject_id = %s", $this->audit_floor, $action, (string) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public function test_REQ_009_a_classification_edit_sets_the_primary_meta_and_terms_with_a_version_and_audit(): void {
		$id    = $this->profile();
		$staff = $this->user( 'wiw_editor' );
		wp_set_current_user( $staff );
		$result = ( new ProfileEditor() )->edit( $id, array(), 'Classified from the annual report', $staff, 0, array(), $this->wanted() );
		$this->assertTrue( $result['applied'] );
		$this->assertSame( array( ProfileEditor::CLASSIFICATION_KEY ), $result['changed'] );
		$this->assertSame( (string) $this->t['oil'], get_post_meta( $id, Classification::PRIMARY_INDUSTRY_META, true ) );
		$this->assertSame( (string) $this->t['ng'], get_post_meta( $id, Classification::PRIMARY_COUNTRY_META, true ) );
		$industries = array( $this->t['oil'], $this->t['power'] );
		sort( $industries );
		$this->assertSame( $industries, $this->ids( $id, Taxonomies::INDUSTRY ) );
		$places = array( $this->t['ng'], $this->t['lagos'] );
		sort( $places );
		$this->assertSame( $places, $this->ids( $id, Taxonomies::PLACE ) );
		$this->assertSame( array( $this->t['bank'] ), $this->ids( $id, Taxonomies::ORG_TYPE ) );
		$this->assertSame( $this->wanted( array( 'industries' => array( $this->t['power'] ) ) ), ( new ClassificationInput() )->current( $id ) );

		$version = ( new VersionRepository() )->for_profile( $id );
		$last    = end( $version );
		$this->assertSame( 'Classified from the annual report', $last['reason'] );
		$this->assertArrayHasKey( ProfileEditor::CLASSIFICATION_KEY, (array) json_decode( (string) $last['new_value'], true ) );
		$this->assertCount( 1, $this->audit_rows( 'profile.edit', $id ) );

		$this->expectException( EditRefused::class );
		( new ProfileEditor() )->edit( $id, array(), 'Again', $staff, 0, array(), $this->wanted() );
	}

	public function test_REQ_009_a_sector_a_foreign_city_or_an_unknown_type_is_refused(): void {
		$id    = $this->profile();
		$staff = $this->user( 'wiw_editor' );
		$bad   = array(
			'sector as main industry' => array( 'primary_industry' => $this->t['energy'] ),
			'sector as extra'         => array( 'industries' => array( $this->t['energy'] ) ),
			'city in another country' => array( 'country' => $this->t['gh'] ),
			'city as country'         => array(
				'country' => $this->t['lagos'],
				'city'    => 0,
			),
			'industry as org type'    => array( 'org_type' => $this->t['oil'] ),
		);
		foreach ( $bad as $case => $over ) {
			try {
				( new ProfileEditor() )->edit( $id, array(), 'Bad', $staff, 0, array(), $this->wanted( $over ) );
				$this->fail( $case . ' was accepted' );
			} catch ( EditRefused $refused ) {
				$this->assertNotSame( '', $refused->getMessage(), $case );
			}
		}
		$this->assertSame( '', get_post_meta( $id, Classification::PRIMARY_INDUSTRY_META, true ) );
	}

	public function test_REQ_009_unclassified_is_an_allowed_main_industry_and_a_person_has_no_org_type(): void {
		$id    = $this->profile( 'individual' );
		$staff = $this->user( 'wiw_editor' );
		( new ProfileEditor() )->edit( $id, array(), 'Not sure yet', $staff, 0, array(), $this->wanted( array( 'primary_industry' => $this->t['unc'] ) ) );
		$this->assertSame( (string) $this->t['unc'], get_post_meta( $id, Classification::PRIMARY_INDUSTRY_META, true ) );
		$this->assertSame( array(), $this->ids( $id, Taxonomies::ORG_TYPE ), 'people carry no organisation type' );
	}

	public function test_REQ_244_a_manager_with_limited_rights_cannot_classify(): void {
		$id = $this->profile();
		$this->expectException( EditRefused::class );
		( new ProfileEditor() )->edit( $id, array(), 'Mine', $this->user( 'wiw_reviewer' ), 0, array(), $this->wanted() );
	}

	public function test_REQ_143_a_staff_edit_writes_a_manual_provenance_row_so_the_fact_stays_public(): void {
		$id    = $this->profile();
		$staff = $this->user( 'wiw_editor' );
		( new ProfileEditor() )->edit( $id, array( 'website' => 'https://bank.example.org' ), 'Owner request', $staff );
		$rows = ( new ProvenanceRepository() )->for_profile( $id, 'website' );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'manual', $rows[0]['method'] );
		$this->assertSame( ProfileEditor::STAFF_SOURCE, $rows[0]['source_url'] );
		$this->assertSame( 'medium', $rows[0]['confidence'] );
		$public = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $public );
		$this->assertSame( 'https://bank.example.org', $public->facts['website']['value'] ?? null, 'the edited fact is not dropped from the public page' );
	}

	public function test_REQ_125_the_screen_shows_the_pickers_and_no_old_lists_or_text_fields(): void {
		$id = $this->profile();
		( new ProfileEditor() )->edit( $id, array(), 'Set', $this->user( 'wiw_editor' ), 0, array(), $this->wanted() );
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$html = ( new ProfileEditScreen() )->html( new Request( array( 'profile_id' => (string) $id ) ) );
		foreach ( array( 'cls_present', 'cls_primary_industry', 'cls_industries[]', 'cls_org_type', 'cls_country', 'cls_city' ) as $name ) {
			$this->assertStringContainsString( 'name="' . $name . '"', $html, $name );
		}
		foreach ( array( 'f_industry', 'f_sector', 'f_org_type', 'f_hq_city', 'terms_wiw_industry[]', 'terms_wiw_sector[]', 'terms_wiw_place[]', 'terms_wiw_org_type[]' ) as $name ) {
			$this->assertStringNotContainsString( 'name="' . $name . '"', $html, $name );
		}
		$this->assertStringNotContainsString( '<h3>Lists</h3>', $html );
		$this->assertStringNotContainsString( 'Arrangements', $html );
		$this->assertStringNotContainsString( 'Source sync', $html );
		$this->assertStringNotContainsString( 'Changes from the source since review', $html, 'hidden when nothing is pending' );
		$this->assertStringContainsString( 'Where each fact comes from', $html );
		$this->assertMatchesRegularExpression( '#<optgroup label="Energy [a-z0-9]+">#', $html, 'industries are grouped by sector' );
		$this->assertMatchesRegularExpression( '#<input type="checkbox" name="cls_industries\[\]" value="' . $this->t['power'] . '" checked="checked" />#', $html, 'the profile\'s own extra is a chip' );
		$this->assertSame( 1, substr_count( $html, 'type="checkbox" name="cls_industries[]"' ), 'only the profile\'s own extras are chips' );
		$this->assertMatchesRegularExpression( '#<option value="' . $this->t['lagos'] . '" selected=\'selected\'>#', $html );
		$this->assertMatchesRegularExpression( '#<optgroup label="Testnigeria [a-z0-9]+" data-wiw-country="' . $this->t['ng'] . '">#', $html, 'cities are grouped by country' );
	}

	public function test_REQ_125_the_handler_reads_the_picker_and_untouched_forms_leave_the_classification_alone(): void {
		$picker = new ClassificationPicker();
		$this->assertNull( $picker->from_request( new Request( array( 'cls_primary_industry' => '5' ) ) ) );
		$this->assertSame(
			array(
				'primary_industry' => 7,
				'industries'       => array( 8, 9 ),
				'org_type'         => 3,
				'country'          => 2,
				'city'             => 0,
			),
			$picker->from_request(
				new Request(
					array(
						'cls_present'          => '1',
						'cls_primary_industry' => '7',
						'cls_industries'       => array( '8', '0', '9' ),
						'cls_org_type'         => '3',
						'cls_country'          => '2',
					)
				)
			)
		);
	}

	public function test_REQ_161_the_source_changes_panel_shows_only_with_a_pending_change(): void {
		$id = $this->profile();
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$panels = new ProfilePanels();
		$this->assertStringNotContainsString( 'Changes from the source since review', $panels->html( $id, (array) ( new StateRepository() )->get( $id ) ) );
		( new VersionRepository() )->append( $id, 0, 'import', '', array( 'website' => 'https://a.example' ), array( 'website' => 'https://b.example' ), 'pending' );
		$html = $panels->html( $id, (array) ( new StateRepository() )->get( $id ) );
		$this->assertStringContainsString( 'Changes from the source since review', $html );
		$this->assertStringContainsString( 'not shown publicly until a reviewer accepts them', $html );
	}

	public function test_REQ_009_an_approved_revision_applies_its_classification(): void {
		$id      = $this->profile();
		$author  = $this->user( 'wiw_editor' );
		$version = ( new VersionRepository() )->append( $id, $author, 'revision', 'Manager change', array( ProfileEditor::CLASSIFICATION_KEY => ClassificationInput::blank() ), array( ProfileEditor::CLASSIFICATION_KEY => $this->wanted() ), 'pending' );
		$admin   = $this->user( 'administrator' );
		wp_set_current_user( $admin );
		( new RevisionService( null, static function (): void {} ) )->approve( $version, $admin );
		$this->assertSame( (string) $this->t['oil'], get_post_meta( $id, Classification::PRIMARY_INDUSTRY_META, true ) );
		$this->assertSame( array( $this->t['bank'] ), $this->ids( $id, Taxonomies::ORG_TYPE ) );
	}
}
