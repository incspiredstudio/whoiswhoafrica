<?php
/**
 * Profile page shell (REQ-003, REQ-004, REQ-005, REQ-247, REQ-082). Needs the WordPress test suite and the profile tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

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

final class ProfilePageTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__ ) . '/../wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		switch_theme( 'whoiswho-child' );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		set_query_var( 'wiw_profile_id', 0 );
		parent::tearDown();
	}

	/**
	 * Create a profile with sourced fields.
	 *
	 * @param string               $name     Name.
	 * @param string               $type     Entity type.
	 * @param bool                 $reviewed Reviewed (else pending).
	 * @param array<string, mixed> $fields   Fields.
	 */
	private function profile( string $name, string $type, bool $reviewed, array $fields ): PublicProfile {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => $type ) );
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
		if ( $reviewed ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		}
		$profile = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $profile );
		return $profile;
	}

	/**
	 * Render the profile template for a profile.
	 *
	 * @param PublicProfile $profile Profile.
	 */
	private function render( PublicProfile $profile ): string {
		set_query_var( 'wiw_profile_id', $profile->id );
		ob_start();
		include get_template_directory() . '/templates/profile.php';
		return (string) ob_get_clean();
	}

	public function test_REQ_003_person_page_has_one_h1_about_roles_and_only_sections_with_content(): void {
		$profile = $this->profile(
			'Pagetest Person',
			'individual',
			true,
			array(
				'bio'                 => 'A long biography.',
				'current_title'       => 'Chief Engineer',
				'public_record_roles' => array( 'Board member, 2019 to 2023' ),
			)
		);
		$out     = $this->render( $profile );
		$this->assertSame( 1, substr_count( $out, '<h1>' ) );
		$this->assertStringContainsString( 'Chief Engineer', $out );
		$this->assertStringContainsString( 'A long biography.', $out );
		$this->assertStringContainsString( 'Public record roles', $out );
		$this->assertStringContainsString( 'Board member, 2019 to 2023', $out );
		$this->assertStringNotContainsString( 'Mandate', $out );
		$this->assertStringNotContainsString( 'Achievements', $out, 'no awards, no section' );
		$this->assertStringContainsString( 'class="crumbs"', $out );
		$this->assertStringContainsString( '/people/', $out );
	}

	public function test_REQ_005_institution_shows_mandate_and_organisation_has_no_person_sections(): void {
		$inst = $this->profile(
			'Pagetest Institute',
			'institution',
			true,
			array(
				'summary' => 'An institute.',
				'mandate' => 'To research things.',
			)
		);
		$out  = $this->render( $inst );
		$this->assertStringContainsString( 'Mandate', $out );
		$this->assertStringContainsString( 'To research things.', $out );
		$this->assertStringContainsString( '/institutions/', $out );
		$org = $this->profile( 'Pagetest Business', 'organisation', true, array( 'summary' => 'A business.' ) );
		$this->assertStringNotContainsString( 'Mandate', $this->render( $org ) );
	}

	public function test_REQ_247_no_cover_banner_email_or_phone_and_links_are_plain(): void {
		$profile = $this->profile(
			'Pagetest Linked',
			'individual',
			true,
			array(
				'bio'              => 'Bio.',
				'official_website' => 'https://person.example/home',
			)
		);
		$out     = $this->render( $profile );
		foreach ( array( 'cover', 'banner', 'mailto:', 'tel:', 'profile-cover' ) as $word ) {
			$this->assertStringNotContainsString( $word, $out, $word );
		}
		$this->assertStringContainsString( 'href="https://person.example/home" rel="nofollow noopener"', $out );
		$this->assertStringNotContainsString( '<script', $out );
	}

	public function test_REQ_216_pending_profile_shows_the_label_and_correction_links_and_reviewed_does_not(): void {
		$pending  = $this->profile( 'Pagetest Pending', 'organisation', false, array( 'summary' => 'Compiled.' ) );
		$reviewed = $this->profile( 'Pagetest Reviewed', 'organisation', true, array( 'summary' => 'Checked.' ) );
		$out      = $this->render( $pending );
		$this->assertStringContainsString( StatePolicy::PENDING_LABEL, $out );
		$this->assertStringContainsString( 'notice--pending', $out );
		$this->assertStringContainsString( '/correct/' . $pending->slug . '/', $out );
		$this->assertStringNotContainsString( StatePolicy::PENDING_LABEL, $this->render( $reviewed ) );
	}

	public function test_REQ_082_the_rail_slot_exists_and_the_layout_is_two_columns(): void {
		$profile = $this->profile( 'Pagetest Rail', 'individual', true, array( 'bio' => 'Bio.' ) );
		add_action(
			'wiw_profile_rail',
			static function (): void {
				echo '<p id="rail-probe">rail</p>';
			}
		);
		$out = $this->render( $profile );
		remove_all_actions( 'wiw_profile_rail' );
		$this->assertStringContainsString( 'split split--rail', $out );
		$this->assertStringContainsString( 'id="rail-probe"', $out );
	}

	public function test_REQ_003_template_is_chosen_only_when_the_route_selected_a_profile(): void {
		set_query_var( 'wiw_profile_id', 0 );
		$this->assertSame( 'x.php', wiw_profile_template( 'x.php' ) );
		set_query_var( 'wiw_profile_id', 5 );
		$this->assertStringEndsWith( 'templates/profile.php', wiw_profile_template( 'x.php' ) );
		$this->assertSame( 'Person', wiw_profile_kind( 'individual' )['label'] );
	}
}
