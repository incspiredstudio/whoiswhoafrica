<?php
/**
 * The profile enquiry form, its rail panel and the switched-off state (REQ-018, REQ-081, REQ-122). Needs the WordPress test suite
 * and the profile tables.
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

final class ProfileEnquiryPageTest extends TestCase {

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
		wiw_form_result( array() );
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
		set_query_var( WIW_ENQUIRY_SLUG_VAR, '' );
		wiw_form_result( array() );
		parent::tearDown();
	}

	/**
	 * Create a reviewed profile.
	 *
	 * @param string $name        Name.
	 * @param bool   $enquiry_off Whether enquiries are switched off.
	 */
	private function profile( string $name, bool $enquiry_off = false ): PublicProfile {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'individual' ) );
		( new ProfileFields() )->set( $id, 'bio', 'A biography.' );
		( new ProvenanceRepository() )->record(
			$id,
			array(
				'field'        => 'bio',
				'source_url'   => 'https://example.org/src',
				'retrieved_at' => '2026-11-10T09:00:00Z',
				'confidence'   => 'high',
				'method'       => 'import',
			)
		);
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		if ( $enquiry_off ) {
			update_post_meta( $id, PublicProfile::ENQUIRIES_OFF_META, '1' );
		}
		$profile = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $profile );
		return $profile;
	}

	/**
	 * Render the profile page.
	 *
	 * @param PublicProfile $profile Profile.
	 */
	private function render_profile( PublicProfile $profile ): string {
		set_query_var( 'wiw_profile_id', $profile->id );
		ob_start();
		include get_template_directory() . '/templates/profile.php';
		return (string) ob_get_clean();
	}

	/**
	 * Render the enquiry page.
	 *
	 * @param PublicProfile $profile Profile.
	 */
	private function render_enquiry( PublicProfile $profile ): string {
		set_query_var( WIW_ENQUIRY_SLUG_VAR, $profile->slug );
		ob_start();
		include get_template_directory() . '/templates/enquiry.php';
		return (string) ob_get_clean();
	}

	public function test_REQ_018_profile_enquiry_markup_has_no_owner_contact(): void {
		$profile = $this->profile( 'Enquiry Person' );
		foreach ( array( $this->render_profile( $profile ), $this->render_enquiry( $profile ) ) as $out ) {
			$this->assertStringNotContainsString( 'mailto:', $out );
			$this->assertStringNotContainsString( 'tel:', $out );
		}
	}

	public function test_REQ_018_the_rail_offers_the_enquiry_page_when_enquiries_are_open(): void {
		$profile = $this->profile( 'Enquiry Open' );
		$out     = $this->render_profile( $profile );
		$this->assertStringContainsString( '/enquire/' . $profile->slug . '/', $out );
		$this->assertStringContainsString( 'Send an enquiry', $out );
	}

	public function test_REQ_081_the_rail_says_enquiries_are_off_and_offers_no_button(): void {
		$profile = $this->profile( 'Enquiry Off', true );
		$out     = $this->render_profile( $profile );
		$this->assertStringContainsString( 'Enquiries are switched off for this profile', $out );
		$this->assertStringNotContainsString( '/enquire/', $out );
	}

	public function test_REQ_018_the_enquiry_page_has_name_email_message_token_and_posts_to_the_relay(): void {
		$profile = $this->profile( 'Enquiry Form' );
		$out     = $this->render_enquiry( $profile );
		$this->assertStringContainsString( 'action="' . esc_url( home_url( '/enquire/' . $profile->slug . '/' ) ) . '"', $out );
		foreach ( array( 'name', 'email', 'message', 'wiw_form_token', 'wiw_website' ) as $field ) {
			$this->assertStringContainsString( 'name="' . $field . '"', $out );
		}
		$this->assertSame( 1, substr_count( $out, '<h1' ) );
		$this->assertStringContainsString( 'never shown', $out );
	}

	public function test_REQ_122_the_enquiry_page_shows_the_notice_and_no_form_when_enquiries_are_off(): void {
		$profile = $this->profile( 'Enquiry Page Off', true );
		$out     = $this->render_enquiry( $profile );
		$this->assertStringContainsString( 'switched off', $out );
		$this->assertStringNotContainsString( '<form', $out );
	}

	public function test_REQ_122_a_rate_limited_submission_shows_the_try_later_message_and_the_form(): void {
		$profile = $this->profile( 'Enquiry Limited' );
		wiw_form_result(
			array(
				'status'  => 'try_later',
				'message' => 'Please try again later.',
				'errors'  => array(),
				'http'    => 429,
			)
		);
		$out = $this->render_enquiry( $profile );
		$this->assertStringContainsString( 'role="alert"', $out );
		$this->assertStringContainsString( 'Please try again later.', $out );
		$this->assertStringNotContainsString( 'Enquiry received', $out );
	}

	public function test_REQ_018_an_unknown_slug_is_not_servable(): void {
		set_query_var( WIW_ENQUIRY_SLUG_VAR, 'no-such-profile-here' );
		$this->assertFalse( wiw_enquiry_is_servable() );
		$profile = $this->profile( 'Enquiry Known' );
		set_query_var( WIW_ENQUIRY_SLUG_VAR, $profile->slug );
		$this->assertTrue( wiw_enquiry_is_servable() );
	}
}
