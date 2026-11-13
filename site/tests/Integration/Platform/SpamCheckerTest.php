<?php
/**
 * The spam checker is honeypot-only and sends nothing to a third party (REQ-081, REQ-122). Needs the WordPress test suite for
 * the filter and the HTTP guard.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Forms\FormGate;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\NullChallenge;
use WhoIsWho\Core\Platform\Forms\Spam\HoneypotSpamChecker;
use WhoIsWho\Core\Platform\Forms\Spam\SpamChecker;
use WhoIsWho\Core\Platform\Forms\Spam\SpamCheckers;
use WhoIsWho\Tests\WpTestCase;

/**
 * Spam checker.
 */
final class SpamCheckerTest extends WpTestCase {

	public function test_REQ_081_spam_checker_defaults_to_honeypot_and_sends_nothing(): void {
		$requests = 0;
		add_filter(
			'pre_http_request',
			static function ( $pre ) use ( &$requests ) {
				++$requests;
				return $pre;
			}
		);
		$checker = SpamCheckers::for_site();
		$this->assertInstanceOf( HoneypotSpamChecker::class, $checker );

		$this->assertSame( SpamChecker::HAM, $checker->check( new FormInput( array( 'message' => 'Hello, please correct my profile.' ) ), 'correction' ) );
		$this->assertSame( SpamChecker::SPAM, $checker->check( new FormInput( array( NullChallenge::HONEYPOT_FIELD => 'http://spam.example' ) ), 'correction' ) );
		$this->assertSame( 0, $requests, 'no data leaves the site for spam checking' );
	}

	public function test_REQ_081_a_spam_verdict_is_not_revealed_to_the_sender(): void {
		// The visitor-facing answer is one fixed response for every outcome, so a bot learns nothing from it.
		$this->assertSame( FormGate::public_response(), FormGate::public_response() );
		$this->assertArrayNotHasKey( 'spam', FormGate::public_response() );
		$this->assertStringNotContainsStringIgnoringCase( 'spam', FormGate::public_response()['message'] );
	}

	public function test_REQ_122_no_third_party_spam_service_is_wired(): void {
		$files = (array) glob( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/src/Platform/Forms/Spam/*.php' );
		$this->assertNotEmpty( $files );
		foreach ( $files as $file ) {
			$source = (string) file_get_contents( $file );
			$this->assertDoesNotMatchRegularExpression( '/akismet|wp_remote_|curl_init|file_get_contents\(\s*[\'"]http/i', $this->code_only( $source ), basename( $file ) );
		}
		$this->assertFileDoesNotExist( dirname( __DIR__, 3 ) . '/wp-content/plugins/akismet' );
	}

	public function test_REQ_122_another_checker_can_be_supplied_only_through_the_filter(): void {
		$custom = new class() implements SpamChecker {
			public function check( FormInput $input, string $form ): string {
				return self::SPAM;
			}
		};
		add_filter( 'wiw_spam_checker', static fn () => $custom );
		$this->assertSame( $custom, SpamCheckers::for_site() );
		remove_all_filters( 'wiw_spam_checker' );
		add_filter( 'wiw_spam_checker', static fn () => 'not a checker' );
		$this->assertInstanceOf( HoneypotSpamChecker::class, SpamCheckers::for_site(), 'a bad filter value falls back to the default' );
		remove_all_filters( 'wiw_spam_checker' );
	}

	/**
	 * Source without comments, so the explanation in a docblock may name the service it does not use.
	 *
	 * @param string $source PHP source.
	 */
	private function code_only( string $source ): string {
		$out = '';
		foreach ( token_get_all( $source ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$out .= is_array( $token ) ? $token[1] : $token;
		}
		return $out;
	}
}
