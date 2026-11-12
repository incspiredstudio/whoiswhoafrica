<?php
/**
 * Profile key facts with sources, and the right rail with its actions (REQ-144, REQ-133, REQ-142, REQ-156, REQ-006). Needs the
 * WordPress test suite and the profile tables.
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
use WhoIsWho\Tests\TestCase;

final class ProfileFactsRailTest extends TestCase {

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
		remove_all_filters( 'wiw_profile_enquiries_open' );
		parent::tearDown();
	}

	/**
	 * Create a profile with sourced fields.
	 *
	 * @param string               $name     Name.
	 * @param string               $type     Entity type.
	 * @param bool                 $reviewed Reviewed (else pending).
	 * @param array<string, mixed> $fields   Fields (all get a source).
	 * @param string[]             $no_src   Field keys stored without a source.
	 */
	private function profile( string $name, string $type, bool $reviewed, array $fields, array $no_src = array() ): PublicProfile {
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
			if ( in_array( $key, $no_src, true ) ) {
				continue;
			}
			$prov->record(
				$id,
				array(
					'field'        => $key,
					'source_url'   => 'https://registry.example.org/entry/' . $key,
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
	 * Render the profile template.
	 *
	 * @param PublicProfile $profile Profile.
	 */
	private function render( PublicProfile $profile ): string {
		set_query_var( 'wiw_profile_id', $profile->id );
		ob_start();
		include get_template_directory() . '/templates/profile.php';
		return (string) ob_get_clean();
	}

	public function test_REQ_144_every_key_fact_shows_a_source_link_and_a_retrieval_date(): void {
		$profile = $this->profile(
			'Factstest Co',
			'organisation',
			true,
			array(
				'org_type'     => 'Company',
				'hq_city'      => 'Lagos',
				'year_founded' => 1999,
			)
		);
		$out     = $this->render( $profile );
		$this->assertStringContainsString( 'Key facts', $out );
		$this->assertStringContainsString( 'Lagos', $out );
		$this->assertStringContainsString( '1999', $out );
		$this->assertSame( 3, substr_count( $out, 'href="https://registry.example.org/entry/' ) );
		$this->assertSame( 3, substr_count( $out, 'datetime="2026-11-10"' ) );
		$this->assertStringContainsString( 'registry.example.org', $out );
	}

	public function test_REQ_133_a_fact_without_a_source_is_not_rendered(): void {
		$profile = $this->profile(
			'Factstest Unsourced',
			'organisation',
			true,
			array(
				'org_type' => 'Company',
				'hq_city'  => 'Lagos',
				'sector'   => 'SecretSector',
			),
			array( 'sector' )
		);
		$out     = $this->render( $profile );
		$this->assertStringContainsString( 'Lagos', $out );
		$this->assertStringNotContainsString( 'SecretSector', $out );
	}

	public function test_REQ_144_no_key_facts_block_when_there_are_no_sourced_facts(): void {
		$profile = $this->profile( 'Factstest Empty', 'individual', true, array() );
		$this->assertStringNotContainsString( 'Key facts', $this->render( $profile ) );
	}

	public function test_REQ_156_rail_has_the_correction_removal_and_report_actions(): void {
		$profile = $this->profile( 'Railtest One', 'organisation', true, array( 'org_type' => 'Company' ) );
		$out     = $this->render( $profile );
		$this->assertStringContainsString( 'Something wrong?', $out );
		$this->assertStringContainsString( '/correct/' . $profile->slug . '/', $out );
		$this->assertStringContainsString( '/remove/' . $profile->slug . '/', $out );
		$this->assertStringContainsString( '/report/' . $profile->slug . '/', $out );
		$this->assertStringContainsString( '/claim/' . $profile->slug . '/', $out );
	}

	public function test_REQ_156_claim_is_hidden_when_the_profile_is_pending_review_or_claimed(): void {
		$pending = $this->profile( 'Railtest Pending', 'organisation', false, array( 'org_type' => 'Company' ) );
		$this->assertFalse( $pending->claimable );
		$this->assertStringNotContainsString( '/claim/' . $pending->slug . '/', $this->render( $pending ) );
		$reviewed            = $this->profile( 'Railtest Claimed', 'organisation', true, array( 'org_type' => 'Company' ) );
		$reviewed->claimable = false;
		$reviewed->claimed   = true;
		$this->assertStringNotContainsString( '/claim/' . $reviewed->slug . '/', $this->renderPart( $reviewed ) );
	}

	public function test_REQ_142_contact_offers_an_enquiry_and_says_so_when_enquiries_are_off(): void {
		$profile = $this->profile( 'Railtest Contact', 'organisation', true, array( 'org_type' => 'Company' ) );
		$out     = $this->render( $profile );
		$this->assertStringContainsString( 'Send an enquiry', $out );
		$this->assertStringNotContainsString( 'mailto:', $out );
		add_filter( 'wiw_profile_enquiries_open', '__return_false' );
		$off = $this->render( $profile );
		$this->assertStringNotContainsString( 'Send an enquiry', $off );
		$this->assertStringContainsString( 'not accepting enquiries', $off );
	}

	public function test_REQ_006_the_share_preview_panel_is_not_rendered_on_the_live_page(): void {
		$profile = $this->profile( 'Railtest Share', 'organisation', true, array( 'org_type' => 'Company' ) );
		$out     = $this->render( $profile );
		$this->assertStringNotContainsString( 'When shared', $out );
		$this->assertStringNotContainsString( 'class="share"', $out );
	}

	public function test_REQ_156_related_stories_and_award_blocks_are_absent_without_data(): void {
		$profile = $this->profile( 'Railtest Related', 'organisation', true, array( 'org_type' => 'Company' ) );
		$out     = $this->render( $profile );
		$this->assertStringNotContainsString( 'Related stories', $out );
		$this->assertStringNotContainsString( 'prof-award', $out );
	}

	/**
	 * Render only the rail part for a profile object.
	 *
	 * @param PublicProfile $profile Profile.
	 */
	private function renderPart( PublicProfile $profile ): string {
		ob_start();
		get_template_part( 'template-parts/profile-rail', null, array( 'profile' => $profile ) );
		return (string) ob_get_clean();
	}
}
