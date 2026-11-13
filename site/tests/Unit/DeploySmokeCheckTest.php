<?php
/**
 * The deploy smoke-check rules and command (M5-LCH-03; REQ-273, REQ-208, REQ-046).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Deploy\Smoke;
use WhoIsWho\Tests\TestCase;

require_once dirname( __DIR__, 2 ) . '/deploy/smoke/Smoke.php';

final class DeploySmokeCheckTest extends TestCase {

	/**
	 * Facts for a healthy site.
	 *
	 * @return array<string, mixed>
	 */
	private function good(): array {
		return array(
			'base_url'           => 'https://example.org',
			'stylesheet'         => 'whoiswho-child',
			'template'           => 'whoiswho',
			'parent_exists'      => true,
			'secrets_loaded'     => true,
			'file_edit_disabled' => true,
			'registration_open'  => false,
			'cron_scheduled'     => true,
			'pending_migrations' => 0,
			'http'               => array(
				'home'        => array( 'status' => 200 ),
				'xmlrpc'      => array( 'status' => 403 ),
				'rest'        => array( 'status' => 401 ),
				'sitemap'     => array(
					'status' => 200,
					'body'   => '<sitemapindex><sitemap><loc>https://example.org/sitemap-pages.xml</loc></sitemap></sitemapindex>',
				),
				'author_enum' => array(
					'status'   => 200,
					'location' => '',
				),
				'healthz'     => array( 'status' => 200 ),
				'gone'        => array( 'status' => 410 ),
			),
		);
	}

	/**
	 * Names of failed checks.
	 *
	 * @param array<string, mixed> $facts Facts.
	 * @return list<string>
	 */
	private function failed( array $facts ): array {
		$names = array();
		foreach ( Smoke::evaluate( $facts ) as $row ) {
			if ( Smoke::FAIL === $row['status'] ) {
				$names[] = $row['name'];
			}
		}
		return $names;
	}

	public function test_REQ_273_deploy_check_passes_on_a_healthy_site(): void {
		$results = Smoke::evaluate( $this->good() );
		$this->assertSame( array(), $this->failed( $this->good() ) );
		$this->assertTrue( Smoke::passed( $results ) );
	}

	public function test_REQ_273_deploy_check_fails_when_child_theme_inactive(): void {
		$facts               = $this->good();
		$facts['stylesheet'] = 'whoiswho';
		$this->assertSame( array( 'Child theme whoiswho-child is active and the parent whoiswho is present' ), $this->failed( $facts ) );
	}

	public function test_REQ_273_deploy_check_fails_when_parent_theme_missing(): void {
		$facts                  = $this->good();
		$facts['parent_exists'] = false;
		$this->assertCount( 1, $this->failed( $facts ) );
	}

	public function test_REQ_046_deploy_check_fails_on_open_registration_and_file_edit(): void {
		$facts                       = $this->good();
		$facts['registration_open']  = true;
		$facts['file_edit_disabled'] = false;
		$this->assertCount( 2, $this->failed( $facts ) );
	}

	public function test_REQ_046_deploy_check_fails_on_pending_migrations_and_missing_secrets(): void {
		$facts                       = $this->good();
		$facts['pending_migrations'] = 2;
		$facts['secrets_loaded']     = false;
		$this->assertCount( 2, $this->failed( $facts ) );
	}

	public function test_REQ_208_deploy_check_fails_when_sitemap_lists_a_preview_address(): void {
		$facts                            = $this->good();
		$facts['http']['sitemap']['body'] = '<sitemapindex><loc>https://example.org/preview/x</loc></sitemapindex>';
		$this->assertCount( 1, $this->failed( $facts ) );
	}

	public function test_REQ_046_deploy_check_fails_on_http_xmlrpc_and_author_enumeration(): void {
		$facts                                    = $this->good();
		$facts['base_url']                        = 'http://example.org';
		$facts['http']['xmlrpc']['status']        = 200;
		$facts['http']['author_enum']['location'] = 'https://example.org/author/admin/';
		$this->assertCount( 3, $this->failed( $facts ) );
	}

	public function test_REQ_273_a_run_with_no_facts_is_not_a_pass(): void {
		$results = Smoke::evaluate( array() );
		$this->assertFalse( Smoke::passed( $results ) );
		foreach ( $results as $row ) {
			$this->assertSame( Smoke::SKIP, $row['status'] );
		}
	}

	public function test_REQ_273_command_exits_non_zero_and_names_the_failed_check(): void {
		$facts               = $this->good();
		$facts['stylesheet'] = 'twentytwentyfour';
		$file                = tempnam( sys_get_temp_dir(), 'wiw-facts' );
		file_put_contents( $file, (string) json_encode( $facts ) );
		$out  = array();
		$code = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( dirname( __DIR__, 2 ) . '/deploy/smoke-check.php' ) . ' --facts=' . escapeshellarg( $file ), $out, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- runs the deploy command under test.
		unlink( $file );
		$text = implode( "\n", $out );
		$this->assertSame( 1, $code );
		$this->assertStringContainsString( 'FAIL  Child theme whoiswho-child is active', $text );
		$this->assertStringContainsString( 'RESULT: FAILED', $text );
	}

	public function test_REQ_273_command_exits_zero_on_a_healthy_site(): void {
		$file = tempnam( sys_get_temp_dir(), 'wiw-facts' );
		file_put_contents( $file, (string) json_encode( $this->good() ) );
		$code = 1;
		$out  = array();
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( dirname( __DIR__, 2 ) . '/deploy/smoke-check.php' ) . ' --facts=' . escapeshellarg( $file ), $out, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- runs the deploy command under test.
		unlink( $file );
		$this->assertSame( 0, $code, implode( "\n", $out ) );
	}
}
