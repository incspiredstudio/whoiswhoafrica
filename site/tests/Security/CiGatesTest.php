<?php
/**
 * The merge gates in .github/workflows/ci.yml are all present and none can be skipped quietly (M5-CI-01, M5-SEC-05;
 * REQ-056, REQ-046, REQ-190). Static checks on the workflow text; branch protection itself lives in the repository settings
 * and is not testable here.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CiGatesTest extends TestCase {

	/**
	 * The workflow file.
	 */
	private function workflow(): string {
		$text = (string) file_get_contents( dirname( __DIR__, 3 ) . '/.github/workflows/ci.yml' );
		return str_replace( "\r\n", "\n", $text );
	}

	/**
	 * Text of one job, from its key to the next job.
	 *
	 * @param string $job Job key.
	 */
	private function job( string $job ): string {
		$this->assertSame( 1, preg_match( '/^  ' . preg_quote( $job, '/' ) . ':\n(.*?)(?=^  [a-z0-9-]+:\n|\z)/ms', $this->workflow(), $m ), 'job ' . $job . ' is missing from ci.yml' );
		return $m[1];
	}

	public function test_REQ_056_every_gate_is_a_job(): void {
		foreach ( array( 'lint', 'analyse', 'test', 'visibility', 'build', 'e2e', 'audit', 'plugins', 'brand', 'secrets', 'pr-body' ) as $job ) {
			$this->assertNotSame( '', $this->job( $job ) );
		}
	}

	public function test_REQ_056_workflow_runs_on_pull_requests(): void {
		$this->assertMatchesRegularExpression( '/^on:\n(?:.*\n)*?\s+pull_request:/m', $this->workflow() );
	}

	public function test_REQ_056_ci_pipeline_runs_all_gates(): void {
		$this->assertStringContainsString( 'composer lint', $this->job( 'lint' ) );
		$this->assertStringContainsString( 'composer analyse', $this->job( 'analyse' ) );
		$this->assertStringContainsString( 'composer test:coverage', $this->job( 'test' ) );
		$this->assertStringContainsString( '--testsuite Visibility', $this->job( 'visibility' ) );
		$this->assertStringContainsString( 'npm run build', $this->job( 'build' ) );
		$this->assertStringContainsString( 'npx playwright test', $this->job( 'e2e' ) );
	}

	public function test_REQ_056_coverage_threshold_is_enforced_by_the_test_script(): void {
		$composer = (string) file_get_contents( dirname( __DIR__, 2 ) . '/composer.json' );
		$this->assertMatchesRegularExpression( '/"test:coverage"\s*:\s*\[[^\]]*check-coverage\.php/s', $composer );
		$this->assertFileExists( dirname( __DIR__ ) . '/check-coverage.php' );
	}

	public function test_REQ_046_ci_fails_on_high_severity_finding(): void {
		$audit = $this->job( 'audit' );
		$this->assertStringContainsString( 'composer audit', $audit );
		$this->assertStringContainsString( 'npm audit --audit-level=high', $audit );
		$this->assertStringNotContainsString( 'continue-on-error', $audit );
		$this->assertDoesNotMatchRegularExpression( '/(composer|npm) audit[^\n]*\|\|\s*true/', $audit );
	}

	public function test_REQ_046_secret_scan_reads_the_full_history(): void {
		$secrets = $this->job( 'secrets' );
		$this->assertStringContainsString( 'gitleaks', $secrets );
		$this->assertStringContainsString( 'fetch-depth: 0', $secrets );
		$this->assertStringNotContainsString( 'continue-on-error', $secrets );
	}

	public function test_REQ_190_brand_leak_check_runs_in_ci(): void {
		$brand = $this->job( 'brand' );
		$this->assertStringContainsString( 'node scripts/check-brand-values.js', $brand );
	}

	public function test_REQ_273_child_theme_check_runs_in_the_browser_job(): void {
		$spec = (string) file_get_contents( dirname( __DIR__ ) . '/e2e/brand-config.spec.ts' );
		$this->assertStringContainsString( 'child theme', strtolower( $spec ) );
		$this->assertStringContainsString( 'WIW_THEME', $this->job( 'e2e' ) . (string) file_get_contents( dirname( __DIR__, 2 ) . '/deploy/ci/install-wp.php' ) );
	}

	public function test_REQ_056_pr_body_gate_needs_a_req_id_and_closes_line(): void {
		$gate = $this->job( 'pr-body' );
		$this->assertStringContainsString( 'REQ-[0-9]+', $gate );
		$this->assertStringContainsString( 'closes|fixes|resolves', strtolower( $gate ) );
	}

	public function test_REQ_056_only_the_traceability_report_may_continue_on_error(): void {
		$count = preg_match_all( '/continue-on-error/', $this->workflow() );
		$this->assertLessThanOrEqual( 1, $count, 'a blocking gate was made optional' );
		if ( 1 === $count ) {
			$this->assertStringContainsString( 'Traceability report', $this->job( 'brand' ) );
			$this->assertMatchesRegularExpression( '/Traceability report[^\n]*\n\s+continue-on-error: true/', $this->workflow() );
		}
	}
}
