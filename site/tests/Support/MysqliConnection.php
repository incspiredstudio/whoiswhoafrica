<?php
/**
 * A plain mysqli connection to an audit table, with no WordPress. Lets tests run several real writers in separate processes.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Support;

use WhoIsWho\Core\Platform\Audit\Connection;

/**
 * Connection on mysqli.
 */
final class MysqliConnection implements Connection {

	/**
	 * The connection.
	 *
	 * @var \mysqli
	 */
	private \mysqli $mysqli;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 *
	 * @param \mysqli $mysqli Connection.
	 * @param string  $table  Table name.
	 */
	public function __construct( \mysqli $mysqli, string $table ) {
		$this->mysqli = $mysqli;
		$this->table  = $table;
	}

	/**
	 * Open a connection from the WIW_TEST_DB_* environment variables.
	 *
	 * @param string $table Table name.
	 */
	public static function from_env( string $table ): self {
		$host = (string) getenv( 'WIW_TEST_DB_HOST' );
		$port = 3306;
		if ( false !== strpos( $host, ':' ) ) {
			list( $host, $port ) = explode( ':', $host );
		}
		$mysqli = new \mysqli( $host, (string) getenv( 'WIW_TEST_DB_USER' ), (string) getenv( 'WIW_TEST_DB_PASSWORD' ), (string) getenv( 'WIW_TEST_DB_NAME' ), (int) $port );
		return new self( $mysqli, $table );
	}

	/**
	 * Lock.
	 */
	public function lock(): bool {
		$res = $this->mysqli->query( "SELECT GET_LOCK('wiw_audit_probe:{$this->table}', 20)" );
		$row = $res ? $res->fetch_row() : array( 0 );
		return 1 === (int) $row[0];
	}

	/**
	 * Unlock.
	 */
	public function unlock(): void {
		$this->mysqli->query( "SELECT RELEASE_LOCK('wiw_audit_probe:{$this->table}')" );
	}

	/**
	 * Newest hash.
	 */
	public function last_hash(): ?string {
		$res = $this->mysqli->query( "SELECT hash FROM {$this->table} ORDER BY id DESC LIMIT 1" );
		$row = $res ? $res->fetch_row() : null;
		return $row ? (string) $row[0] : null;
	}

	/**
	 * Insert.
	 *
	 * @param array<string, mixed> $row Row.
	 */
	public function insert( array $row ): int {
		$cols   = implode( ',', array_keys( $row ) );
		$values = implode(
			',',
			array_map(
				fn ( $v ) => null === $v ? 'NULL' : "'" . $this->mysqli->real_escape_string( (string) $v ) . "'",
				$row
			)
		);
		if ( ! $this->mysqli->query( "INSERT INTO {$this->table} ({$cols}) VALUES ({$values})" ) ) {
			throw new \RuntimeException( 'insert failed' );
		}
		return (int) $this->mysqli->insert_id;
	}

	/**
	 * Rows after an id.
	 *
	 * @param int $after_id Id.
	 * @param int $limit    Maximum.
	 * @return array<int, array<string, mixed>>
	 */
	public function rows( int $after_id, int $limit ): array {
		$res = $this->mysqli->query( "SELECT * FROM {$this->table} WHERE id > {$after_id} ORDER BY id ASC LIMIT {$limit}" );
		return $res ? $res->fetch_all( MYSQLI_ASSOC ) : array();
	}
}
