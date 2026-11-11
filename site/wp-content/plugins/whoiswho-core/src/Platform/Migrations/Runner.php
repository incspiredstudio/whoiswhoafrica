<?php
/**
 * Runs pending migrations in order, once, under a lock. Forward only.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Migrations;

/**
 * Migration runner.
 */
final class Runner {

	public const OPTION_VERSION = 'wiw_schema_version';
	public const OPTION_LOCK    = 'wiw_migration_lock';
	private const LOCK_TTL      = 600;

	/**
	 * Folder holding the migration files.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Whether the site is in production mode.
	 *
	 * @var bool
	 */
	private bool $production;

	/**
	 * Whether the deploy script started this run.
	 *
	 * @var bool
	 */
	private bool $deploy_flag;

	/**
	 * Constructor.
	 *
	 * @param string $dir         Migrations folder.
	 * @param bool   $production  Production mode.
	 * @param bool   $deploy_flag The deploy script's flag.
	 */
	public function __construct( string $dir, bool $production, bool $deploy_flag ) {
		$this->dir         = $dir;
		$this->production  = $production;
		$this->deploy_flag = $deploy_flag;
	}

	/**
	 * Runner for the plugin's own migrations folder and the current environment.
	 * The deploy script sets WIW_DEPLOY_MIGRATE=1 for its run.
	 */
	public static function for_site(): self {
		return new self(
			WIW_CORE_DIR . 'migrations',
			'production' === wp_get_environment_type(),
			'1' === getenv( 'WIW_DEPLOY_MIGRATE' )
		);
	}

	/**
	 * Current schema version, or "0" before any migration.
	 */
	public function current_version(): string {
		return (string) get_option( self::OPTION_VERSION, '0' );
	}

	/**
	 * Migrations found in the folder, ordered by version.
	 *
	 * @return MigrationInterface[]
	 */
	public function discover(): array {
		$found = array();
		$files = glob( $this->dir . '/*.php' );
		foreach ( false === $files ? array() : $files as $file ) {
			$migration = require $file;
			if ( $migration instanceof MigrationInterface ) {
				$found[ $migration->version() ] = $migration;
			}
		}
		ksort( $found, SORT_STRING );
		return array_values( $found );
	}

	/**
	 * Migrations not yet applied, in order.
	 *
	 * @return MigrationInterface[]
	 */
	public function pending(): array {
		$current = $this->current_version();
		return array_values( array_filter( $this->discover(), static fn ( MigrationInterface $m ): bool => strcmp( $m->version(), $current ) > 0 ) );
	}

	/**
	 * Contract (destructive) migrations, for CI to list.
	 *
	 * @return string[] Versions.
	 */
	public function contract_versions(): array {
		$out = array();
		foreach ( $this->discover() as $migration ) {
			if ( $migration->is_contract() ) {
				$out[] = $migration->version();
			}
		}
		return $out;
	}

	/**
	 * Apply pending migrations.
	 *
	 * @return array{applied: string[], error: string|null}
	 */
	public function run(): array {
		if ( $this->production && ! $this->deploy_flag ) {
			return array(
				'applied' => array(),
				'error'   => 'In production, migrations run only from the deploy script.',
			);
		}
		if ( ! $this->acquire_lock() ) {
			return array(
				'applied' => array(),
				'error'   => 'Another migration run is in progress.',
			);
		}

		$applied = array();
		$error   = null;
		try {
			$current = $this->current_version();
			foreach ( $this->discover() as $migration ) {
				if ( strcmp( $migration->version(), $current ) <= 0 ) {
					continue;
				}
				try {
					$migration->up();
				} catch ( \Throwable $e ) {
					// Report the class and version only: exception messages can carry personal data.
					$error = sprintf( 'Migration %s failed (%s).', $migration->version(), get_class( $e ) );
					break;
				}
				update_option( self::OPTION_VERSION, $migration->version(), false );
				$applied[] = $migration->version();
				$current   = $migration->version();
			}
		} finally {
			delete_option( self::OPTION_LOCK );
		}

		return array(
			'applied' => $applied,
			'error'   => $error,
		);
	}

	/**
	 * Take the lock. add_option is atomic, so two runs cannot both succeed; a stale lock expires.
	 */
	private function acquire_lock(): bool {
		$lock = get_option( self::OPTION_LOCK );
		if ( false !== $lock && (int) $lock < time() - self::LOCK_TTL ) {
			delete_option( self::OPTION_LOCK );
		}
		return add_option( self::OPTION_LOCK, (string) time(), '', false );
	}
}
