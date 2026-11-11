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
		Links\LinkRoute::for_site()->register();
		Mail\Notifier::for_site();
		Log\Logger::for_site()->register();
		Mail\Admin\EmailLogScreen::hooks();
		Jobs\JobRunner::for_site()->recurring(
			'log.purge',
			86400,
			static function (): void {
				Log\FileSink::for_site()->purge();
			}
		);
		Jobs\JobRunner::for_site()->recurring(
			'links.purge_spent',
			86400,
			static function (): void {
				Links\SignedLinks::for_site()->purge_spent();
			}
		);
		add_action(
			'init',
			static function (): void {
				( new Capabilities\RoleSync( new Capabilities\CapabilityCatalogue() ) )->maybe_sync();
			}
		);
		Mail\Admin\EmailLogScreen::hooks();
		Capabilities\CapabilityAudit::for_site()->register();
	}
}
