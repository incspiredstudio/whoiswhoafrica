<?php
/**
 * The claim page at /claim/{slug}: the form, the Already claimed page and the review-first message (REQ-251, REQ-252, REQ-058,
 * REQ-156). Needs the WordPress test suite and the profile tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ClaimPageTest extends TestCase {

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
		$this->reset_state();
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		$this->reset_state();
		parent::tearDown();
	}

	/**
	 * Clear the refused-form result and the claim query variable.
	 */
	private function reset_state(): void {
		wiw_form_result( array() );
		set_query_var( WIW_FORM_PAGE_VAR, '' );
		set_query_var( WIW_CLAIM_SLUG_VAR, '' );
	}

	/**
	 * Create a profile.
	 *
	 * @param string $name     Name.
	 * @param bool   $reviewed Reviewed (else pending review).
	 * @param bool   $claimed  Already claimed.
	 * @return string The profile slug.
	 */
	private function profile( string $name, bool $reviewed, bool $claimed = false ): string {
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
		return $post->post_name;
	}

	/**
	 * Render the claim template for a slug.
	 *
	 * @param string $slug Profile slug.
	 */
	private function render( string $slug ): string {
		set_query_var( WIW_FORM_PAGE_VAR, 'claim' );
		set_query_var( WIW_CLAIM_SLUG_VAR, $slug );
		ob_start();
		include get_template_directory() . '/templates/claim.php';
		return (string) ob_get_clean();
	}

	public function test_REQ_251_the_claim_route_is_registered_with_its_slug(): void {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$wp_rewrite->flush_rules( false );
		$rules = (array) $wp_rewrite->wp_rewrite_rules();
		$this->assertArrayHasKey( '^claim/([^/]+)/?$', $rules );
		$this->assertArrayNotHasKey( '^(contact|subscribe|done|claim)/?$', $rules );
		$wp_rewrite->set_permalink_structure( '' );
		$wp_rewrite->flush_rules( false );
	}

	public function test_REQ_251_claim_template_has_required_fields(): void {
		$slug = $this->profile( 'Claimable Ltd', true );
		$out  = $this->render( $slug );
		$this->assertSame( 1, substr_count( $out, '<h1' ) );
		$this->assertStringContainsString( 'Claim this profile', $out );
		$this->assertStringContainsString( 'method="post"', $out );
		$this->assertStringContainsString( 'action="' . home_url( '/claim/' . $slug . '/' ) . '"', $out );
		foreach ( array( 'name="relationship"', 'name="name"', 'name="role"', 'name="work_email"', 'name="phone"', 'name="evidence"', 'name="consent"', 'name="policy_version"', 'name="wiw_form_token"', 'name="wiw_website"' ) as $needle ) {
			$this->assertStringContainsString( $needle, $out, $needle );
		}
		$this->assertStringContainsString( 'value="self"', $out );
		$this->assertStringContainsString( 'value="representative"', $out );
		$this->assertStringContainsString( 'privacy-notice', $out );
		$this->assertStringContainsString( 'href="' . home_url( '/privacy/' ) . '"', $out );
		$this->assertStringContainsString( 'What happens next', $out );
		$this->assertStringContainsString( 'cannot be bought', $out );
		$this->assertStringNotContainsString( 'onsubmit', $out );
	}

	public function test_REQ_251_phone_is_the_only_optional_field_that_is_not_marked_required(): void {
		$slug = $this->profile( 'Required Fields Ltd', true );
		$out  = $this->render( $slug );
		$this->assertSame( 5, substr_count( $out, 'aria-required="true"' ), 'relationship, name, work email, evidence and consent' );
	}

	public function test_REQ_252_already_claimed_page_links_to_correction(): void {
		$slug = $this->profile( 'Claimed Ltd', true, true );
		$out  = $this->render( $slug );
		$this->assertStringContainsString( 'Already claimed', $out );
		$this->assertStringContainsString( 'href="' . home_url( '/correct/' . $slug . '/' ) . '"', $out );
		$this->assertStringNotContainsString( '<form', $out );
		$this->assertSame( 1, substr_count( $out, '<h1' ) );
	}

	public function test_REQ_252_a_profile_pending_review_shows_the_review_first_message_instead_of_the_form(): void {
		$slug = $this->profile( 'Pending Ltd', false );
		$out  = $this->render( $slug );
		$this->assertStringContainsString( 'Not yet open for claims', $out );
		$this->assertStringContainsString( 'has not been reviewed yet', $out );
		$this->assertStringContainsString( 'href="' . home_url( '/correct/' . $slug . '/' ) . '"', $out );
		$this->assertStringNotContainsString( '<form', $out );
	}

	public function test_REQ_156_an_unknown_slug_has_no_profile_so_the_page_is_a_404(): void {
		set_query_var( WIW_CLAIM_SLUG_VAR, 'no-such-profile' );
		$this->assertNull( wiw_claim_profile() );
		set_query_var( WIW_FORM_PAGE_VAR, 'claim' );
		$this->assertFalse( wiw_claim_is_servable() );
	}

	public function test_REQ_251_a_refused_claim_shows_errors_beside_the_fields_and_the_form_stays(): void {
		$slug = $this->profile( 'Refused Ltd', true );
		wiw_form_result(
			array(
				'form'    => 'claim',
				'status'  => 'invalid',
				'message' => 'Please check the highlighted fields.',
				'errors'  => array(
					'name'       => 'required',
					'work_email' => 'invalid_email',
					'consent'    => 'required',
				),
				'http'    => 422,
			)
		);
		$out = $this->render( $slug );
		$this->assertStringContainsString( 'role="alert"', $out );
		$this->assertStringContainsString( 'Please check the highlighted fields.', $out );
		$this->assertSame( 3, substr_count( $out, 'class="err"' ) );
		$this->assertStringContainsString( 'name="evidence"', $out );
	}

	public function test_REQ_081_a_throttled_claim_says_to_try_later(): void {
		$slug = $this->profile( 'Throttled Ltd', true );
		wiw_form_result(
			array(
				'form'    => 'claim',
				'status'  => 'try_later',
				'message' => 'Please try again later.',
				'errors'  => array(),
				'http'    => 429,
			)
		);
		$out = $this->render( $slug );
		$this->assertStringContainsString( 'Please try again later.', $out );
		$this->assertStringContainsString( 'name="evidence"', $out );
	}

	public function test_REQ_252_a_claim_refused_because_someone_else_just_claimed_shows_the_message_and_the_correction_link(): void {
		$slug = $this->profile( 'Race Ltd', true );
		wiw_form_result(
			array(
				'form'    => 'claim',
				'status'  => 'already_claimed',
				'message' => 'This profile has already been claimed.',
				'errors'  => array(),
				'http'    => 409,
			)
		);
		$out = $this->render( $slug );
		$this->assertStringContainsString( 'This profile has already been claimed.', $out );
		$this->assertStringContainsString( 'href="' . home_url( '/correct/' . $slug . '/' ) . '"', $out );
	}

	public function test_REQ_251_the_claim_page_is_not_indexed_and_the_title_names_the_profile(): void {
		$slug = $this->profile( 'Titled Ltd', true );
		set_query_var( WIW_FORM_PAGE_VAR, 'claim' );
		set_query_var( WIW_CLAIM_SLUG_VAR, $slug );
		$this->assertStringContainsString( 'Titled Ltd', apply_filters( 'pre_get_document_title', '' ) );
		ob_start();
		do_action( 'wp_head' );
		$head = (string) ob_get_clean();
		$this->assertStringContainsString( 'noindex', $head );
	}
}
