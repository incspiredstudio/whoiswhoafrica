<?php
/**
 * Profile card and pending-review label (REQ-216, REQ-155, REQ-247). Needs the WordPress test suite and the profile tables.
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

final class ProfileCardTest extends TestCase {

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
	private function profile( string $name, string $type, bool $reviewed, array $fields = array() ): PublicProfile {
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
	 * Render a template part.
	 *
	 * @param string               $part Part.
	 * @param array<string, mixed> $args Args.
	 */
	private function render( string $part, array $args ): string {
		ob_start();
		get_template_part( $part, null, $args );
		return (string) ob_get_clean();
	}

	public function test_REQ_216_pending_card_shows_the_label_and_a_reviewed_card_never_does(): void {
		$pending  = $this->profile( 'Cardtest Pending Org', 'organisation', false, array( 'industry' => 'Logistics' ) );
		$reviewed = $this->profile( 'Cardtest Reviewed Org', 'organisation', true, array( 'industry' => 'Logistics' ) );
		$pend_out = $this->render( 'template-parts/profile-card', array( 'profile' => $pending ) );
		$rev_out  = $this->render( 'template-parts/profile-card', array( 'profile' => $reviewed ) );
		$this->assertStringContainsString( 'badge--pending', $pend_out );
		$this->assertStringContainsString( StatePolicy::PENDING_LABEL, $pend_out );
		$this->assertStringNotContainsString( 'badge--pending', $rev_out );
		$this->assertStringNotContainsString( StatePolicy::PENDING_LABEL, $rev_out );
	}

	public function test_REQ_155_card_has_no_trust_or_sponsored_mark_and_no_cover(): void {
		$profile = $this->profile( 'Cardtest Plain Org', 'organisation', true, array( 'industry' => 'Energy' ) );
		$out     = $this->render( 'template-parts/profile-card', array( 'profile' => $profile ) );
		foreach ( array( 'Verified', 'Featured', 'Sponsored', 'Claimed', 'cover', 'banner' ) as $word ) {
			$this->assertStringNotContainsStringIgnoringCase( $word, $out, $word );
		}
		$this->assertStringContainsString( 'Energy', $out );
		$this->assertStringContainsString( 'class="stretched" href="' . esc_url( $profile->url ) . '"', $out );
		$this->assertStringContainsString( 'poster-ini', $out, 'No image: the monogram shows.' );
	}

	public function test_REQ_216_pending_notice_variant_has_correction_links_and_empty_label_renders_nothing(): void {
		$out = $this->render(
			'template-parts/pending-label',
			array(
				'label'       => 'Pending text',
				'variant'     => 'notice',
				'correct_url' => '/correct/x/',
				'remove_url'  => '/remove/x/',
			)
		);
		$this->assertStringContainsString( 'notice--pending', $out );
		$this->assertStringContainsString( 'href="/correct/x/"', $out );
		$this->assertSame( '', trim( $this->render( 'template-parts/pending-label', array( 'label' => null ) ) ) );
	}

	public function test_REQ_155_card_without_a_profile_renders_nothing(): void {
		$this->assertSame( '', trim( $this->render( 'template-parts/profile-card', array() ) ) );
	}

	public function test_REQ_155_initials_and_poster_style_are_stable(): void {
		$this->assertSame( 'AO', wiw_initials( 'Dr. Ada Okafor' ) );
		$this->assertSame( wiw_poster_style( 'same-slug' ), wiw_poster_style( 'same-slug' ) );
		$this->assertContains( wiw_poster_style( 'x' )['tone'], array( 'blue', 'purple', 'green', 'red', 'orange' ) );
	}
}
