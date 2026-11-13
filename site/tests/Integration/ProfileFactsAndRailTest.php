<?php
/**
 * Profile key facts, sources and right rail (REQ-144, REQ-133, REQ-142, REQ-156, REQ-006). Needs the WordPress test suite and the profile tables.
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

final class ProfileFactsAndRailTest extends TestCase {

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
		remove_all_filters( 'wiw_profile_award_panel' );
		parent::tearDown();
	}

	/**
	 * Create a profile. Fields named in $unsourced are stored but get no provenance.
	 *
	 * @param string               $name      Name.
	 * @param bool                 $reviewed  Reviewed (else pending).
	 * @param array<string, mixed> $fields    Sourced fields.
	 * @param array<string, mixed> $unsourced Fields without a source.
	 */
	private function profile( string $name, bool $reviewed, array $fields, array $unsourced = array() ): PublicProfile {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		$store = new ProfileFields();
		$prov  = new ProvenanceRepository();
		foreach ( $fields as $key => $value ) {
			$store->set( $id, $key, $value );
			$prov->record(
				$id,
				array(
					'field'        => $key,
					'source_url'   => 'https://www.example.org/registry/entry',
					'retrieved_at' => '2026-11-10T09:00:00Z',
					'confidence'   => 'high',
					'method'       => 'import',
				)
			);
		}
		foreach ( $unsourced as $key => $value ) {
			$store->set( $id, $key, $value );
		}
		if ( $reviewed ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		}
		$profile = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $profile );
		return $profile;
	}

	/**
	 * Render a template part.
	 *
	 * @param string          $part    Part.
	 * @param PublicProfile   $profile Profile.
	 */
	private function render( string $part, PublicProfile $profile ): string {
		ob_start();
		get_template_part( $part, null, array( 'profile' => $profile ) );
		return (string) ob_get_clean();
	}

	public function test_REQ_144_every_key_fact_shows_a_source_link_and_a_retrieval_date(): void {
		$profile = $this->profile(
			'Factstest Org',
			true,
			array(
				'org_type' => 'Company',
				'industry' => 'Energy',
				'hq_city'  => 'Lagos',
			)
		);
		$out     = $this->render( 'template-parts/profile-facts', $profile );
		$this->assertSame( 3, substr_count( $out, '<tr>' ) );
		$this->assertSame( 3, substr_count( $out, 'href="https://www.example.org/registry/entry"' ) );
		$this->assertSame( 3, substr_count( $out, '<time datetime="2026-11-10">' ) );
		$this->assertStringContainsString( 'example.org', $out );
		$this->assertStringContainsString( 'Energy', $out );
	}

	public function test_REQ_133_a_fact_without_a_source_is_not_rendered_and_no_facts_means_no_section(): void {
		$profile = $this->profile( 'Factstest Mixed Org', true, array( 'industry' => 'Energy' ), array( 'hq_city' => 'Secretville' ) );
		$out     = $this->render( 'template-parts/profile-facts', $profile );
		$this->assertStringContainsString( 'Energy', $out );
		$this->assertStringNotContainsString( 'Secretville', $out );
		$bare = $this->profile( 'Factstest Bare Org', true, array(), array( 'hq_city' => 'Secretville' ) );
		$this->assertSame( '', trim( $this->render( 'template-parts/profile-facts', $bare ) ) );
	}

	public function test_REQ_142_the_rail_links_the_four_actions_and_the_share_preview_is_not_rendered(): void {
		$profile = $this->profile( 'Railtest Org', true, array( 'industry' => 'Energy' ) );
		$out     = $this->render( 'template-parts/profile-rail', $profile );
		foreach ( array( 'claim', 'correct', 'remove', 'report' ) as $action ) {
			$this->assertStringContainsString( '/' . $action . '/' . $profile->slug . '/', $out, $action );
		}
		$this->assertStringNotContainsString( 'When shared', $out );
		$this->assertStringNotContainsString( 'mailto:', $out );
		$this->assertStringNotContainsString( 'tel:', $out );
	}

	public function test_REQ_156_the_claim_button_is_hidden_when_the_profile_is_pending_review(): void {
		$pending = $this->profile( 'Railtest Pending Org', false, array( 'industry' => 'Energy' ) );
		$out     = $this->render( 'template-parts/profile-rail', $pending );
		$this->assertStringNotContainsString( 'data-action="claim"', $out );
		$this->assertStringContainsString( 'data-action="correct"', $out );
		$this->assertStringContainsString( 'data-action="remove"', $out );
	}

	public function test_REQ_156_the_claim_button_is_hidden_when_the_profile_is_claimed(): void {
		global $wpdb;
		$profile = $this->profile( 'Railtest Claimed Org', true, array( 'industry' => 'Energy' ) );
		$wpdb->update( StateRepository::table(), array( 'claimed' => 1 ), array( 'profile_id' => $profile->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$claimed = PublicProfile::find_by_id( $profile->id );
		$this->assertNotNull( $claimed );
		$this->assertStringNotContainsString( 'data-action="claim"', $this->render( 'template-parts/profile-rail', $claimed ) );
		$this->assertStringContainsString( 'data-action="claim"', $this->render( 'template-parts/profile-rail', $profile ) );
	}

	public function test_REQ_142_the_contact_block_is_absent_until_the_enquiry_feature_prints_one(): void {
		$profile = $this->profile( 'Railtest Contact Org', true, array( 'industry' => 'Energy' ) );
		$this->assertStringNotContainsString( 'rail-contact', $this->render( 'template-parts/profile-rail', $profile ) );
		$print = static function (): void {
			echo '<a href="#enquiry">Send an enquiry</a>';
		};
		add_action( 'wiw_profile_contact', $print );
		$out = $this->render( 'template-parts/profile-rail', $profile );
		remove_action( 'wiw_profile_contact', $print );
		$this->assertStringContainsString( 'rail-contact', $out );
		$this->assertStringContainsString( 'Send an enquiry', $out );
	}
}
