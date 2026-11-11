<?php
/**
 * Base for tests that need WordPress factories and query helpers (go_to, factory). Without the WordPress test suite the
 * class still loads and every test in it is skipped, so plain runs never fail.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.OneObjectStructurePerFile,Generic.Classes.DuplicateClassName,Universal.Files.SeparateFunctionsFromOO -- two alternative definitions of one class.

namespace WhoIsWho\Tests;

if ( class_exists( '\WP_UnitTestCase' ) ) {
	/**
	 * With WordPress.
	 */
	abstract class WpTestCase extends \WP_UnitTestCase {
	}
} else {
	/**
	 * Without WordPress: skip.
	 */
	abstract class WpTestCase extends TestCase {

		/**
		 * Skip every test.
		 */
		protected function setUp(): void {
			parent::setUp();
			$this->requireWordPress();
		}
	}
}
