<?php
/**
 * Contract for a module bootstrap.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform;

/**
 * A module registers its hooks in register() and nothing else.
 */
interface ModuleInterface {

	/**
	 * Short lowercase module name.
	 */
	public function name(): string;

	/**
	 * Register the module's hooks.
	 */
	public function register(): void;
}
