<?php
/**
 * Staff add a profile by hand (REQ-209, REQ-208, REQ-143, REQ-145, REQ-244, REQ-267). Needs the WordPress test suite and the real
 * tables, so it is a plain TestCase that runs the migrations and cleans up after itself.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\Add\AddProfileFields;
use WhoIsWho\Core\Directory\Add\AddProfileService;
use WhoIsWho\Core\Directory\Admin\AddProfileScreen;
use WhoIsWho\Core\Directory\ImageMeta;
use WhoIsWho\Core\Import\Images\ImagePolicy;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\Duplicates\Matcher;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class AddProfileTest extends TestCase {

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
	 * Terms to remove: [term id, taxonomy].
	 *
	 * @var array<int, array{0: int, 1: string}>
	 */
	private array $terms = array();

	private int $audit_floor = 0;

	private int $country = 0;

	private int $industry = 0;

	private int $org_type = 0;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! post_type_exists( ProfileType::POST_TYPE ) ) {
			( new ProfileType() )->register();
		}
		if ( ! taxonomy_exists( Taxonomies::PLACE ) ) {
			( new Taxonomies() )->register();
		}
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
		global $wpdb;
		$this->audit_floor = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}wiw_audit_log" ); // phpcs:ignore WordPress.DB
		$this->country     = $this->term( 'Testland ' . wp_generate_password( 6, false ), Taxonomies::PLACE, Taxonomies::KIND_COUNTRY );
		$this->industry    = $this->term( 'Test music ' . wp_generate_password( 6, false ), Taxonomies::INDUSTRY );
		$this->org_type    = $this->term( 'Test bank ' . wp_generate_password( 6, false ), Taxonomies::ORG_TYPE );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( $this->terms as list( $term, $taxonomy ) ) {
			wp_delete_term( $term, $taxonomy );
		}
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Suppression::table() . ' WHERE name LIKE %s', 'Zz Suppressed%' ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		foreach ( $this->users as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->posts = array();
		$this->users = array();
		$this->terms = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function term( string $name, string $taxonomy, string $kind = '' ): int {
		$made = wp_insert_term( $name, $taxonomy );
		$this->assertIsArray( $made );
		$id = (int) $made['term_id'];
		if ( '' !== $kind ) {
			update_term_meta( $id, Taxonomies::META_KIND, $kind );
		}
		$this->terms[] = array( $id, $taxonomy );
		return $id;
	}

	private function user( string $role ): int {
		$id            = (int) wp_insert_user(
			array(
				'user_login' => 'addp_' . $role . '_' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password( 20 ),
				'user_email' => 'addp_' . wp_generate_password( 6, false ) . '@example.invalid',
				'role'       => $role,
			)
		);
		$this->users[] = $id;
		return $id;
	}

	private function as_editor(): int {
		$id = $this->user( 'wiw_editor' );
		wp_set_current_user( 0 );
		wp_set_current_user( $id );
		return $id;
	}

	/**
	 * A valid submission for a person.
	 *
	 * @param array<string, mixed> $over Overrides.
	 * @return array<string, mixed>
	 */
	private function person( array $over = array() ): array {
		return array_merge(
			array(
				'entity_type'            => 'individual',
				'name'                   => 'Ifeoma Testperson ' . wp_generate_password( 6, false, false ),
				'country'                => (string) $this->country,
				'f_industry'             => (string) $this->industry,
				'f_current_title'        => 'Composer',
				'f_current_organisation' => 'Fictional Orchestra',
				'f_bio'                  => "First paragraph of a fictional bio.\n\nSecond paragraph.",
				'f_achievement'          => 'Wrote a fictional symphony.',
				'f_influence'            => 'Taught fictional students.',
				'f_legacy'               => 'A fictional school of music.',
				'sources'                => "https://example.org/source-one\nhttps://example.org/source-two",
			),
			$over
		);
	}

	/**
	 * Run the screen's form processing and remember any profile it created.
	 *
	 * @param array<string, mixed> $values Request values.
	 * @return array<string, mixed>
	 */
	private function submit( array $values ): array {
		$result = ( new AddProfileScreen() )->process( new Request( $values ) );
		if ( ! empty( $result['profile_id'] ) ) {
			$this->posts[] = (int) $result['profile_id'];
		}
		return $result;
	}

	/**
	 * Audit rows written since the test started.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function audit_rows(): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wiw_audit_log WHERE id > %d ORDER BY id", $this->audit_floor ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	private function existing( string $name, string $type, array $external_ids = array() ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => $type ) );
		if ( array() !== $external_ids ) {
			update_post_meta( $id, Matcher::EXTERNAL_IDS_META, wp_json_encode( $external_ids ) );
		}
		return $id;
	}

	public function test_REQ_209_validation_requires_name_country_and_a_source(): void {
		$this->as_editor();
		$result = $this->submit(
			$this->person(
				array(
					'name'    => '',
					'country' => '',
					'sources' => '',
				)
			)
		);
		$this->assertSame( 'invalid', $result['status'] );
		foreach ( array( 'name', 'country', 'sources' ) as $field ) {
			$this->assertArrayHasKey( $field, $result['errors'] );
		}
		$this->assertArrayNotHasKey( 'profile_id', array_filter( $result ) );
	}

	public function test_REQ_209_validation_refuses_bad_urls_types_and_terms(): void {
		$this->as_editor();
		$result = $this->submit(
			$this->person(
				array(
					'entity_type' => 'alien',
					'sources'     => "https://example.org/ok\njavascript:alert(1)",
				)
			)
		);
		$this->assertSame( 'invalid', $result['status'] );
		$this->assertArrayHasKey( 'entity_type', $result['errors'] );

		$result = $this->submit(
			$this->person(
				array(
					'sources'    => "https://example.org/ok\njavascript:alert(1)",
					'f_industry' => '999999999',
					'country'    => (string) $this->industry,
				)
			)
		);
		$this->assertSame( 'invalid', $result['status'] );
		$this->assertArrayHasKey( 'sources', $result['errors'] );
		$this->assertArrayHasKey( 'f_industry', $result['errors'] );
		$this->assertArrayHasKey( 'country', $result['errors'] );
	}

	public function test_REQ_209_organisation_needs_its_type_and_a_valid_website(): void {
		$this->as_editor();
		$result = $this->submit(
			array(
				'entity_type' => 'organisation',
				'name'        => 'Fictional Savings Bank',
				'country'     => (string) $this->country,
				'f_website'   => 'not a url',
				'sources'     => 'https://example.org/bank',
			)
		);
		$this->assertSame( 'invalid', $result['status'] );
		$this->assertArrayHasKey( 'f_org_type', $result['errors'] );
		$this->assertArrayHasKey( 'f_website', $result['errors'] );
	}

	public function test_REQ_209_an_image_needs_an_open_licence_a_credit_and_a_source(): void {
		$this->as_editor();
		$image  = $this->image();
		$result = $this->submit( $this->person( array( 'image_id' => (string) $image ) ) );
		$this->assertSame( 'invalid', $result['status'] );
		foreach ( array( 'image_licence', 'image_credit', 'image_source' ) as $field ) {
			$this->assertArrayHasKey( $field, $result['errors'] );
		}
		$result = $this->submit(
			$this->person(
				array(
					'image_id'      => (string) $image,
					'image_licence' => 'All rights reserved',
					'image_credit'  => 'A. Photographer',
					'image_source'  => 'https://example.org/photo',
				)
			)
		);
		$this->assertArrayHasKey( 'image_licence', $result['errors'] );
		$this->assertArrayNotHasKey( 'image_credit', $result['errors'] );
	}

	public function test_REQ_208_a_new_profile_starts_in_the_review_queue_and_is_not_public(): void {
		$actor  = $this->as_editor();
		$result = $this->submit( $this->person() );
		$this->assertSame( 'created', $result['status'], (string) wp_json_encode( $result ) );
		$id  = (int) $result['profile_id'];
		$row = ( new StateRepository() )->get( $id );
		$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, $row['pub_state'] );
		$this->assertSame( 'manual', $row['origin'] );
		$this->assertSame( 'individual', $row['entity_type'] );
		$this->assertSame( $actor, (int) $row['importer_id'], 'The creator may not approve their own profile.' );
		$this->assertSame( ProfileType::STATUS_HIDDEN, get_post_status( $id ) );
		$this->assertNull( PublicProfile::find_by_id( $id ) );
		$slug = (string) get_post_field( 'post_name', $id );
		if ( '' !== $slug ) {
			$this->assertNull( PublicProfile::find( $slug ) );
		}
	}

	public function test_REQ_143_fields_terms_sections_and_manual_provenance_are_stored(): void {
		$this->as_editor();
		$values = $this->person();
		$result = $this->submit( $values );
		$id     = (int) $result['profile_id'];
		$this->assertSame( $values['name'], get_the_title( $id ) );
		$this->assertSame( 'Composer', get_post_meta( $id, 'wiw_current_title', true ) );
		foreach ( AddProfileFields::SECTIONS as $key ) {
			$this->assertSame( $values[ 'f_' . $key ], get_post_meta( $id, 'wiw_' . $key, true ), $key );
		}
		$this->assertSame( array( $this->country ), array_map( 'intval', wp_get_object_terms( $id, Taxonomies::PLACE, array( 'fields' => 'ids' ) ) ) );
		$this->assertSame( array( $this->industry ), array_map( 'intval', wp_get_object_terms( $id, Taxonomies::INDUSTRY, array( 'fields' => 'ids' ) ) ) );

		$prov = new ProvenanceRepository();
		$name = $prov->for_profile( $id, 'name' );
		$this->assertCount( 2, $name, 'Every source URL backs the name.' );
		$this->assertSame( 'manual', $name[0]['method'] );
		$this->assertSame( 'https://example.org/source-one', $name[0]['source_url'] );
		$bio = $prov->for_profile( $id, 'bio' );
		$this->assertCount( 1, $bio );
		$this->assertSame( 'manual', $bio[0]['method'] );
	}

	public function test_REQ_209_the_creation_is_audited_without_field_values(): void {
		$this->as_editor();
		$values = $this->person();
		$id     = (int) $this->submit( $values )['profile_id'];
		$rows   = $this->audit_rows();
		$create = array_values( array_filter( $rows, static fn ( array $row ): bool => 'profile.create' === $row['action'] ) );
		$this->assertCount( 1, $create );
		$this->assertSame( (string) $id, (string) $create[0]['subject_id'] );
		$json = (string) wp_json_encode( $rows );
		$this->assertStringNotContainsString( 'Fictional Orchestra', $json );
		$this->assertStringNotContainsString( 'fictional symphony', $json );
	}

	public function test_REQ_145_a_possible_duplicate_is_shown_before_saving_and_can_be_overridden(): void {
		$this->as_editor();
		$existing = $this->existing( 'Chidinma Duplicate-Testcase', 'individual' );
		$result   = $this->submit( $this->person( array( 'name' => 'chidinma  duplicate testcase' ) ) );
		$this->assertSame( 'duplicates', $result['status'] );
		$this->assertContains( $existing, array_column( $result['matches'], 'id' ) );

		$other_type = $this->submit(
			array(
				'entity_type' => 'organisation',
				'name'        => 'Chidinma Duplicate-Testcase',
				'country'     => (string) $this->country,
				'f_org_type'  => (string) $this->org_type,
				'sources'     => 'https://example.org/org',
			)
		);
		$this->assertSame( 'created', $other_type['status'], 'A name match of another type is not a duplicate.' );

		$anyway = $this->submit(
			$this->person(
				array(
					'name'          => 'chidinma  duplicate testcase',
					'create_anyway' => '1',
				)
			)
		);
		$this->assertSame( 'created', $anyway['status'] );
		$override = array_values( array_filter( $this->audit_rows(), static fn ( array $row ): bool => 'profile.create' === $row['action'] && (string) $row['subject_id'] === (string) $anyway['profile_id'] ) );
		$this->assertStringContainsString( 'duplicate', (string) $override[0]['new_value'] );
	}

	public function test_REQ_145_an_external_id_match_is_a_possible_duplicate(): void {
		$this->as_editor();
		$existing = $this->existing( 'Totally Different Name', 'entrepreneur', array( 'wikidata_qid' => 'Q99999901' ) );
		$result   = $this->submit( $this->person( array( 'wikidata_qid' => 'Q99999901' ) ) );
		$this->assertSame( 'duplicates', $result['status'] );
		$this->assertContains( $existing, array_column( $result['matches'], 'id' ) );
		$this->assertSame( 'invalid', $this->submit( $this->person( array( 'wikidata_qid' => 'not-a-qid' ) ) )['status'] );
	}

	public function test_REQ_147_a_suppressed_name_cannot_be_added(): void {
		$this->as_editor();
		( new Suppression( AuditLogger::for_site() ) )->add_from_removal( 'Zz Suppressed Person', 'individual', array(), 0 );
		$result = $this->submit(
			$this->person(
				array(
					'name'          => 'Zz Suppressed Person',
					'create_anyway' => '1',
				)
			)
		);
		$this->assertSame( 'refused', $result['status'] );
		$this->assertEmpty( $result['profile_id'] ?? null );
	}

	public function test_REQ_244_the_service_refuses_a_user_without_the_capability(): void {
		wp_set_current_user( $this->user( 'wiw_reviewer' ) );
		$this->expectException( \DomainException::class );
		( new AddProfileService() )->create( 'individual', array( 'name' => 'Nobody' ), array( 'https://example.org/x' ), $this->country );
	}

	public function test_REQ_209_an_image_is_attached_with_its_licence_and_credit(): void {
		$this->as_editor();
		$image  = $this->image();
		$result = $this->submit(
			$this->person(
				array(
					'image_id'      => (string) $image,
					'image_licence' => 'CC-BY-SA-4.0',
					'image_credit'  => 'A. Photographer',
					'image_source'  => 'https://example.org/photo',
				)
			)
		);
		$this->assertSame( 'created', $result['status'], (string) wp_json_encode( $result ) );
		$id = (int) $result['profile_id'];
		$this->assertSame( (string) $image, (string) get_post_meta( $id, PublicProfile::IMAGE_META, true ) );
		$this->assertSame( 'CC-BY-SA-4.0', get_post_meta( $image, ImageMeta::LICENCE, true ) );
		$this->assertSame( ImagePolicy::licence_url( 'CC-BY-SA-4.0' ), get_post_meta( $image, ImageMeta::LICENCE_URL, true ) );
		$this->assertSame( 'A. Photographer', get_post_meta( $image, ImageMeta::ATTRIBUTION, true ) );
		$this->assertSame( 'https://example.org/photo', get_post_meta( $image, ImageMeta::SOURCE_URL, true ) );
		$this->assertSame( 'photo', get_post_meta( $image, ImageMeta::KIND, true ) );
	}

	public function test_REQ_267_return_to_nominations_carries_the_name(): void {
		$this->as_editor();
		$values = $this->person( array( 'return' => 'nominations' ) );
		$result = $this->submit( $values );
		$this->assertSame( 'created', $result['status'] );
		$url = AddProfileScreen::after_create_url( $result, 'nominations', (string) $values['name'] );
		$this->assertStringContainsString( 'page=wiw-nominations', $url );
		$this->assertStringContainsString( 'profile_q=', $url );
	}

	public function test_REQ_209_the_form_lists_the_fields_of_the_chosen_type_from_the_config(): void {
		$this->as_editor();
		$html = ( new AddProfileScreen() )->html( new Request( array( 'entity_type' => 'individual' ) ) );
		foreach ( array( 'f_current_title', 'f_current_organisation', 'f_bio', 'f_achievement', 'f_influence', 'f_legacy', 'f_lifespan', 'f_field', 'sources', 'country' ) as $name ) {
			$this->assertStringContainsString( 'name="' . $name . '"', $html, $name );
		}
		$this->assertStringNotContainsString( 'name="f_org_type"', $html );
		$html = ( new AddProfileScreen() )->html( new Request( array( 'entity_type' => 'organisation' ) ) );
		foreach ( array( 'f_org_type', 'f_website', 'f_bio', 'f_legacy' ) as $name ) {
			$this->assertStringContainsString( 'name="' . $name . '"', $html, $name );
		}
		$this->assertStringNotContainsString( 'name="f_current_title"', $html );
		$this->assertStringNotContainsString( 'name="f_lifespan"', $html );
	}

	public function test_REQ_209_labels_come_from_the_config_and_long_boxes_have_a_counter(): void {
		$this->as_editor();
		$html = ( new AddProfileScreen() )->html( new Request( array( 'entity_type' => 'organisation' ) ) );
		$this->assertStringContainsString( '>Head office city<', $html, 'the label is the config label' );
		$this->assertStringContainsString( '>Organisation type *<', $html );
		foreach ( array( 'f_mandate' => 2000, 'f_summary' => 2000, 'f_bio' => 6000 ) as $name => $max ) {
			$this->assertMatchesRegularExpression( '#<textarea[^>]*name="' . $name . '"[^>]*maxlength="' . $max . '"[^>]*data-wiw-count="wiw-count-' . $name . '"#', $html, $name );
			$this->assertStringContainsString( 'id="wiw-count-' . $name . '"', $html, $name . ' counter' );
		}
		$this->assertStringContainsString( '0 / 6000 characters', $html );
		$this->assertSame( 'Head office city', \WhoIsWho\Core\Review\Admin\Labels::field( 'hq_city' ) );
		$this->assertSame( 'Official website', \WhoIsWho\Core\Review\Admin\Labels::field( 'official_website', 'individual' ) );
	}

	/**
	 * A small image attachment in the media library.
	 */
	private function image(): int {
		$id            = (int) wp_insert_attachment(
			array(
				'post_title'     => 'Fictional portrait',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			),
			'fictional-portrait.jpg'
		);
		$this->posts[] = $id;
		return $id;
	}
}
