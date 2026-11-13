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
		( new Search\StoryIndexer() )->register();
		Stories\StoryRoute::hooks();
		( new Pages\PageRoutes() )->register();
		Stories\RelatedEvent::hooks();
		Stories\StorySeo::hooks();
		Events\EventPostType::hooks();
		Events\EventFields::hooks();
		( new Newsletter\Privacy\SubscriberPrivacy() )->register();
		Events\Admin\EventLifecycle::hooks();
		Events\Admin\EventListScreen::hooks();
		Events\EventCache::hooks();
		Events\EventCache::register_job();
		Events\EventSeo::hooks();
		( new Comments\CommentPolicy() )->register();
		Comments\CommentPrivacy::hooks();
		( new Comments\CommentModeratorNotice() )->register();
		Newsletter\NewsletterSignup::register();
		Curation\Admin\CurationScreen::hooks();
		Curation\CurationCache::register();
		( new Newsletter\ConfirmRoute() )->register();
		Newsletter\Admin\SubscribersScreen::hooks();
		( new Newsletter\SendGuard() )->register();
	}
}
