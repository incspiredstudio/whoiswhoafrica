<?php
/**
 * The theme-facing wiw_nominations_open() helper (REQ-259, REQ-194). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\WpTestCase;

final class NominationsHelperTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		delete_option( SettingsService::OPTION_PREFIX . SettingsService::NOMINATIONS_ENABLED );
	}

	public function test_REQ_259_helper_returns_closed_when_flag_off(): void {
		$this->assertTrue( function_exists( 'wiw_nominations_open' ), 'the plugin registers the helper' );
		$this->assertFalse( wiw_nominations_open(), 'closed on a clean install' );
	}

	public function test_REQ_259_helper_returns_open_when_the_flag_is_on(): void {
		update_option( SettingsService::OPTION_PREFIX . SettingsService::NOMINATIONS_ENABLED, '1', false );
		$this->assertTrue( wiw_nominations_open() );
		update_option( SettingsService::OPTION_PREFIX . SettingsService::NOMINATIONS_ENABLED, '0', false );
		$this->assertFalse( wiw_nominations_open() );
	}
}
