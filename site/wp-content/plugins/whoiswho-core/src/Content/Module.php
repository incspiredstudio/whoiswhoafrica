<?php
/**
 * Content module bootstrap. Registers this module's hooks in one place; domain classes arrive in later issues.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content;

use WhoIsWho\Core\Platform\ModuleInterface;

/**
 * Content module.
 */
final class Module implements ModuleInterface {

	/**
	 * Module name.
	 */
	public function name(): string {
		return 'content';
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		Stories\StoryFields::hooks();
		Stories\StoryFlags::hooks();
		Stories\StoryRoute::hooks();
		( new Pages\PageRoutes() )->register();
		Stories\RelatedEvent::hooks();
		Events\EventPostType::hooks();
		Events\EventFields::hooks();
		( new Comments\CommentPolicy() )->register();
		( new Comments\CommentSpamGuard() )->register();
		Newsletter\NewsletterSignup::register();
		Curation\CurationCache::register();
		( new Newsletter\ConfirmRoute() )->register();
		( new Newsletter\SendGuard() )->register();
	}
}
