<?php
/**
 * Protection of the public support forms (REQ-122, REQ-081): bot challenge, form token and throttles run before any item
 * is created, and replies do not reveal whether an email or profile exists.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit\Compliance;

use WhoIsWho\Core\Compliance\Forms\PublicSubmission;
use WhoIsWho\Core\Compliance\Forms\TurnstileChallenge;
use WhoIsWho\Core\Platform\Forms\FormGate;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\FormToken;
use WhoIsWho\Core\Platform\Forms\NullChallenge;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Tests\Support\MemoryStore;
use WhoIsWho\Tests\TestCase;

final class PublicSubmissionTest extends TestCase {

	private int $now = 1000000;

	/**
	 * Items the creator made.
	 *
	 * @var int
	 */
	private int $created = 0;

	/**
	 * Build the handler with a stub challenge.
	 *
	 * @param bool $solved Whether the stub says the challenge was solved.
	 */
	private function handler( bool $solved = true ): PublicSubmission {
		$clock   = fn (): int => $this->now;
		$token   = new FormToken( 'secret', $clock );
		$limiter = new RateLimiter(
			new MemoryStore(),
			'secret',
			array(
				'default' => array(
					'ip'      => array( 2, 600 ),
					'subject' => array( 5, 600 ),
				),
			),
			$clock
		);
		return new PublicSubmission( new TurnstileChallenge( static fn ( string $response ): bool => $solved && 'good' === $response ), new FormGate( $token, $limiter ) );
	}

	private function token(): string {
		$token      = ( new FormToken( 'secret', fn (): int => $this->now ) )->issue( 'correction' );
		$this->now += 10;
		return $token;
	}

	/**
	 * Submit a correction form.
	 *
	 * @param PublicSubmission $handler  Handler.
	 * @param array<string,string> $fields Fields.
	 * @param string           $ip       IP.
	 * @param string           $email    Email.
	 * @return array<string, mixed>
	 */
	private function submit( PublicSubmission $handler, array $fields, string $ip = '203.0.113.5', string $email = 'a@example.test' ): array {
		return $handler->handle(
			'correction',
			new FormInput( $fields ),
			$ip,
			$email,
			function (): string {
				++$this->created;
				return 'item';
			}
		);
	}

	public function test_REQ_122_a_good_submission_creates_one_item(): void {
		$result = $this->submit(
			$this->handler(),
			array(
				NullChallenge::TOKEN_FIELD         => $this->token(),
				TurnstileChallenge::RESPONSE_FIELD => 'good',
			)
		);
		$this->assertSame( PublicSubmission::RECEIVED, $result['status'] );
		$this->assertSame( 'item', $result['item'] );
		$this->assertSame( 1, $this->created );
	}

	public function test_REQ_122_over_limit_shows_try_later_and_creates_no_item(): void {
		$handler = $this->handler();
		for ( $i = 0; $i < 2; $i++ ) {
			$this->submit(
				$handler,
				array(
					NullChallenge::TOKEN_FIELD         => $this->token(),
					TurnstileChallenge::RESPONSE_FIELD => 'good',
				),
				'203.0.113.5',
				'user' . $i . '@example.test'
			);
		}
		$this->assertSame( 2, $this->created );
		$third = $this->submit(
			$handler,
			array(
				NullChallenge::TOKEN_FIELD         => $this->token(),
				TurnstileChallenge::RESPONSE_FIELD => 'good',
			),
			'203.0.113.5',
			'user9@example.test'
		);
		$this->assertSame( PublicSubmission::TRY_LATER, $third['status'] );
		$this->assertStringContainsString( 'try again later', $third['message'] );
		$this->assertSame( 2, $this->created, 'no item for the throttled request' );
		$this->assertNull( $third['item'] );
	}

	public function test_REQ_081_unsolved_challenge_refused(): void {
		$result = $this->submit(
			$this->handler(),
			array(
				NullChallenge::TOKEN_FIELD         => $this->token(),
				TurnstileChallenge::RESPONSE_FIELD => 'bad',
			)
		);
		$this->assertSame( 'challenge_failed', $result['outcome'] );
		$this->assertSame( 0, $this->created );

		$missing = $this->submit( $this->handler(), array( NullChallenge::TOKEN_FIELD => $this->token() ) );
		$this->assertSame( 'challenge_missing', $missing['outcome'] );
		$this->assertSame( 0, $this->created );
	}

	public function test_REQ_122_missing_form_token_refused(): void {
		$none = $this->submit( $this->handler(), array( TurnstileChallenge::RESPONSE_FIELD => 'good' ) );
		$this->assertSame( PublicSubmission::EXPIRED, $none['status'] );
		$this->assertSame( 0, $this->created );

		$stale_token = $this->token();
		$this->now  += 7200;
		$stale       = $this->submit(
			$this->handler(),
			array(
				NullChallenge::TOKEN_FIELD         => $stale_token,
				TurnstileChallenge::RESPONSE_FIELD => 'good',
			)
		);
		$this->assertSame( 'expired', $stale['outcome'] );
		$this->assertSame( 0, $this->created );
	}

	public function test_REQ_122_replies_do_not_reveal_whether_an_email_or_profile_exists(): void {
		$handler = $this->handler();
		$known   = $this->submit(
			$handler,
			array(
				NullChallenge::TOKEN_FIELD         => $this->token(),
				TurnstileChallenge::RESPONSE_FIELD => 'good',
			),
			'198.51.100.1',
			'known@example.test'
		);
		$unknown = $handler->handle(
			'correction',
			new FormInput(
				array(
					NullChallenge::TOKEN_FIELD         => $this->token(),
					TurnstileChallenge::RESPONSE_FIELD => 'good',
				)
			),
			'198.51.100.2',
			'never-seen@example.test',
			static fn (): ?string => null
		);
		$this->assertSame( $known['status'], $unknown['status'] );
		$this->assertSame( $known['message'], $unknown['message'] );
		$this->assertSame( FormGate::public_response()['message'], $known['message'] );
	}
}
