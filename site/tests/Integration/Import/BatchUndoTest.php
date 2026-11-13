<?php
/**
 * Undo of an import batch (REQ-237): unreviewed creations are removed, unreviewed updates are reverted, reviewed records are
 * kept and listed, and the undo is audited with counts. Needs the WordPress test suite. Plain TestCase: the state machine opens
 * real transactions on real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Import;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Import\Apply\RecordMap;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\Undo\BatchUndo;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateException;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class BatchUndoTest extends TestCase {

	private const BATCH = 'batch-undo-test';

	/**
	 * Posts created.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Users created.
	 *
	 * @var int[]
	 */
	private array $users = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
		$this->clean_batch();
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			BatchRepository::table(),
			array(
				'batch_id'          => self::BATCH,
				'run_id'            => 'run-1',
				'contract_version'  => '1.0',
				'file_hash'         => 'sha256:abc',
				'record_count'      => 4,
				'status'            => 'applied',
				'progress_cursor'   => 4,
				'created_by'        => 1,
				'created_at'        => gmdate( 'Y-m-d H:i:s' ),
				'updated_at'        => gmdate( 'Y-m-d H:i:s' ),
				'settings_snapshot' => '{}',
			)
		);
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
		$this->clean_batch();
		$this->posts = array();
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function clean_batch(): void {
		global $wpdb;
		$wpdb->delete( BatchRepository::table(), array( 'batch_id' => self::BATCH ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( ResultRepository::table(), array( 'batch_id' => self::BATCH ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( 'DELETE FROM ' . RecordMap::table() . " WHERE record_id LIKE 'rec-%'" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
	}

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'user_email' => wp_generate_password( 8, false ) . '@example.test',
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	private function profile( string $record_id, string $code, string $state = PubState::IMPORTED_NOT_PUBLIC, bool $reviewed = false ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => 'Undo ' . $record_id,
				'post_status' => 'draft',
			)
		);
		$this->assertIsInt( $id );
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter(
			$id,
			$state,
			array(
				'entity_type'  => 'organisation',
				'import_batch' => self::BATCH,
			)
		);
		if ( $reviewed ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', $this->user( 'wiw_reviewer' ) );
		}
		( new RecordMap() )->link( $record_id, $id );
		( new ResultRepository() )->record( self::BATCH, $record_id, $code, $id );
		return $id;
	}

	public function test_REQ_237_undo_removes_unreviewed_only(): void {
		$fresh    = $this->profile( 'rec-1', 'CREATED' );
		$pending  = $this->profile( 'rec-2', 'CREATED', PubState::PUBLISHED_PENDING );
		$reviewed = $this->profile( 'rec-3', 'CREATED', PubState::PUBLISHED_PENDING, true );

		$out = ( new BatchUndo() )->undo( self::BATCH, $this->user( 'administrator' ) );

		$states = new StateRepository();
		$this->assertSame( PubState::REJECTED, $states->pub_state( $fresh ) );
		$this->assertSame( PubState::UNPUBLISHED, $states->pub_state( $pending ), 'a live unreviewed record is taken down (410)' );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, $states->pub_state( $reviewed ), 'a reviewed record stays' );
		$this->assertNull( ( new RecordMap() )->profile_for( 'rec-1' ), 'a removed record can be imported again as new' );
		$this->assertSame( 2, $out['removed'] );
		$this->assertSame( 1, $out['kept'] );
		$this->assertSame( array( 'rec-3' ), $out['kept_record_ids'] );
		$this->assertSame( 'undone', ( new BatchRepository() )->get( self::BATCH )['status'] );
	}

	public function test_REQ_237_undo_keeps_reviewed_records(): void {
		$reviewed = $this->profile( 'rec-1', 'CREATED', PubState::PUBLISHED_PENDING, true );
		$flagged  = $this->profile( 'rec-2', 'FLAGGED_CHANGE', PubState::PUBLISHED_PENDING, true );
		( new BatchUndo() )->undo( self::BATCH, $this->user( 'administrator' ) );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, ( new StateRepository() )->pub_state( $reviewed ) );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, ( new StateRepository() )->pub_state( $flagged ) );
	}

	public function test_REQ_237_undo_reverts_unreviewed_updates(): void {
		$id = $this->profile( 'rec-1', 'UPDATED' );
		update_post_meta( $id, 'wiw_sector', 'Finance' );
		( new VersionRepository() )->append(
			$id,
			0,
			'import',
			'Updated by import batch ' . self::BATCH,
			array(
				'sector'  => 'Banking',
				'mandate' => '',
			),
			array(
				'sector'  => 'Finance',
				'mandate' => 'Lend',
			)
		);
		update_post_meta( $id, 'wiw_mandate', 'Lend' );
		$before = count( ( new VersionRepository() )->for_profile( $id ) );

		$out = ( new BatchUndo() )->undo( self::BATCH, $this->user( 'administrator' ) );

		$this->assertSame( 'Banking', get_post_meta( $id, 'wiw_sector', true ) );
		$this->assertSame( '', get_post_meta( $id, 'wiw_mandate', true ) );
		$this->assertSame( 1, $out['reverted'] );
		$this->assertCount( $before + 1, ( new VersionRepository() )->for_profile( $id ), 'the revert is a new version' );
		$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, ( new StateRepository() )->pub_state( $id ), 'the profile stays' );
	}

	public function test_REQ_237_undo_writes_an_audit_row_with_the_counts(): void {
		global $wpdb;
		$this->profile( 'rec-1', 'CREATED' );
		$this->profile( 'rec-2', 'CREATED', PubState::PUBLISHED_PENDING, true );
		( new BatchUndo() )->undo( self::BATCH, $this->user( 'administrator' ) );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'wiw_audit_log WHERE action = %s AND batch_ref = %s ORDER BY id DESC LIMIT 1', 'import.undo', self::BATCH ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$this->assertIsArray( $row );
		$this->assertStringContainsString( '"removed":1', (string) $row['new_value'] );
		$this->assertStringContainsString( '"kept":1', (string) $row['new_value'] );
	}

	public function test_REQ_237_undo_needs_the_import_capability_and_an_applied_batch(): void {
		$this->profile( 'rec-1', 'CREATED' );
		try {
			( new BatchUndo() )->undo( self::BATCH, $this->user( 'subscriber' ) );
			$this->fail( 'expected a refusal' );
		} catch ( StateException $e ) {
			$this->assertSame( 'Not allowed.', $e->getMessage() );
		}
		$admin = $this->user( 'administrator' );
		( new BatchUndo() )->undo( self::BATCH, $admin );
		$this->expectException( StateException::class );
		( new BatchUndo() )->undo( self::BATCH, $admin );
	}
}
