<?php
/**
 * /healthz (REQ-050, REQ-045): ok or fail without the token, detail with it, stale job heartbeat is a fault.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Core\Platform\Health\HealthCheck;
use WhoIsWho\Core\Platform\Health\HealthEndpoint;
use WhoIsWho\Tests\TestCase;

final class HealthEndpointTest extends TestCase {

	private const NOW = 1800000000;

	/**
	 * Build an endpoint with chosen probe results.
	 *
	 * @param bool $database  Database answers.
	 * @param int  $heartbeat Unix time of the last job run.
	 * @param int  $free      Free bytes.
	 * @param string $token   Configured token.
	 */
	private function endpoint( bool $database = true, int $heartbeat = self::NOW - 60, int $free = 5000000000, string $token = 'secret-token' ): HealthEndpoint {
		$check = new HealthCheck(
			array(
				'database'   => static fn (): bool => $database,
				'heartbeat'  => static fn (): int => $heartbeat,
				'now'        => static fn (): int => self::NOW,
				'free_bytes' => static fn (): int => $free,
			)
		);
		return new HealthEndpoint(
			$check,
			$token,
			static fn (): array => array(
				'version' => '1.2.3',
				'commit'  => 'abc1234',
			)
		);
	}

	public function test_REQ_050_healthz_shows_no_detail_without_token(): void {
		$answer = $this->endpoint()->respond( '' );
		$this->assertSame( 200, $answer['status'] );
		$this->assertSame( array( 'status' => 'ok' ), $answer['body'] );

		$wrong = $this->endpoint()->respond( 'not-the-token' );
		$this->assertSame( array( 'status' => 'ok' ), $wrong['body'] );

		$failing = $this->endpoint( false )->respond( '' );
		$this->assertSame( 503, $failing['status'] );
		$this->assertSame( array( 'status' => 'fail' ), $failing['body'] );
	}

	public function test_REQ_050_with_the_token_it_lists_database_heartbeat_disk_and_the_release(): void {
		$answer = $this->endpoint()->respond( 'secret-token' );
		$this->assertSame( 200, $answer['status'] );
		$body = $answer['body'];
		$this->assertSame( 'ok', $body['status'] );
		$this->assertSame( array( 'ok' => true ), $body['database'] );
		$this->assertSame( 60, $body['jobs']['heartbeat_age_seconds'] );
		$this->assertTrue( $body['jobs']['ok'] );
		$this->assertSame( 4768, $body['disk']['free_megabytes'] );
		$this->assertSame( '1.2.3', $body['release']['version'] );
	}

	public function test_REQ_050_stale_job_heartbeat_reports_unhealthy(): void {
		$stale = $this->endpoint( true, self::NOW - 3600 )->respond( 'secret-token' );
		$this->assertSame( 503, $stale['status'] );
		$this->assertSame( 'fail', $stale['body']['status'] );
		$this->assertFalse( $stale['body']['jobs']['ok'] );
		$this->assertSame( 3600, $stale['body']['jobs']['heartbeat_age_seconds'] );

		$never = $this->endpoint( true, 0 )->respond( 'secret-token' );
		$this->assertSame( 503, $never['status'], 'a runner that has never run is not healthy' );
		$this->assertNull( $never['body']['jobs']['heartbeat_age_seconds'] );
	}

	public function test_REQ_050_low_disk_reports_unhealthy(): void {
		$answer = $this->endpoint( true, self::NOW - 60, 1000 )->respond( 'secret-token' );
		$this->assertSame( 503, $answer['status'] );
		$this->assertFalse( $answer['body']['disk']['ok'] );
	}

	public function test_REQ_050_an_unset_token_never_shows_detail(): void {
		$answer = $this->endpoint( true, self::NOW - 60, 5000000000, '' )->respond( '' );
		$this->assertSame( array( 'status' => 'ok' ), $answer['body'], 'an empty configured token must not match an empty header' );
	}

	public function test_REQ_050_the_report_holds_no_personal_data(): void {
		$json = (string) json_encode( $this->endpoint()->respond( 'secret-token' )['body'] );
		$this->assertDoesNotMatchRegularExpression( '/@|password|email|user/i', $json );
	}
}
