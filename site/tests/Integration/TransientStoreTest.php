<?php
/**
 * The limiter works on the WordPress transient store, and no raw identifier reaches the database (REQ-122).
 * Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Forms\FormToken;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Core\Platform\Forms\TransientStore;
use WhoIsWho\Tests\WpTestCase;

final class TransientStoreTest extends WpTestCase {

	public function test_REQ_122_limiter_blocks_on_wordpress_transients_and_stores_no_raw_identifier(): void {
		global $wpdb;
		$limiter = new RateLimiter(
			new TransientStore(),
			'secret',
			array(
				'default' => array(
					'ip'      => array( 2, 600 ),
					'subject' => array( 2, 600 ),
				),
			)
		);
		$this->assertTrue( $limiter->allow( 'probe', '203.0.113.77', 'probe@example.org' ) );
		$this->assertTrue( $limiter->allow( 'probe', '203.0.113.77', 'probe@example.org' ) );
		$this->assertFalse( $limiter->allow( 'probe', '203.0.113.77', 'probe@example.org' ) );

		$rows = $wpdb->get_col( "SELECT CONCAT(option_name, option_value) FROM {$wpdb->options} WHERE option_name LIKE '%wiw_rl_%'" ); // phpcs:ignore WordPress.DB
		$this->assertNotEmpty( $rows );
		$this->assertStringNotContainsString( '203.0.113.77', implode( '', $rows ) );
		$this->assertStringNotContainsString( 'probe@example.org', implode( '', $rows ) );
	}

	public function test_REQ_081_site_form_token_round_trips(): void {
		$token = FormToken::for_site();
		$value = $token->issue( 'claim' );
		$this->assertSame( FormToken::TOO_FAST, $token->verify( $value, 'claim' ) );
		$this->assertSame( FormToken::OK, $token->verify( $value, 'claim', 0 ) );
	}
}
