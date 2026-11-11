<?php
/**
 * Minimum PHP and WordPress versions for the plugin.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform;

/**
 * Version requirements.
 */
final class Requirements {

	public const MIN_PHP = '8.3';
	public const MIN_WP  = '6.5';

	/**
	 * Whether the given versions meet the minimums.
	 *
	 * @param string $php PHP version.
	 * @param string $wp  WordPress version.
	 */
	public static function met( string $php, string $wp ): bool {
		return version_compare( $php, self::MIN_PHP, '>=' ) && version_compare( $wp, self::MIN_WP, '>=' );
	}
}
