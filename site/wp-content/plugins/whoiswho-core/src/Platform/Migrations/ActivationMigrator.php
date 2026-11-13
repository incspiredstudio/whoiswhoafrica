<?php
/**
 * Runs pending migrations when the plugin is activated, so a plain WordPress gets its tables without a command line (REQ-056).
 * The runner itself refuses in production (only the deploy script migrates there) and holds a lock, so this is safe to repeat.
 * It never throws: an activation must not fatal. Failures are reported by class only.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Migrations;

/**
 * Activation migrator.
 */
final class ActivationMigrator {

	/**
	 * Apply what is pending.
	 *
	 * @param Runner $runner Runner.
	 * @return array{applied: string[], error: string|null}
	 */
	public static function run( Runner $runner ): array {
		try {
			return $runner->run();
		} catch ( \Throwable $e ) {
			return array(
				'applied' => array(),
				'error'   => 'Migrations could not run (' . get_class( $e ) . ').',
			);
		}
	}
}
