<?php
/**
 * Parallel writers in separate processes keep one valid chain (REQ-051). Needs a database (WIW_TEST_DB_*).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Tests\Support\MysqliConnection;
use WhoIsWho\Tests\TestCase;

final class AuditConcurrencyTest extends TestCase {

	private const TABLE = 'wiw_audit_concurrency_probe';

	public function test_REQ_051_concurrent_appends_keep_chain_valid(): void {
		if ( ! getenv( 'WIW_TEST_DB_NAME' ) ) {
			$this->markTestSkipped( 'Set the WIW_TEST_DB_* variables to run this test.' );
		}
		$db = MysqliConnection::from_env( self::TABLE );
		$this->create_table();

		$workers   = 4;
		$per       = 25;
		$processes = array();
		$script    = dirname( __DIR__ ) . '/Support/audit-worker.php';
		for ( $w = 1; $w <= $workers; $w++ ) {
			$cmd   = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ) . ' ' . self::TABLE . ' ' . $per . ' ' . $w;
			$pipes = array();
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- real parallel processes are the point of this test.
			$proc        = proc_open(
				$cmd,
				array(
					1 => array( 'pipe', 'w' ),
					2 => array( 'pipe', 'w' ),
				),
				$pipes
			);
			$processes[] = array( $proc, $pipes );
		}
		foreach ( $processes as list( $proc, $pipes ) ) {
			$out = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
			proc_close( $proc );
			$this->assertStringContainsString( 'done', $out, 'a worker failed: ' . $out );
		}

		$rows = $db->rows( 0, 1000 );
		$this->assertCount( $workers * $per, $rows, 'every append was written' );
		$this->assertSame( count( $rows ), count( array_unique( array_column( $rows, 'prev_hash' ) ) ), 'no two rows share a previous hash, so there is one chain' );
		$this->assertSame(
			array(
				'intact'    => true,
				'broken_id' => null,
			),
			( new AuditLogger( $db ) )->verify()
		);
		$this->drop_table();
	}

	private function create_table(): void {
		$mysqli = $this->mysqli();
		$mysqli->query( 'DROP TABLE IF EXISTS ' . self::TABLE );
		$mysqli->query( 'CREATE TABLE ' . self::TABLE . ' ( ' . AuditLogger::TABLE_DEFINITION . ' ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' );
	}

	private function drop_table(): void {
		$this->mysqli()->query( 'DROP TABLE IF EXISTS ' . self::TABLE );
	}

	private function mysqli(): \mysqli {
		$host = (string) getenv( 'WIW_TEST_DB_HOST' );
		$port = 3306;
		if ( false !== strpos( $host, ':' ) ) {
			list( $host, $port ) = explode( ':', $host );
		}
		return new \mysqli( $host, (string) getenv( 'WIW_TEST_DB_USER' ), (string) getenv( 'WIW_TEST_DB_PASSWORD' ), (string) getenv( 'WIW_TEST_DB_NAME' ), (int) $port );
	}
}
