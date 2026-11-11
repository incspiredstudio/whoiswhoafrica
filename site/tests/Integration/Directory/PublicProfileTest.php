<?php
/**
 * The PublicProfile read model (REQ-208, REQ-139, REQ-144, REQ-060, REQ-247, REQ-216). Needs the WordPress test suite. Plain
 * TestCase because it needs the real state and provenance tables; its posts and rows are removed in tearDown.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Core\Review\StatePolicy;
use WhoIsWho\Tests\TestCase;

final class PublicProfileTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Create a profile in a state with sourced fields.
	 *
	 * @param string               $name   Name.
	 * @param string               $type   Entity type.
	 * @param string               $state  Target state.
	 * @param array<string, mixed> $fields Sourced fields.
	 */
	private function profile( string $name, string $type, string $state, array $fields = array() ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$start         = in_array( $state, array( PubState::IMPORTED_NOT_PUBLIC, PubState::REJECTED ), true ) ? PubState::IMPORTED_NOT_PUBLIC : PubState::PUBLISHED_PENDING;
		$machine->enter( $id, $start, array( 'entity_type' => $type ) );
		$store = new ProfileFields();
		$prov  = new ProvenanceRepository();
		foreach ( $fields as $key => $value ) {
			$store->set( $id, $key, $value );
			$prov->record(
				$id,
				array(
					'field'        => $key,
					'source_url'   => 'https://example.org/src',
					'retrieved_at' => '2026-11-10T09:00:00Z',
					'confidence'   => 'high',
					'method'       => 'import',
				)
			);
		}
		if ( PubState::PUBLISHED_REVIEWED === $state ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		} elseif ( in_array( $state, array( PubState::UNPUBLISHED, PubState::REJECTED, PubState::REMOVED_SUPPRESSED ), true ) ) {
			$machine->transition( $id, PubState::REJECTED === $state ? PubState::REJECTED : PubState::UNPUBLISHED, 'test' );
			if ( PubState::REMOVED_SUPPRESSED === $state ) {
				$machine->transition( $id, PubState::REMOVED_SUPPRESSED, 'test' );
			}
		}
		return $id;
	}

	public function test_REQ_208_hides_unreviewed_profiles_from_every_surface(): void {
		$slugs = array();
		foreach ( PubState::all() as $state ) {
			$type            = PubState::PUBLISHED_PENDING === $state ? 'organisation' : 'individual';
			$id              = $this->profile( 'Matrix ' . $state . ' Qwv', $type, $state );
			$slugs[ $state ] = get_post_field( 'post_name', $id );
		}
		foreach ( PubState::all() as $state ) {
			$found = PublicProfile::find( $slugs[ $state ] );
			if ( PubState::is_public( $state ) ) {
				$this->assertNotNull( $found, $state );
			} else {
				$this->assertNull( $found, "{$state} gives no object to a public caller" );
			}
		}
		$choices = PublicProfile::public_choices();
		$this->assertContains( 'Matrix published_reviewed Qwv', $choices );
		$this->assertNotContains( 'Matrix published_pending Qwv', $choices, 'Pickers offer reviewed profiles only.' );
		$this->assertNotContains( 'Matrix imported_not_public Qwv', $choices );
		$this->assertContains( 'Matrix published_reviewed Qwv', apply_filters( 'wiw_public_profile_choices', array() ) );
	}

	public function test_REQ_216_pending_profile_carries_label_and_noindex(): void {
		$id      = $this->profile( 'Pending Org Qwv', 'organisation', PubState::PUBLISHED_PENDING );
		$profile = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $profile );
		$this->assertTrue( $profile->noindex );
		$this->assertSame( StatePolicy::PENDING_LABEL, $profile->label );
		$this->assertSame( $profile->label, $profile->share['label'] );
		add_filter( 'wiw_pending_label', static fn (): string => 'Legal wording' );
		$this->assertSame( 'Legal wording', PublicProfile::find_by_id( $id )->label );
		remove_all_filters( 'wiw_pending_label' );

		$reviewed = PublicProfile::find_by_id( $this->profile( 'Reviewed Org Qwv', 'organisation', PubState::PUBLISHED_REVIEWED ) );
		$this->assertFalse( $reviewed->noindex );
		$this->assertNull( $reviewed->label );
		$this->assertNotNull( $reviewed->reviewed_at );
	}

	public function test_REQ_144_fact_without_source_is_not_returned(): void {
		$id = $this->profile(
			'Sourced Person Qwv',
			'individual',
			PubState::PUBLISHED_REVIEWED,
			array(
				'current_title'        => 'Chief Executive',
				'current_organisation' => 'Example Bank',
			)
		);
		( new ProfileFields() )->set( $id, 'industry', 'Banking' ); // Stored, but no provenance row.
		$profile = PublicProfile::find_by_id( $id );
		$this->assertSame( 'Chief Executive', $profile->facts['current_title']['value'] );
		$this->assertSame( 'https://example.org/src', $profile->facts['current_title']['source_url'] );
		$this->assertSame( '2026-11-10 09:00:00', $profile->facts['current_title']['retrieved_at'] );
		$this->assertArrayNotHasKey( 'industry', $profile->facts, 'An unsourced fact is omitted.' );
	}

	public function test_REQ_139_read_model_returns_allowlisted_fields_only(): void {
		$id = $this->profile( 'Allow Person Qwv', 'individual', PubState::PUBLISHED_REVIEWED, array( 'current_title' => 'Director' ) );
		update_post_meta( $id, 'wiw_internal_note', 'secret' );
		$profile = PublicProfile::find_by_id( $id );
		$this->assertSame( array( 'current_title' ), array_keys( $profile->facts ) );
		$this->assertStringNotContainsString( 'secret', (string) wp_json_encode( get_object_vars( $profile ) ) );
	}

	public function test_REQ_060_contact_details_private_by_default(): void {
		$profile = PublicProfile::find_by_id( $this->profile( 'Contact Person Qwv', 'individual', PubState::PUBLISHED_REVIEWED ) );
		$props   = array_keys( get_object_vars( $profile ) );
		foreach ( array( 'email', 'phone', 'contact', 'address' ) as $name ) {
			$this->assertNotContains( $name, $props );
			$this->assertArrayNotHasKey( $name, $profile->facts );
		}
	}

	public function test_REQ_247_profile_page_has_no_cover_image(): void {
		$props = array_keys( get_class_vars( PublicProfile::class ) );
		foreach ( $props as $name ) {
			$this->assertDoesNotMatchRegularExpression( '/cover|banner|hero/i', $name );
		}
		$this->assertContains( 'image_id', $props );
	}

	public function test_REQ_208_related_organisation_hidden_when_not_public(): void {
		$org    = $this->profile( 'Linked Org Qwv', 'organisation', PubState::PUBLISHED_REVIEWED );
		$hidden = $this->profile( 'Hidden Org Qwv', 'organisation', PubState::IMPORTED_NOT_PUBLIC );
		update_post_meta( $org, PublicProfile::RECORD_ID_META, 'rec_org_a' );
		update_post_meta( $hidden, PublicProfile::RECORD_ID_META, 'rec_org_b' );

		$linked = $this->profile( 'Linked Person Qwv', 'individual', PubState::PUBLISHED_REVIEWED );
		( new ProfileFields() )->set( $linked, 'current_organisation_ref', 'rec_org_a' );
		$this->assertSame( 'Linked Org Qwv', PublicProfile::find_by_id( $linked )->organisation['name'] );

		( new ProfileFields() )->set( $linked, 'current_organisation_ref', 'rec_org_b' );
		$this->assertNull( PublicProfile::find_by_id( $linked )->organisation, 'An organisation that is not public is not linked.' );
	}

	public function test_REQ_208_staff_preview_sees_a_hidden_profile_with_a_note(): void {
		$id = $this->profile( 'Preview Person Qwv', 'individual', PubState::IMPORTED_NOT_PUBLIC );
		$this->assertNull( PublicProfile::find_by_id( $id, true ), 'A visitor asking for a preview still gets nothing.' );
		$admin = self::factory_admin();
		wp_set_current_user( $admin );
		$preview = PublicProfile::find_by_id( $id, true );
		$this->assertNotNull( $preview );
		$this->assertStringContainsString( 'Not public', (string) $preview->visibility_note );
		$this->assertNull( PublicProfile::find_by_id( $id, false ), 'Without the preview flag even staff get nothing.' );
	}

	/**
	 * An administrator user id.
	 */
	private static function factory_admin(): int {
		$user = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		if ( array() !== $user ) {
			return (int) $user[0];
		}
		return (int) wp_insert_user(
			array(
				'user_login' => 'wiw_preview_admin',
				'user_pass'  => wp_generate_password(),
				'role'       => 'administrator',
			)
		);
	}
}
