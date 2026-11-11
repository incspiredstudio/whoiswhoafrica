<?php
/**
 * The audit log: append-only, hash-chained, tamper-evident (REQ-051, REQ-188, REQ-228). Needs the WordPress test suite and a
 * database. Uses a scratch table created for the test, so no other test data is touched.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\TestCase;

final class AuditLoggerTest extends TestCase {

	private const PROBE = 'wiw_audit_probe';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		parent::tearDown();
	}

	private function logger( int $actor = 7 ): AuditLogger {
		return new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-11 10:00:00', static fn (): int => $actor );
	}

	public function test_REQ_051_every_row_has_actor_time_action_target_and_values(): void {
		global $wpdb;
		$id = $this->logger()->log(
			'profile.unpublish',
			array(
				'type' => 'profile',
				'id'   => 42,
			),
			array( 'state' => 'published' ),
			array( 'state' => 'unpublished' ),
			'removal request'
		);

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}" . self::PROBE . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$this->assertSame( '7', $row['actor_id'] );
		$this->assertSame( '2026-11-11 10:00:00', $row['at'] );
		$this->assertSame( 'profile.unpublish', $row['action'] );
		$this->assertSame( 'profile', $row['subject_type'] );
		$this->assertSame( '42', $row['subject_id'] );
		$this->assertSame( '{"state":"published"}', $row['old_value'] );
		$this->assertSame( '{"state":"unpublished"}', $row['new_value'] );
		$this->assertSame( 'removal request', $row['reason'] );
		$this->assertSame( AuditLogger::GENESIS, $row['prev_hash'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $row['hash'] );
	}

	public function test_REQ_051_audit_log_chain_detects_tampering(): void {
		global $wpdb;
		$logger = $this->logger();
		$ids    = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$ids[] = $logger->log(
				'label.set',
				array(
					'type' => 'profile',
					'id'   => $i,
				),
				null,
				array( 'label' => 'featured' )
			);
		}
		$this->assertSame(
			array(
				'intact'    => true,
				'broken_id' => null,
			),
			$logger->verify(),
			'an untouched log is intact'
		);

		// Change one value in the middle: verify names exactly that row.
		$table = $wpdb->prefix . self::PROBE;
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET new_value = %s WHERE id = %d", '{"label":"none"}', $ids[2] ) ); // phpcs:ignore WordPress.DB
		$result = $logger->verify();
		$this->assertFalse( $result['intact'] );
		$this->assertSame( $ids[2], $result['broken_id'] );

		// Restore it, then delete a row: the next row no longer follows.
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET new_value = %s WHERE id = %d", '{"label":"featured"}', $ids[2] ) ); // phpcs:ignore WordPress.DB
		$this->assertTrue( $logger->verify()['intact'] );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id = %d", $ids[1] ) ); // phpcs:ignore WordPress.DB
		$this->assertSame( $ids[2], $logger->verify()['broken_id'], 'a removed row breaks the row after it' );
	}

	public function test_REQ_188_batch_writes_one_row_per_subject_plus_batch_row(): void {
		global $wpdb;
		$subjects = array(
			array(
				'type' => 'profile',
				'id'   => 1,
			),
			array(
				'type' => 'profile',
				'id'   => 2,
			),
			array(
				'type' => 'profile',
				'id'   => 3,
			),
		);
		$batch    = $this->logger()->log_batch( 'profile.bulk_unpublish', $subjects, 'bad import' );

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT action, subject_type, new_value FROM {$wpdb->prefix}" . self::PROBE . ' WHERE batch_ref = %s ORDER BY id', $batch ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$this->assertCount( 4, $rows, 'one batch row plus one per subject' );
		$this->assertSame( 'profile.bulk_unpublish.batch', $rows[0]['action'] );
		$this->assertSame( '{"count":3}', $rows[0]['new_value'] );
		$this->assertSame( array( 'profile', 'profile', 'profile' ), array_column( array_slice( $rows, 1 ), 'subject_type' ) );
		$this->assertTrue( $this->logger()->verify()['intact'] );
	}

	public function test_REQ_051_there_is_no_way_to_update_or_delete_through_the_logger(): void {
		$methods = array_map( static fn ( \ReflectionMethod $m ): string => $m->getName(), ( new \ReflectionClass( AuditLogger::class ) )->getMethods( \ReflectionMethod::IS_PUBLIC ) );
		foreach ( $methods as $method ) {
			$this->assertDoesNotMatchRegularExpression( '/^(update|delete|remove|edit|purge|truncate)/i', $method, "{$method} must not exist" );
		}
	}

	public function test_REQ_051_the_migration_creates_the_real_table(): void {
		global $wpdb;
		$runner = new Runner( dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false );
		$this->assertContains( '2026111101', array_map( static fn ( $m ): string => $m->version(), $runner->discover() ) );
		$this->assertFalse( $runner->contract_versions() > array(), 'the audit migration is not destructive' );
		$this->assertSame( array(), array_values( array_filter( $runner->discover(), static fn ( $m ): bool => array() !== $m->pii_columns() ) ), 'no personal-data columns' );
		$this->assertNotNull( $wpdb );
	}
}
