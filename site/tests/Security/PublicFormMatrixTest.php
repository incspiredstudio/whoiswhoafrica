<?php
/**
 * Pre-launch matrix over every public form (REQ-122, REQ-081, REQ-233; M5-SEC-04). It reads the real route table, so a form
 * added later without a rate limit or a bot check fails here. Forms that do not exist yet are listed in KNOWN_MISSING and the
 * test fails when one of them appears, so the list cannot go stale.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Security;

use PHPUnit\Framework\TestCase;
use WhoIsWho\Core\Compliance\Forms\FormRoutes;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Tests\Support\MemoryStore;

final class PublicFormMatrixTest extends TestCase {

	/**
	 * Forms in the brief that have no POST handler or template yet (data request is an M4 feature; comments use
	 * WordPress's own handler).
	 */
	private const KNOWN_MISSING = array( 'data_request' );

	/**
	 * The form keys of the real route table.
	 *
	 * @return string[]
	 */
	private function forms(): array {
		$routes = ( new \ReflectionClassConstant( FormRoutes::class, 'ROUTES' ) )->getValue();
		return array_values( array_unique( array_values( $routes ) ) );
	}

	private function source( string $relative ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/wp-content/' . $relative ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	public function test_REQ_122_every_public_form_rate_limited(): void {
		$forms = $this->forms();
		$this->assertGreaterThanOrEqual( 8, count( $forms ), 'enquiry, contact, get listed, claim, correct, remove, report, newsletter' );
		foreach ( $forms as $form ) {
			// Each form is held to its own configured limit (nomination has a wider per-email allowance than the default).
			$limits              = RateLimiter::LIMITS[ $form ] ?? RateLimiter::LIMITS['default'];
			list( $ip_max )      = $limits['ip'];
			list( $subject_max ) = $limits['subject'];
			$this->assertLessThanOrEqual( 10, $ip_max, "$form: no more than 10 attempts per IP" );
			$this->assertLessThanOrEqual( 8, $subject_max, "$form: no more than 8 attempts per address" );

			$limiter = new RateLimiter( new MemoryStore(), 'k', null, static fn (): int => 1000 );
			for ( $i = 1; $i <= $ip_max; $i++ ) {
				$this->assertTrue( $limiter->allow( $form, '203.0.113.9' ), "$form attempt $i from one IP is allowed" );
			}
			$this->assertFalse( $limiter->allow( $form, '203.0.113.9' ), "$form: attempt " . ( $ip_max + 1 ) . ' from one IP is throttled' );

			$limiter = new RateLimiter( new MemoryStore(), 'k', null, static fn (): int => 1000 );
			for ( $i = 1; $i <= $subject_max; $i++ ) {
				$this->assertTrue( $limiter->allow( $form, '198.51.100.' . $i, 'Same@Example.test' ), "$form attempt $i for one address is allowed" );
			}
			$this->assertFalse( $limiter->allow( $form, '198.51.100.99', 'same@example.test' ), "$form: attempt " . ( $subject_max + 1 ) . ' for one address is throttled across IPs and letter case' );
		}
	}

	public function test_REQ_122_every_handler_goes_through_the_protected_submission(): void {
		$code     = $this->source( 'plugins/whoiswho-core/src/Compliance/Forms/FormRoutes.php' );
		$handlers = preg_match_all( "/^\s*'[a-z_]+'\s*=> static fn/m", $code );
		$this->assertSame( count( $this->forms() ), $handlers, 'every route has exactly one handler' );
		foreach ( array( 'EnquirySubmission.php', 'RequestSubmission.php' ) as $file ) {
			$this->assertStringContainsString( 'PublicSubmission', $this->source( 'plugins/whoiswho-core/src/Compliance/Support/' . $file ), "$file checks the bot challenge and limits first" );
		}
		$this->assertStringContainsString( 'PublicSubmission', $this->source( 'plugins/whoiswho-core/src/Content/Newsletter/NewsletterSignup.php' ) );
		$this->assertStringContainsString( 'PublicSubmission', $this->source( 'plugins/whoiswho-core/src/Trust/ClaimIntake.php' ) );
		$this->assertStringContainsString( 'FormGate', $this->source( 'plugins/whoiswho-core/src/Compliance/Forms/PublicSubmission.php' ) );
	}

	public function test_REQ_081_every_public_form_has_bot_challenge(): void {
		$base  = dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho';
		$check = (string) file_get_contents( $base . '/template-parts/forms/bot-check.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->assertMatchesRegularExpression( '/<label for="wiw_website">/', $check, 'the challenge field is labelled' );
		$this->assertStringContainsString( 'tabindex="-1"', $check );

		$posting = array();
		foreach ( glob( $base . '/templates/*.php' ) ?: array() as $file ) {
			$code = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( 1 === preg_match( '/<form[^>]*method="post"/i', $code ) ) {
				$posting[] = basename( $file );
				$this->assertStringContainsString( 'forms/bot-check', $code, basename( $file ) . ' includes the bot check' );
			}
		}
		$this->assertGreaterThanOrEqual( 4, count( $posting ), 'claim, contact, enquiry, get-listed' );
	}

	public function test_REQ_081_forms_without_a_template_are_listed_and_still_missing(): void {
		// Correct, remove and report have handlers but no page template in the theme yet: this fails when one is
		// added, as a reminder to add it to the bot-check test above.
		$base = dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho/templates';
		foreach ( array( 'correct', 'correction', 'remove', 'removal', 'report' ) as $name ) {
			$this->assertFileDoesNotExist( $base . '/' . $name . '.php', "templates/$name.php now exists: it is covered by the bot-check scan, update this list" );
		}
		foreach ( self::KNOWN_MISSING as $form ) {
			$this->assertNotContains( $form, $this->forms(), "$form now has a route: add it to the rate-limit and bot-check scans and drop it from KNOWN_MISSING" );
		}
	}

	public function test_REQ_233_scanner_get_does_not_consume_link(): void {
		$route = $this->source( 'plugins/whoiswho-core/src/Platform/Links/LinkRoute.php' );
		$this->assertStringContainsString( 'peek(', $route, 'GET only looks at the link' );
		$this->assertSame( 1, substr_count( $route, 'use_link(' ), 'consumption happens in exactly one place (the POST branch)' );
		// The behavioural test is Unit\SignedLinksTest::test_REQ_233_scanner_style_get_does_not_consume_link.
	}
}
