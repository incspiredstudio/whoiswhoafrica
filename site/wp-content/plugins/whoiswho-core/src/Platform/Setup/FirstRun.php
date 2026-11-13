<?php
/**
 * First-run setup (REQ-056): what happens when the plugin is activated, with no wp-config.php edit and no command line.
 *
 * A fresh install (no schema, no tables) creates its tables through the locked runner in any environment, because there is nothing
 * to protect. Once the schema exists, production keeps its rule: migrations run only from the deploy script. Either way the
 * activation sets pretty permalinks, makes the legal pages and sends the administrator to the Setup screen once. It never throws,
 * and when something prevents the setup it stores a plain-language reason for the Setup screen and an admin notice.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Setup;

use WhoIsWho\Core\Compliance\Legal\LegalPages;
use WhoIsWho\Core\Platform\Migrations\ActivationMigrator;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Permalinks;

/**
 * First run.
 */
final class FirstRun {

	public const REDIRECT = 'wiw_setup_redirect';

	/**
	 * Set up the site.
	 *
	 * @param string|null                                                      $dir        Migrations folder (tests pass one); the plugin's by default.
	 * @param bool|null                                                        $production Whether the site is in production; the site's environment by default.
	 * @param bool|null                                                        $fresh      Whether this is a first install (tests pass it); detected by default.
	 * @param array<int, array{id: string, message: string, fix: string}>|null $problems Blocking problems (tests pass them); checked by default.
	 * @return array{mode: string, applied: string[], error: string}
	 */
	public static function activate( ?string $dir = null, ?bool $production = null, ?bool $fresh = null, ?array $problems = null ): array {
		$dir        = $dir ?? WIW_CORE_DIR . 'migrations';
		$production = $production ?? ( 'production' === wp_get_environment_type() );
		$mode       = 'upgrade';
		$result     = array(
			'applied' => array(),
			'error'   => null,
		);
		if ( $fresh ?? SchemaState::may_self_initialise() ) {
			$mode     = 'fresh';
			$problems = $problems ?? self::blocking_problems();
			if ( array() !== $problems ) {
				$result['error'] = $problems[0]['message'] . ' ' . $problems[0]['fix'];
			} else {
				update_option( SchemaState::OPTION_STARTED, '1', false );
				$result = ActivationMigrator::run( new Runner( $dir, false, false ) );
				if ( null === $result['error'] && array() === ( new Runner( $dir, false, false ) )->pending() ) {
					delete_option( SchemaState::OPTION_STARTED );
				}
			}
		} else {
			$deploy = '1' === getenv( 'WIW_DEPLOY_MIGRATE' );
			$result = ActivationMigrator::run( new Runner( $dir, $production, $deploy ) );
			if ( $production && null !== $result['error'] ) {
				$result['error'] = null;
				// Production upgrades are the deploy script's job; this is the normal answer, not a fault.
			}
		}
		if ( null === $result['error'] ) {
			delete_option( SchemaState::OPTION_ERROR );
			Permalinks::ensure_pretty();
			( new LegalPages() )->ensure();
		} else {
			update_option( SchemaState::OPTION_ERROR, (string) $result['error'], false );
			Permalinks::ensure_pretty();
		}
		update_option( self::REDIRECT, '1', false );
		return array(
			'mode'    => $mode,
			'applied' => $result['applied'],
			'error'   => (string) $result['error'],
		);
	}

	/**
	 * Problems that stop the tables from being made: the cheap checks and the database probe.
	 *
	 * @return array<int, array{id: string, message: string, fix: string}>
	 */
	public static function blocking_problems(): array {
		$problems = SystemCheck::problems();
		if ( array() === $problems ) {
			$probe = SystemCheck::probe_database();
			if ( null !== $probe ) {
				$problems[] = $probe;
			}
		}
		return $problems;
	}

	/**
	 * Run the database setup now (the Setup screen's button). Allowed on a fresh or half-finished first install in any environment,
	 * and on any non-production site; never on a production site that already has its schema.
	 *
	 * @return array{ok: bool, applied: string[], error: string}
	 */
	public static function run_database_setup(): array {
		$production = 'production' === wp_get_environment_type();
		if ( $production && ! SchemaState::may_self_initialise() ) {
			return array(
				'ok'      => false,
				'applied' => array(),
				'error'   => __( 'This is a live site that already has its database. Database changes there are applied only by the deploy script.', 'whoiswho' ),
			);
		}
		$problems = self::blocking_problems();
		if ( array() !== $problems ) {
			update_option( SchemaState::OPTION_ERROR, $problems[0]['message'] . ' ' . $problems[0]['fix'], false );
			return array(
				'ok'      => false,
				'applied' => array(),
				'error'   => $problems[0]['message'] . ' ' . $problems[0]['fix'],
			);
		}
		$runner = new Runner( WIW_CORE_DIR . 'migrations', false, false );
		if ( SchemaState::may_self_initialise() ) {
			update_option( SchemaState::OPTION_STARTED, '1', false );
		}
		$result = ActivationMigrator::run( $runner );
		if ( null === $result['error'] ) {
			delete_option( SchemaState::OPTION_ERROR );
			if ( array() === $runner->pending() ) {
				delete_option( SchemaState::OPTION_STARTED );
			}
			( new LegalPages() )->ensure();
			return array(
				'ok'      => true,
				'applied' => $result['applied'],
				'error'   => '',
			);
		}
		update_option( SchemaState::OPTION_ERROR, (string) $result['error'], false );
		return array(
			'ok'      => false,
			'applied' => $result['applied'],
			'error'   => (string) $result['error'],
		);
	}
}
