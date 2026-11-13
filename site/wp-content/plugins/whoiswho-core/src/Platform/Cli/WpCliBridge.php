<?php
/**
 * Makes the plugin's commands available through WP-CLI as `wp wiw <command>` (for example `wp wiw migrate`, `wp wiw sample:seed`),
 * for sites that have no bin/wiw.php beside the plugin, such as a plain WordPress or WordPress Studio (REQ-056). The commands
 * and their production refusals are the same ones bin/wiw.php runs.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli;

/**
 * WP-CLI bridge.
 */
final class WpCliBridge {

	/**
	 * Register `wp wiw` when WP-CLI is running.
	 */
	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( '\WP_CLI' ) ) {
			return;
		}
		\WP_CLI::add_command(
			'wiw',
			static function ( array $args, array $assoc ): void {
				$code = Application::for_site()->run( self::argv( $args, $assoc ), new Output() );
				if ( 0 !== $code ) {
					\WP_CLI::halt( $code );
				}
			}
		);
	}

	/**
	 * The argv the application expects: script name, command, then flags.
	 *
	 * @param string[]             $args  Positional arguments from WP-CLI.
	 * @param array<string, mixed> $assoc Flags from WP-CLI.
	 * @return string[]
	 */
	public static function argv( array $args, array $assoc ): array {
		$argv = array_merge( array( 'wiw' ), array_map( 'strval', $args ) );
		foreach ( $assoc as $name => $value ) {
			$argv[] = true === $value ? '--' . $name : '--' . $name . '=' . ( is_scalar( $value ) ? (string) $value : '' );
		}
		return $argv;
	}
}
