<?php
/**
 * Worker for the concurrency test: appends N rows to the probe table in its own process and connection.
 * Usage: php audit-worker.php <table> <count> <worker-id>
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Tests\Support\MysqliConnection;

$table  = $argv[1];
$count  = (int) $argv[2];
$worker = (int) $argv[3];

$logger = new AuditLogger( MysqliConnection::from_env( $table ), null, static fn (): int => $worker );
for ( $i = 0; $i < $count; $i++ ) {
	$logger->log(
		'probe.append',
		array(
			'type' => 'probe',
			'id'   => $worker . '-' . $i,
		),
		null,
		array( 'i' => $i )
	);
}
echo "done\n";
