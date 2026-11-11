<?php
/**
 * Deactivating the plugin keeps data (REQ-056). Needs the WordPress test suite (WP_TESTS_DIR).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Activation;
use WhoIsWho\Tests\TestCase;

final class DeactivationTest extends TestCase {

	public function test_REQ_056_deactivation_keeps_data(): void {
		$this->requireWordPress();
		Activation::activate();
		Activation::deactivate();
		$this->assertSame( WIW_CORE_VERSION, get_option( 'wiw_core_version' ) );
	}
}
