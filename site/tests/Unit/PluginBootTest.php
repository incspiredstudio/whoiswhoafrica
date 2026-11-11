<?php
/**
 * The core plugin registers all nine module bootstraps (REQ-235).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Core\Platform\Plugin;
use WhoIsWho\Tests\TestCase;

final class PluginBootTest extends TestCase {

	public function test_REQ_235_plugin_boots_and_registers_all_module_bootstraps(): void {
		$registry = Plugin::build();
		$this->assertSame(
			array( 'platform', 'directory', 'review', 'import', 'trust', 'commerce', 'content', 'awards', 'compliance' ),
			$registry->module_names()
		);
		$registry->boot();
	}

	public function test_REQ_235_module_folders_exist_at_the_documented_paths(): void {
		foreach ( array( 'Directory', 'Review', 'Import', 'Trust', 'Commerce', 'Content', 'Awards', 'Compliance', 'Platform' ) as $module ) {
			$this->assertFileExists( dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/src/' . $module . '/Module.php' );
		}
	}

	public function test_REQ_235_registry_returns_services_and_rejects_unknown(): void {
		$registry = Plugin::build();
		$registry->set( 'thing', new \stdClass() );
		$this->assertInstanceOf( \stdClass::class, $registry->get( 'thing' ) );
		$this->expectException( \OutOfBoundsException::class );
		$registry->get( 'missing' );
	}
}
