<?php
/**
 * The guard test: every personal-looking column in the real database schema is in the PII catalogue (REQ-063). It runs the real
 * migrations, then reads the column list from the database, so it does not depend on how a migration writes its table.
 * Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Tests\TestCase;

final class PiiCoverageTest extends TestCase {

	public function test_REQ_063_every_personal_column_is_in_the_pii_catalogue(): void {
		$this->requireWordPress();
		global $wpdb;

		$dir    = dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/migrations';
		$runner = new Runner( $dir, false, false );
		$runner->run();

		// Every column the migrations declare as personal is catalogued.
		$declared = array();
		foreach ( $runner->discover() as $migration ) {
			$declared = array_merge( $declared, $migration->pii_columns() );
		}
		$catalogue = new PiiCatalogue();
		$this->assertSame( array(), $catalogue->missing( $declared ), 'a migration declares a personal column the catalogue does not list' );

		// And every column of our own tables whose name looks personal is catalogued or allowlisted.
		$like = $wpdb->esc_like( $wpdb->prefix . 'wiw_' ) . '%';
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE %s', $like ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$this->assertNotEmpty( $rows, 'the migrations created our tables' );

		$columns = array();
		foreach ( $rows as $row ) {
			$table = substr( (string) $row['t'], strlen( $wpdb->prefix ) );
			if ( false !== strpos( $table, 'probe' ) ) {
				continue; // Scratch tables made by other tests.
			}
			$columns[] = $table . '.' . $row['c'];
		}
		$this->assertSame( array(), $catalogue->unclassified( $columns ), 'a personal-looking column is not in config/pii.php' );
	}
}
