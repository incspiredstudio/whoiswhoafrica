<?php
/**
 * Proves the bootstrap runs and tells tests whether WordPress is available.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Tests\TestCase;

final class BootstrapTest extends TestCase {

	public function test_REQ_056_bootstrap_defines_the_wordpress_flag(): void {
		$this->assertTrue( defined( 'WIW_WP_TESTS' ) );
	}
}
