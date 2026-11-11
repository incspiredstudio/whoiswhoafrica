<?php
/**
 * Plugin bootstrap: builds the registry with every module and boots it.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform;

/**
 * Entry point.
 */
final class Plugin {

	/**
	 * The module classes, in boot order.
	 */
	public const MODULES = array(
		\WhoIsWho\Core\Platform\Module::class,
		\WhoIsWho\Core\Directory\Module::class,
		\WhoIsWho\Core\Review\Module::class,
		\WhoIsWho\Core\Import\Module::class,
		\WhoIsWho\Core\Trust\Module::class,
		\WhoIsWho\Core\Commerce\Module::class,
		\WhoIsWho\Core\Content\Module::class,
		\WhoIsWho\Core\Awards\Module::class,
		\WhoIsWho\Core\Compliance\Module::class,
	);

	/**
	 * Build the registry with every module.
	 */
	public static function build(): Registry {
		$registry = new Registry();
		foreach ( self::MODULES as $class ) {
			$registry->add_module( new $class() );
		}
		return $registry;
	}

	/**
	 * Build and boot.
	 */
	public static function run(): void {
		$registry = self::build();
		$registry->boot();
	}
}
