<?php
/**
 * The Awards tables (REQ-034, REQ-202, REQ-266, REQ-199). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Tests\TestCase;

final class AwardsTablesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		global $wpdb;
		foreach ( array_keys( AwardsTables::definitions() ) as $name ) {
			// The migration runner may have recorded the migration while a test dropped a table; make sure each exists.
			\WhoIsWho\Core\Platform\Migrations\Schema::create_table( $name, AwardsTables::definitions()[ $name ] );
		}
		$wpdb->query( 'DELETE FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
	}

	/**
	 * Insert a nomination with only the required columns.
	 *
	 * @param array<string, mixed> $extra Extra columns.
	 * @return array<string, mixed>
	 */
	private function nomination( array $extra = array() ): array {
		global $wpdb;
		$now = '2026-11-12 10:00:00';
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::NOMINATIONS ),
			array_merge(
				array(
					'category_id' => 1,
					'created_at'  => $now,
					'updated_at'  => $now,
				),
				$extra
			)
		);
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ) . ' WHERE id = %d', $wpdb->insert_id ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed name.
		$this->assertIsArray( $row );
		return $row;
	}

	/**
	 * Allowed values of an enum column.
	 *
	 * @param string $table  Table constant.
	 * @param string $column Column.
	 * @return string[]
	 */
	private function enum_values( string $table, string $column ): array {
		global $wpdb;
		$type = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s', AwardsTables::table( $table ), $column ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		preg_match_all( "/'([a-z_]+)'/", $type, $m );
		return $m[1];
	}

	public function test_REQ_034_new_nomination_defaults_to_nominated_and_not_live(): void {
		$row = $this->nomination();
		$this->assertSame( 'nominated', $row['stage'] );
		$this->assertSame( 'not_live', $row['status'] );
		$this->assertSame( 'public_form', $row['source'] );
		$this->assertNull( $row['nominator_name'], 'A staff-added nomination holds no nominator data.' );
		$this->assertNull( $row['contact_email'] );
	}

	public function test_REQ_034_stage_and_status_accept_only_the_defined_values(): void {
		$this->assertSame( array( 'nominated', 'winner' ), $this->enum_values( AwardsTables::NOMINATIONS, 'stage' ) );
		$this->assertSame( array( 'not_live', 'live' ), $this->enum_values( AwardsTables::NOMINATIONS, 'status' ) );
		$this->assertSame( AwardsTables::STAGES, $this->enum_values( AwardsTables::NOMINATIONS, 'stage' ) );
		$this->assertSame( AwardsTables::STATUSES, $this->enum_values( AwardsTables::NOMINATIONS, 'status' ) );
		foreach ( array( 'ineligible', 'declined', 'shortlisted', 'finalist' ) as $gone ) {
			$row = $this->nomination( array( 'stage' => $gone ) );
			$this->assertNotSame( $gone, $row['stage'], "{$gone} is not storable as a stage." );
			$row = $this->nomination( array( 'status' => $gone ) );
			$this->assertNotSame( $gone, $row['status'], "{$gone} is not storable as a status." );
		}
		$this->assertSame( array( 'stage', 'status', 'removal' ), $this->enum_values( AwardsTables::CHANGES, 'change_type' ) );
	}

	public function test_REQ_202_schema_has_no_judge_conflict_or_score_columns(): void {
		global $wpdb;
		$like    = $wpdb->esc_like( $wpdb->prefix . 'wiw_nomination' ) . '%';
		$tables  = array_map( array( AwardsTables::class, 'table' ), array_keys( AwardsTables::definitions() ) );
		$columns = (array) $wpdb->get_results( 'SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (\'' . implode( "','", array_map( 'esc_sql', $tables ) ) . '\')', ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed names.
		$this->assertNotEmpty( $columns );
		unset( $like );
		foreach ( $columns as $column ) {
			$this->assertDoesNotMatchRegularExpression( '/judge|conflict|score|weight|rating|shortlist|finalist/i', (string) $column['c'], $column['t'] );
		}
		foreach ( array_keys( AwardsTables::definitions() ) as $name ) {
			$this->assertDoesNotMatchRegularExpression( '/judge|conflict|score/i', $name );
		}
	}

	public function test_REQ_199_no_commercial_table_is_referenced_by_any_awards_table(): void {
		global $wpdb;
		$tables = array_map( array( AwardsTables::class, 'table' ), array_keys( AwardsTables::definitions() ) );
		$refs   = (array) $wpdb->get_col( 'SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL AND TABLE_NAME IN (\'' . implode( "','", array_map( 'esc_sql', $tables ) ) . '\')' ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed names.
		$this->assertSame( array(), $refs, 'No foreign key at all.' );
		foreach ( AwardsTables::definitions() as $definition ) {
			$this->assertDoesNotMatchRegularExpression( '/customer|arrangement|amount|payment|sponsor|price|invoice/i', $definition );
		}
	}

	public function test_REQ_266_five_award_groups_and_migration_is_idempotent(): void {
		$this->assertCount( 5, AwardsTables::GROUPS );
		$this->assertContains( 'Leadership and Public Impact', AwardsTables::GROUPS );
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$this->assertSame( 'nominated', $this->nomination()['stage'] );
	}

	public function test_REQ_268_nominator_and_contact_columns_are_in_the_pii_catalogue(): void {
		$this->assertSame(
			array(),
			( new PiiCatalogue() )->missing( array( 'wiw_nominations.nominator_name', 'wiw_nominations.nominator_email', 'wiw_nominations.contact_email', 'wiw_nominations.evidence' ) )
		);
	}
}
