<?php
/**
 * The plugin refuses to run on too old a PHP or WordPress (REQ-235).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Core\Platform\Requirements;
use WhoIsWho\Tests\TestCase;

final class RequirementsTest extends TestCase {

	public function test_REQ_235_old_php_is_refused(): void {
		$this->assertFalse( Requirements::met( '8.2.20', '6.6' ) );
	}

	public function test_REQ_235_old_wordpress_is_refused(): void {
		$this->assertFalse( Requirements::met( '8.3.1', '6.4.3' ) );
	}

	public function test_REQ_235_current_versions_pass(): void {
		$this->assertTrue( Requirements::met( '8.3.0', '6.5' ) );
		$this->assertTrue( Requirements::met( PHP_VERSION, '7.1' ) );
	}
}
