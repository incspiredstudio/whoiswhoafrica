<?php
/**
 * Awards boundary tests (REQ-199, REQ-183, REQ-194): no awards table, list or export carries a commercial field, and the awards
 * capabilities sit where the matrix says. The rule that the Awards code never references Commerce lives in the architecture
 * suite (AwardsNeverReferencesCommerce). These tests read the code and config only and need no database.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit\Awards;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\NominationExport;
use WhoIsWho\Tests\TestCase;

final class AwardsBoundaryTest extends TestCase {

	/**
	 * Words that mark a commercial field. None may appear as a column or export header of the awards data.
	 */
	private const COMMERCIAL = array( 'price', 'amount', 'kobo', 'currency', 'payment', 'paid', 'invoice', 'subscription', 'sponsor', 'customer', 'deal', 'arrangement', 'revenue', 'refund' );

	/**
	 * Column names of a table definition.
	 *
	 * @param string $definition Definition from AwardsTables.
	 * @return string[]
	 */
	private function columns( string $definition ): array {
		$columns = array();
		foreach ( explode( "\n", $definition ) as $line ) {
			if ( 1 === preg_match( '/^\s*([a-z_]+)\s+(bigint|varchar|text|datetime|date|smallint|int|tinyint|enum|char)/i', $line, $match ) ) {
				$columns[] = $match[1];
			}
		}
		return $columns;
	}

	public function test_REQ_183_no_awards_screen_or_export_shows_commercial_fields(): void {
		foreach ( AwardsTables::definitions() as $table => $definition ) {
			$columns = $this->columns( $definition );
			$this->assertNotEmpty( $columns, $table . ' has columns' );
			foreach ( $columns as $column ) {
				foreach ( self::COMMERCIAL as $word ) {
					$this->assertStringNotContainsString( $word, $column, $table . '.' . $column . ' looks commercial' );
				}
			}
		}
		foreach ( array_keys( NominationExport::COLUMNS ) as $header ) {
			foreach ( self::COMMERCIAL as $word ) {
				$this->assertStringNotContainsString( $word, strtolower( $header ), 'the export column "' . $header . '" looks commercial' );
			}
		}
		$this->assertNotContains( 'contact_email', array_values( NominationExport::COLUMNS ), 'and never the Contact email' );
	}

	public function test_REQ_199_arrangement_changes_do_not_change_nomination_status(): void {
		if ( ! class_exists( 'WhoIsWho\\Core\\Commerce\\Module' ) && ! is_dir( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/src/Commerce' ) ) {
			$this->markTestSkipped( 'Commerce is not built yet (M3-COM-01, issue #199). Until it exists nothing can change a nomination from there; the architecture rule AwardsNeverReferencesCommerce and the empty Commerce directory are the guard.' );
		}
		// With Commerce present: no Commerce file may name a nomination table, the nomination service or a nomination status.
		$found = array();
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/src/Commerce', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			if ( 'php' === $file->getExtension() && 1 === preg_match( '/wiw_nominations|NominationService|Awards\\\\/i', (string) file_get_contents( $file->getPathname() ) ) ) {
				$found[] = $file->getFilename();
			}
		}
		$this->assertSame( array(), $found, 'Commerce never touches a nomination' );
	}

	public function test_REQ_194_awards_capability_matrix(): void {
		$config = require dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/config/capabilities.php';
		$caps   = $config['capabilities'] ?? $config;
		$roles  = static fn ( string $cap ): array => $caps[ $cap ]['roles'] ?? array();
		$this->assertSame( array( 'editor', 'administrator' ), $roles( 'wiw_manage_awards' ), 'the Editor and the Administrator run the awards' );
		$this->assertSame( array( 'administrator' ), $roles( 'wiw_toggle_nominations' ), 'only the Administrator turns Nominations on or off' );
		$this->assertTrue( (bool) ( $caps['wiw_toggle_nominations']['fixed'] ?? false ), 'and it cannot be granted to another role' );
		$this->assertSame( array( 'administrator' ), $roles( 'wiw_export_data' ), 'only the Administrator exports data' );
		$this->assertNotContains( 'reviewer', $roles( 'wiw_manage_awards' ) );
	}
}
