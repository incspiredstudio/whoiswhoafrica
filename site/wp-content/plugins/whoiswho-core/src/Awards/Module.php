<?php
/**
 * Awards module bootstrap. Registers this module's hooks in one place; domain classes arrive in later issues.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Platform\ModuleInterface;

/**
 * Awards module.
 */
final class Module implements ModuleInterface {

	/**
	 * Module name.
	 */
	public function name(): string {
		return 'awards';
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		require_once __DIR__ . '/functions.php';
		NominationsSwitch::hooks();
		( new AwardsRoutes() )->register();
		CategoriesScreen::hooks();
		AwardsStage::hooks();
		NominationIntake::register();
		NominationsScreen::hooks();
		Admin\AddProfileForm::hooks();
		NominationPrivacy::hooks();
	}
}
