<?php
/**
 * The three health checks behind /healthz (REQ-050, REQ-045): the database answers, the job runner has run recently, and the
 * disk has room. Each probe is a callable so tests can set any outcome. The report carries no personal data.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Health;

/**
 * Health check.
 */
final class HealthCheck {

	/**
	 * A job heartbeat older than this (seconds) is stale. Jobs run on every cron tick, so half an hour of silence is a fault.
	 */
	public const MAX_HEARTBEAT_AGE = 1800;

	/**
	 * Less free disk than this (bytes) is a fault.
	 */
	public const MIN_FREE_BYTES = 209715200;

	/**
	 * Probes.
	 *
	 * @var array<string, callable>
	 */
	private array $probes;

	/**
	 * Constructor.
	 *
	 * @param array{database: callable(): bool, heartbeat: callable(): int, now: callable(): int, free_bytes: callable(): int} $probes Probes: database answers, Unix time of the last job run (0 if never), now, free disk bytes.
	 */
	public function __construct( array $probes ) {
		$this->probes = $probes;
	}

	/**
	 * Check everything.
	 *
	 * @return array{ok: bool, database: array{ok: bool}, jobs: array{ok: bool, heartbeat_age_seconds: int|null}, disk: array{ok: bool, free_megabytes: int}}
	 */
	public function report(): array {
		$database = (bool) call_user_func( $this->probes['database'] );
		$beat     = (int) call_user_func( $this->probes['heartbeat'] );
		$now      = (int) call_user_func( $this->probes['now'] );
		$free     = (int) call_user_func( $this->probes['free_bytes'] );
		$age      = $beat > 0 ? max( 0, $now - $beat ) : null;
		$jobs_ok  = null !== $age && $age <= self::MAX_HEARTBEAT_AGE;
		$disk_ok  = $free >= self::MIN_FREE_BYTES;
		return array(
			'ok'       => $database && $jobs_ok && $disk_ok,
			'database' => array( 'ok' => $database ),
			'jobs'     => array(
				'ok'                    => $jobs_ok,
				'heartbeat_age_seconds' => $age,
			),
			'disk'     => array(
				'ok'             => $disk_ok,
				'free_megabytes' => (int) floor( $free / 1048576 ),
			),
		);
	}
}
