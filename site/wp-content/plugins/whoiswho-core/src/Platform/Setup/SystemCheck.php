<?php
/**
 * What the server must have for the plugin to work, in plain language (REQ-056). Each problem says what is wrong and how to fix it,
 * for someone who does not write code. Reuses Requirements for the PHP and WordPress versions.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Setup;

use WhoIsWho\Core\Platform\Requirements;

/**
 * System check.
 */
final class SystemCheck {

	/**
	 * A name for the probe table the trigger test creates and drops.
	 */
	private const PROBE = 'wiw_setup_probe';

	/**
	 * Cheap checks, safe on every admin page: versions, extensions and the kind of database.
	 *
	 * @param string|null $php        PHP version (tests pass one).
	 * @param string|null $wp         WordPress version.
	 * @param string[]    $extensions Loaded extensions, or null for the server's.
	 * @param bool|null   $sqlite     Whether the database is SQLite, or null to detect.
	 * @return array<int, array{id: string, message: string, fix: string}>
	 */
	public static function problems( ?string $php = null, ?string $wp = null, ?array $extensions = null, ?bool $sqlite = null ): array {
		$php        = $php ?? PHP_VERSION;
		$wp         = $wp ?? (string) get_bloginfo( 'version' );
		$extensions = $extensions ?? array_map( 'strtolower', get_loaded_extensions() );
		$sqlite     = $sqlite ?? self::is_sqlite();
		$out        = array();
		if ( ! Requirements::met( $php, $wp ) ) {
			$out[] = array(
				'id'      => 'versions',
				'message' => sprintf(
					/* translators: 1: PHP version, 2: WordPress version, 3: needed PHP, 4: needed WordPress. */
					__( 'This server runs PHP %1$s and WordPress %2$s. The plugin needs PHP %3$s and WordPress %4$s or newer.', 'whoiswho' ),
					$php,
					$wp,
					Requirements::MIN_PHP,
					Requirements::MIN_WP
				),
				'fix'     => __( 'Ask your host to switch the site to a newer PHP version (most hosts have a PHP selector in their control panel), and update WordPress under Dashboard > Updates.', 'whoiswho' ),
			);
		}
		foreach ( array(
			'mbstring' => __( 'A standard part of the server software, the PHP "mbstring" extension, is switched off.', 'whoiswho' ),
			'json'     => __( 'A standard part of the server software, the PHP "json" extension, is switched off.', 'whoiswho' ),
		) as $extension => $message ) {
			if ( ! in_array( $extension, $extensions, true ) ) {
				$out[] = array(
					'id'      => 'ext-' . $extension,
					'message' => $message,
					'fix'     => __( 'In cPanel, open "Select PHP Version" (or "PHP Extensions") and tick it. Or ask your host to switch it on. Then reload this page.', 'whoiswho' ),
				);
			}
		}
		if ( $sqlite ) {
			$out[] = array(
				'id'      => 'sqlite',
				'message' => __( 'This site stores its data in a kind of database (SQLite) that the plugin cannot work with. It needs a MySQL or MariaDB database, which almost every web host provides.', 'whoiswho' ),
				'fix'     => __( 'Set the site up on a web host that gives you a MySQL or MariaDB database: cPanel hosts do, and their one-click WordPress installer uses one. Tools that run WordPress on SQLite, such as WordPress Studio and WordPress Playground, are not supported. Then install WordPress there and upload the plugin again.', 'whoiswho' ),
			);
		}
		return $out;
	}

	/**
	 * Whether the images for the demo can be made (the PHP "gd" extension).
	 */
	public static function has_gd(): bool {
		return function_exists( 'imagecreatetruecolor' );
	}

	/**
	 * Whether the site runs on SQLite (the "SQLite Database Integration" drop-in).
	 */
	public static function is_sqlite(): bool {
		global $wpdb;
		return defined( 'SQLITE_DB_DROPIN_VERSION' ) || ( is_object( $wpdb ) && false !== stripos( get_class( $wpdb ), 'sqlite' ) );
	}

	/**
	 * Try the database features the plugin needs: creating a table and a trigger on it. Cleans up after itself.
	 *
	 * @return array{id: string, message: string, fix: string}|null A problem, or null when it works.
	 */
	public static function probe_database(): ?array {
		global $wpdb;
		$table = $wpdb->prefix . self::PROBE;
		$show  = $wpdb->hide_errors();
		$ok    = false;
		try {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed probe table name.
			$ok = false !== $wpdb->query( "CREATE TABLE {$table} ( id INT NOT NULL ) ENGINE=InnoDB" ) // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- as above.
				&& false !== $wpdb->query( "CREATE TRIGGER {$table}_bi BEFORE INSERT ON {$table} FOR EACH ROW SET NEW.id = 1" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- as above.
		} finally {
			$wpdb->query( "DROP TRIGGER IF EXISTS {$table}_bi" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- as above.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- as above.
			$wpdb->show_errors( $show );
		}
		if ( $ok ) {
			return null;
		}
		return array(
			'id'      => 'triggers',
			'message' => __( 'The database did not allow the plugin to create the tables it needs.', 'whoiswho' ),
			'fix'     => __( 'In your host\'s control panel, open the database section (in cPanel: MySQL Databases), find the user this site uses and give it ALL PRIVILEGES on the site\'s database. Or ask your host to do it: say the database user needs permission to create tables and triggers. The database must be MySQL or MariaDB.', 'whoiswho' ),
		);
	}
}
