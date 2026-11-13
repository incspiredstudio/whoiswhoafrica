<?php
/**
 * The theme draws the customer invitation page and the consent answers (REQ-013, REQ-175, REQ-233, REQ-141). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class InvitationPageTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
		set_query_var( 'wiw_invite', 'tok-1234567890123456789012' );
	}

	protected function tearDown(): void {
		set_query_var( 'wiw_invite', '' );
		parent::tearDown();
	}

	/**
	 * The page the filter returns for a state.
	 *
	 * @param string                $state  State.
	 * @param array<string, string> $errors Field errors.
	 * @param array<string, string> $values Values.
	 */
	private function page( string $state, array $errors = array(), array $values = array() ): string {
		return (string) apply_filters(
			'wiw_invitation_page',
			'default',
			array(
				'state'  => $state,
				'errors' => $errors,
				'values' => $values,
			)
		);
	}

	public function test_REQ_233_a_spent_link_says_expired_or_already_used_with_a_way_to_ask_for_a_new_one(): void {
		$out = $this->page( 'expired' );
		$this->assertStringContainsString( 'Link expired or already used', $out );
		$this->assertStringContainsString( home_url( '/contact/' ), $out );
		$this->assertStringContainsString( 'Ask the team for a new link', $out );
		$this->assertStringNotContainsString( 'name="summary"', $out );
	}

	public function test_REQ_013_the_form_holds_the_draft_has_no_price_field_and_keeps_the_token_out_of_the_text(): void {
		$out = $this->page(
			'form',
			array(),
			array(
				'name'    => 'Obi Holdings',
				'summary' => 'We build roads.',
			)
		);
		$this->assertStringContainsString( 'value="Obi Holdings"', $out );
		$this->assertStringContainsString( 'We build roads.', $out );
		$this->assertStringContainsString( 'enctype="multipart/form-data"', $out );
		$this->assertStringContainsString( '/invite/tok-1234567890123456789012', $out );
		$this->assertStringNotContainsString( '>tok-1234567890123456789012<', $out );
		foreach ( array( 'name="name"', 'name="type"', 'name="summary"', 'name="website"', 'name="image"', 'value="draft"', 'value="send"' ) as $needle ) {
			$this->assertStringContainsString( $needle, $out, $needle );
		}
		$this->assertDoesNotMatchRegularExpression( '/price|package|checkout|payment/i', $out );
	}

	public function test_REQ_141_free_text_with_personal_details_shows_a_field_message(): void {
		$out = $this->page( 'invalid', array( 'summary' => 'remove_personal_text' ), array( 'summary' => 'Call 0803 000 0000' ) );
		$this->assertStringContainsString( 'role="alert"', $out );
		$this->assertStringContainsString( 'Please remove personal details such as phone numbers', $out );
		$this->assertStringContainsString( 'aria-invalid="true"', $out );
	}

	public function test_REQ_013_picture_feedback_names_the_type_and_size_problems(): void {
		$big = $this->page( 'invalid', array( 'image' => 'too_big' ) );
		$this->assertStringContainsString( 'up to 2 MB', $big );
		$this->assertStringContainsString( 'f-image-err', $big );
		$type = $this->page( 'invalid', array( 'image' => 'wrong_type' ) );
		$this->assertStringContainsString( 'JPEG, PNG or WebP', $type );
	}

	public function test_REQ_013_saved_and_done_states_confirm_and_say_nothing_is_published_until_review(): void {
		$this->assertStringContainsString( 'Your draft is saved', $this->page( 'saved' ) );
		$done = $this->page( 'done' );
		$this->assertStringContainsString( 'Nothing is published until a reviewer approves it', $done );
		$this->assertStringNotContainsString( '<form method="post" enctype', $done );
	}

	public function test_REQ_233_the_page_is_not_indexed(): void {
		set_query_var( 'wiw_invite', 'tok-1234567890123456789012' );
		ob_start();
		do_action( 'wp_head' );
		$head = (string) ob_get_clean();
		$this->assertStringContainsString( 'noindex', $head );
	}

	public function test_REQ_175_consent_done_pages_say_the_profile_is_not_public_until_a_reviewer_approves(): void {
		set_query_var( 'wiw_link', 'tok' );
		$accept = (string) apply_filters(
			'wiw_link_page',
			'default',
			array(
				'state'   => 'done',
				'message' => 'Thank you. Your consent is recorded.',
			)
		);
		$this->assertStringContainsString( 'Consent recorded', $accept );
		$this->assertStringContainsString( 'not public until a reviewer approves it', $accept );
		$decline = (string) apply_filters(
			'wiw_link_page',
			'default',
			array(
				'state'   => 'done',
				'message' => 'Your choice is recorded. The profile will not be published.',
			)
		);
		$this->assertStringContainsString( 'will not be published', $decline );
		set_query_var( 'wiw_link', '' );
	}
}
