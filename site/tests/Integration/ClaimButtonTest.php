<?php
/**
 * The Claim this profile button in the profile right rail (M3-CLM-12; REQ-156, REQ-252, REQ-226): shown only when the read model
 * says the profile can be claimed, hidden for a claimed profile and for a profile still pending review, and never any Claimed
 * wording in public markup. Needs the WordPress test suite and the profile tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ClaimButtonTest extends TestCase {

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
	 * Create a profile and return the read model.
	 *
	 * @param string $name     Name.
	 * @param bool   $reviewed Reviewed (else pending review).
	 * @param bool   $claimed  Already claimed.
	 */
	private function profile( string $name, bool $reviewed, bool $claimed = false ): PublicProfile {
		$id            = (int) wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => $name,
				'post_status' => 'publish',
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		if ( $reviewed ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		}
		if ( $claimed ) {
			( new StateRepository() )->update( $id, array( 'claimed' => 1 ) );
		}
		$post = get_post( $id );
		$this->assertInstanceOf( \WP_Post::class, $post );
		$profile = PublicProfile::find( $post->post_name );
		$this->assertInstanceOf( PublicProfile::class, $profile );
		return $profile;
	}

	/**
	 * Render the whole right rail for a profile.
	 *
	 * @param PublicProfile $profile Profile.
	 */
	private function rail( PublicProfile $profile ): string {
		ob_start();
		do_action( 'wiw_profile_rail', $profile );
		return (string) ob_get_clean();
	}

	public function test_REQ_156_a_reviewed_unclaimed_profile_shows_the_claim_button(): void {
		$profile = $this->profile( 'Open Ltd', true );
		$out     = $this->rail( $profile );
		$this->assertStringContainsString( 'Claim this profile', $out );
		$this->assertStringContainsString( 'href="' . home_url( '/claim/' . $profile->slug . '/' ) . '"', $out );
	}

	public function test_REQ_252_claimed_profile_has_no_claim_button(): void {
		$out = $this->rail( $this->profile( 'Taken Ltd', true, true ) );
		$this->assertStringNotContainsString( 'Claim this profile', $out );
		$this->assertStringNotContainsString( '/claim/', $out );
		$this->assertStringNotContainsStringIgnoringCase( 'claimed', $out );
	}

	public function test_REQ_226_pending_review_profile_does_not_offer_the_claim(): void {
		$out = $this->rail( $this->profile( 'Pending Ltd', false ) );
		$this->assertStringNotContainsString( 'Claim this profile', $out );
		$this->assertStringNotContainsString( '/claim/', $out );
	}

	public function test_REQ_252_public_templates_never_print_claimed_wording(): void {
		$root = dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho';
		$hits = array();
		foreach ( array( 'templates', 'template-parts' ) as $dir ) {
			foreach ( glob( $root . '/' . $dir . '/*.php' ) ?: array() as $file ) {
				$text = (string) file_get_contents( $file );
				// Visible wording only: a "Claimed" badge or label, not the helper names that decide whether to hide the button.
				if ( preg_match( '/(?<!-)>\s*(?:<[^>]+>\s*)*Claimed\b|esc_html_e\(\s*\'Claimed\'|class="[^"]*\bclaimed\b/i', $text ) && 'claim.php' !== basename( $file ) ) {
					$hits[] = basename( $file );
				}
			}
		}
		$this->assertSame( array(), $hits );
	}
}
