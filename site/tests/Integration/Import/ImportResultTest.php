<?php
/**
 * The import audit row and the import result for acquisition (REQ-228, REQ-237, contract section 12). Needs the WordPress test
 * suite. Plain TestCase: the state machine opens real transactions on real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Import;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\Result\ImportAudit;
use WhoIsWho\Core\Import\Result\ImportResult;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateException;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ImportResultTest extends TestCase {

	private const BATCH = 'batch-result-test';

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
		$this->clean();
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
		$this->clean();
		$this->posts = array();
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function clean(): void {
		global $wpdb;
		$wpdb->delete( BatchRepository::table(), array( 'batch_id' => self::BATCH ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( ResultRepository::table(), array( 'batch_id' => self::BATCH ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login'   => 'Jane Okafor' . wp_generate_password( 4, false ),
				'display_name' => 'Jane Okafor',
				'user_pass'    => wp_generate_password(),
				'user_email'   => wp_generate_password( 8, false ) . '@example.test',
				'role'         => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	/**
	 * Register a batch confirmed by a named person, with results for profiles that carry names and field values.
	 *
	 * @return int The person.
	 */
	private function batch_with_results(): int {
		global $wpdb;
		$person = $this->user( 'administrator' );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			BatchRepository::table(),
			array(
				'batch_id'          => self::BATCH,
				'run_id'            => 'run-1',
				'contract_version'  => '1.0',
				'file_hash'         => 'sha256:filehash123',
				'record_count'      => 3,
				'status'            => 'applied',
				'progress_cursor'   => 3,
				'created_by'        => $person,
				'created_at'        => gmdate( 'Y-m-d H:i:s' ),
				'updated_at'        => gmdate( 'Y-m-d H:i:s' ),
				'settings_snapshot' => '{}',
			)
		);
		$results = new ResultRepository();
		foreach ( array(
			'rec-a' => 'CREATED',
			'rec-b' => 'CREATED',
			'rec-c' => 'SUPPRESSED',
		) as $record => $code ) {
			$profile = null;
			if ( 'SUPPRESSED' !== $code ) {
				$profile = wp_insert_post(
					array(
						'post_type'   => ProfileType::POST_TYPE,
						'post_title'  => 'Secret Holdings ' . $record,
						'post_status' => 'draft',
					)
				);
				$this->assertIsInt( $profile );
				$this->posts[] = $profile;
				( new StateMachine() )->enter(
					$profile,
					PubState::IMPORTED_NOT_PUBLIC,
					array(
						'entity_type'  => 'organisation',
						'import_batch' => self::BATCH,
					)
				);
				update_post_meta( $profile, 'wiw_sector', 'Confidential sector' );
			}
			$results->record( self::BATCH, $record, $code, $profile );
		}
		return $person;
	}

	public function test_REQ_228_import_writes_audit_row_with_counts_and_hash(): void {
		global $wpdb;
		$person = $this->batch_with_results();
		$batch  = ( new BatchRepository() )->get( self::BATCH );
		$this->assertIsArray( $batch );

		( new ImportAudit() )->record( $batch );

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'wiw_audit_log WHERE action = %s AND batch_ref = %s ORDER BY id DESC LIMIT 1', 'import.apply', self::BATCH ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$this->assertIsArray( $row );
		$after = json_decode( (string) $row['new_value'], true );
		$this->assertSame(
			array(
				'CREATED'    => 2,
				'SUPPRESSED' => 1,
			),
			$after['counts']
		);
		$this->assertSame( 'sha256:filehash123', $after['file_hash'] );
		$this->assertSame( $person, $after['confirmed_by'] );
		$this->assertSame( array( PubState::IMPORTED_NOT_PUBLIC => 2 ), $after['states'] );
	}

	public function test_REQ_237_result_export_has_no_field_values(): void {
		$person = $this->batch_with_results();
		$result = ( new ImportResult() )->for_batch( self::BATCH );

		$this->assertSame( self::BATCH, $result['batch_id'] );
		$this->assertSame( 'administrator', $result['importer_role'] );
		$this->assertSame(
			array(
				'CREATED'    => 2,
				'SUPPRESSED' => 1,
			),
			$result['counts']
		);
		$this->assertSame( array( array( 'rec-a', 'CREATED' ), array( 'rec-b', 'CREATED' ), array( 'rec-c', 'SUPPRESSED' ) ), $result['records'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $result['generated_at'] );

		$json = (string) wp_json_encode( $result );
		foreach ( array( 'Secret Holdings', 'Confidential sector', 'Jane Okafor', (string) get_userdata( $person )->user_login, (string) get_userdata( $person )->user_email ) as $forbidden ) {
			$this->assertStringNotContainsString( $forbidden, $json );
		}
		$this->assertSame( array( 'batch_id', 'importer_role', 'generated_at', 'counts', 'records' ), array_keys( $result ) );
	}

	public function test_REQ_237_result_download_is_limited_to_administrators_and_audited(): void {
		global $wpdb;
		$this->batch_with_results();
		try {
			( new ImportResult() )->export( self::BATCH, $this->user( 'wiw_reviewer' ) );
			$this->fail( 'expected a refusal' );
		} catch ( StateException $e ) {
			$this->assertSame( 'Not allowed.', $e->getMessage() );
		}
		$json = ( new ImportResult() )->export( self::BATCH, $this->user( 'administrator' ) );
		$this->assertSame( self::BATCH, json_decode( $json, true )['batch_id'] );
		$this->assertGreaterThan( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wiw_audit_log WHERE action = %s AND batch_ref = %s', 'import.result_export', self::BATCH ) ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
	}
}
