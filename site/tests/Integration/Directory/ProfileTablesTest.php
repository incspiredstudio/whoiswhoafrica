<?php
/**
 * The profile state, version and provenance tables and their repositories (REQ-123, REQ-125, REQ-142, REQ-242).
 * Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\StateWriter;
use WhoIsWho\Core\Directory\State\VersionGuard;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

/**
 * Plain TestCase on purpose: WordPress's test case turns CREATE TABLE into temporary tables, and these tests need the real
 * tables and triggers. The tables are emptied in setUp.
 */
final class ProfileTablesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		$runner = new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false );
		$runner->run();
		foreach ( array( StateRepository::table(), VersionRepository::table(), ProvenanceRepository::table() ) as $table ) {
			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed names; TRUNCATE is not blocked by the append-only triggers.
		}
	}

	public function test_REQ_242_state_row_stores_source_and_batch(): void {
		$writer = new StateWriter();
		$writer->insert(
			101,
			PubState::IMPORTED_NOT_PUBLIC,
			array(
				'entity_type'  => 'individual',
				'tier'         => 'T1',
				'origin'       => 'import',
				'source'       => 'cac-register',
				'import_batch' => 'batch-0001',
				'content_hash' => str_repeat( 'a', 64 ),
				'importer_id'  => 3,
			)
		);
		$row = ( new StateRepository() )->get( 101 );
		$this->assertNotNull( $row );
		$this->assertSame( 'imported_not_public', $row['pub_state'] );
		$this->assertSame( 'individual', $row['entity_type'] );
		$this->assertSame( 'T1', $row['tier'] );
		$this->assertSame( 'import', $row['origin'] );
		$this->assertSame( 'cac-register', $row['source'] );
		$this->assertSame( 'batch-0001', $row['import_batch'] );
		$this->assertSame( str_repeat( 'a', 64 ), $row['content_hash'] );
		$this->assertSame( '3', $row['importer_id'] );
		$this->assertSame( '0', $row['claimed'] );
		foreach ( array( 'reviewer_id', 'reviewed_at', 'claimed_at', 'assigned_user_id' ) as $column ) {
			$this->assertArrayHasKey( $column, $row );
		}
	}

	public function test_REQ_123_state_table_has_one_row_per_profile_and_no_public_pub_state_write(): void {
		$writer = new StateWriter();
		$writer->insert( 7, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		try {
			$writer->insert( 7, PubState::PUBLISHED_REVIEWED, array( 'entity_type' => 'organisation' ) );
			$this->fail( 'A second row for the same profile must be refused.' );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'published_pending', ( new StateRepository() )->pub_state( 7 ) );
		}
		$this->expectException( \InvalidArgumentException::class );
		( new StateRepository() )->update( 7, array( 'pub_state' => PubState::PUBLISHED_REVIEWED ) );
	}

	public function test_REQ_123_repository_updates_other_columns_but_not_state(): void {
		( new StateWriter() )->insert( 8, PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => 'organisation' ) );
		$repo = new StateRepository();
		$repo->update(
			8,
			array(
				'claimed'          => 1,
				'assigned_user_id' => 5,
			)
		);
		$row = $repo->get( 8 );
		$this->assertSame( '1', $row['claimed'] );
		$this->assertSame( '5', $row['assigned_user_id'] );
		$this->assertSame( 'imported_not_public', $row['pub_state'] );
		$this->assertFalse( method_exists( $repo, 'set_pub_state' ) );
	}

	public function test_REQ_123_state_table_filters_use_indexes(): void {
		global $wpdb;
		$writer = new StateWriter();
		for ( $i = 1; $i <= 30; $i++ ) {
			$writer->insert(
				$i,
				0 === $i % 2 ? PubState::PUBLISHED_PENDING : PubState::IMPORTED_NOT_PUBLIC,
				array(
					'entity_type'  => 0 === $i % 3 ? 'individual' : 'organisation',
					'source'       => 'source-' . ( $i % 4 ),
					'import_batch' => 'batch-' . ( $i % 5 ),
				)
			);
		}
		$table = StateRepository::table();
		$cases = array(
			'source'       => "WHERE source = 'source-1'",
			'import_batch' => "WHERE import_batch = 'batch-1'",
			'entity_type'  => "WHERE entity_type = 'individual'",
			'pub_state'    => "WHERE pub_state = 'published_pending'",
			'origin'       => "WHERE origin = 'import'",
		);
		foreach ( $cases as $key => $where ) {
			$plan = $wpdb->get_row( "EXPLAIN SELECT profile_id FROM {$table} {$where}", ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed test SQL.
			$this->assertStringContainsString( $key, (string) $plan['possible_keys'], "the {$key} filter has an index to use" );
		}
		$this->assertSame( 15, ( new StateRepository() )->count( array( 'pub_state' => PubState::PUBLISHED_PENDING ) ) );
		$this->assertSame( 10, ( new StateRepository() )->count( array( 'entity_type' => 'individual' ) ) );
		$this->assertSame( array( 2, 4 ), ( new StateRepository() )->ids( array( 'pub_state' => PubState::PUBLISHED_PENDING ), 2 ) );
	}

	public function test_REQ_125_versions_table_is_append_only(): void {
		global $wpdb;
		$this->assertTrue( VersionGuard::installed(), 'The append-only triggers exist.' );
		$repo = new VersionRepository();
		$id   = $repo->append( 55, 0, 'import', '', array(), array( 'title' => 'Ada' ) );
		$this->assertGreaterThan( 0, $id );
		$this->assertCount( 1, $repo->for_profile( 55 ) );
		$this->assertFalse( method_exists( $repo, 'update' ) );
		$this->assertFalse( method_exists( $repo, 'delete' ) );

		$table = VersionRepository::table();
		$wpdb->suppress_errors( true );
		$updated = $wpdb->query( "UPDATE {$table} SET reason = 'changed' WHERE id = {$id}" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test SQL.
		$deleted = $wpdb->query( "DELETE FROM {$table} WHERE id = {$id}" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test SQL.
		$wpdb->suppress_errors( false );
		$this->assertFalse( $updated, 'UPDATE is rejected by the database.' );
		$this->assertFalse( $deleted, 'DELETE is rejected by the database.' );
		$this->assertCount( 1, $repo->for_profile( 55 ) );
		$this->assertNull( $repo->for_profile( 55 )[0]['reason'] );
	}

	public function test_REQ_125_pending_revision_is_decided_by_a_new_row(): void {
		$repo    = new VersionRepository();
		$pending = $repo->append( 56, 9, 'revision', 'fix title', array( 'title' => 'A' ), array( 'title' => 'B' ), 'pending' );
		$this->assertCount( 1, $repo->pending_for_profile( 56 ) );
		$repo->append( 56, 4, 'decision', 'ok', array(), array(), 'approved', $pending );
		$this->assertCount( 0, $repo->pending_for_profile( 56 ) );
		$this->assertCount( 2, $repo->for_profile( 56 ) );
	}

	public function test_REQ_142_provenance_row_requires_source_date_confidence_method(): void {
		$repo  = new ProvenanceRepository();
		$entry = array(
			'field'        => 'bio',
			'source_url'   => 'https://example.org/a',
			'retrieved_at' => '2026-11-10T09:00:00Z',
			'confidence'   => 'high',
			'method'       => 'import',
			'source_hash'  => str_repeat( 'b', 64 ),
		);
		$id    = $repo->record( 12, $entry );
		$this->assertGreaterThan( 0, $id );
		$rows = $repo->for_profile( 12, 'bio' );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'https://example.org/a', $rows[0]['source_url'] );
		$this->assertSame( '2026-11-10 09:00:00', $rows[0]['retrieved_at'] );
		$this->assertSame( 'high', $rows[0]['confidence'] );
		$this->assertSame( 'import', $rows[0]['method'] );

		foreach ( array( 'source_url', 'retrieved_at', 'confidence', 'method' ) as $missing ) {
			$bad = $entry;
			unset( $bad[ $missing ] );
			$this->assertSame( 'UNSOURCED_FIELD', ProvenanceRepository::refusal( $bad ), "{$missing} is required" );
			try {
				$repo->record( 12, $bad );
				$this->fail( "{$missing} missing must be refused" );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertSame( 'UNSOURCED_FIELD', $e->getMessage() );
			}
		}
		$low = array_merge( $entry, array( 'confidence' => 'low' ) );
		$this->assertSame( 'LOW_CONFIDENCE', ProvenanceRepository::refusal( $low ) );
		$this->assertCount( 1, $repo->for_profile( 12 ), 'Refused rows were not written.' );
	}
}
