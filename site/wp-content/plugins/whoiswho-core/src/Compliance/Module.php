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
		Consent\ListingConsent::register_gate();
		( new Consent\ListingConsent() )->register_links( \WhoIsWho\Core\Platform\Links\SignedLinks::for_site() );
		Support\EnquiryIntake::register();
		Deadlines\DeadlineTracker::register();
		Retention\RetentionJob::register();
		Privacy\PersonalData::hooks();
		Confirm\RequesterConfirmation::register();
		Admin\EnquiriesScreen::hooks();
		Admin\Requests\RequestsScreen::hooks();
		Admin\Suppression\SuppressionScreen::hooks();
		Forms\FormRoutes::register_for_site();
		( new Legal\LegalPages() )->register();
	}
}
