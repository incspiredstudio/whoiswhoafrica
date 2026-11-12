<?php
/**
 * Directory module bootstrap. Registers this module's hooks in one place; domain classes arrive in later issues.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory;

use WhoIsWho\Core\Platform\ModuleInterface;

/**
 * Directory module.
 */
final class Module implements ModuleInterface {

	/**
	 * Module name.
	 */
	public function name(): string {
		return 'directory';
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		( new Url\ProfileRoute() )->register();
		Admin\ProfilesScreen::hooks();
		( new Views\ViewCounter() )->register();
		( new Sitemap\Sitemap() )->register();
		Purge\PurgeQueue::for_site()->register();
		( new Search\SearchIndexer() )->register();
		add_filter( 'wiw_cli_commands', static fn ( $commands ): array => array_merge( is_array( $commands ) ? $commands : array(), array( new Search\SearchRebuildCommand() ) ) );
		add_filter(
			'wiw_public_profile_choices',
			static fn ( $choices ): array => ( is_array( $choices ) ? $choices : array() ) + PublicProfile::public_choices()
		);
		add_action(
			'init',
			static function (): void {
				( new ProfileType() )->register();
				( new Taxonomies() )->register();
				( new PlaceSeeder() )->maybe_seed();
				( new Fields\ProfileFields() )->register();
			},
			5
		);
	}
}
