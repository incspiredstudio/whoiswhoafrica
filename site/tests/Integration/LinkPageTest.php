<?php
/**
 * The theme renders the single-use link page for the plugin's route (REQ-121, REQ-233). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class LinkPageTest extends TestCase {

	public function test_REQ_233_confirm_state_needs_a_post_button_and_never_prints_the_token(): void {
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
		set_query_var( 'wiw_link', 'sekret-token' );
		$out = apply_filters(
			'wiw_link_page',
			'default',
			array(
				'state'   => 'confirm',
				'message' => '',
			)
		);
		$this->assertStringContainsString( '<form method="post"', $out );
		$this->assertStringContainsString( '/link/sekret-token', $out );
		$this->assertStringNotContainsString( '>sekret-token<', $out );
	}

	public function test_REQ_233_spent_link_says_expired_or_already_used(): void {
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
		$out = apply_filters(
			'wiw_link_page',
			'default',
			array(
				'state'   => 'expired',
				'message' => 'x',
			)
		);
		$this->assertStringContainsString( 'expired or was already used', $out );
		$this->assertStringNotContainsString( '<form', $out );
	}
}
