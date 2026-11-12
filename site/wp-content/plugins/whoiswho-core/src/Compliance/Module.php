<?php
/**
 * Compliance module bootstrap. Registers this module's hooks in one place; domain classes arrive in later issues.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance;

use WhoIsWho\Core\Platform\ModuleInterface;

/**
 * Compliance module.
 */
final class Module implements ModuleInterface {

	/**
	 * Module name.
	 */
	public function name(): string {
		return 'compliance';
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		Support\EnquiryIntake::register();
		( new Legal\LegalPages() )->register();
	}
}
