<?php
/**
 * Base test case. Helpers for the role-by-capability matrix are added in M0-PLG-05.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests;

/**
 * Base class: plain PHPUnit for Unit and Arch, skip helper for tests that need WordPress.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {

	/**
	 * Skip the test when the WordPress test suite is not available.
	 */
	protected function requireWordPress(): void {
		if ( ! defined( 'WIW_WP_TESTS' ) || ! WIW_WP_TESTS ) {
			$this->markTestSkipped( 'Set WP_TESTS_DIR to run tests that need WordPress.' );
		}
	}
}
