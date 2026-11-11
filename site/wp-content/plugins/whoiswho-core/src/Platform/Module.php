<?php
/**
 * Platform module bootstrap. Registers this module's hooks in one place; domain classes arrive in later issues.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform;

use WhoIsWho\Core\Platform\ModuleInterface;

/**
 * Platform module.
 */
final class Module implements ModuleInterface {

	/**
	 * Module name.
	 */
	public function name(): string {
		return 'platform';
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		( Mail\Transport::for_site() )->register();
		Jobs\JobRunner::for_site()->register();
		add_action(
			'init',
			static function (): void {
				( new Capabilities\RoleSync( new Capabilities\CapabilityCatalogue() ) )->maybe_sync();
			}
		);
	}
}
