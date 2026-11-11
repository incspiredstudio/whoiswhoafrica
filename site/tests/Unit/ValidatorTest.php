<?php
/**
 * Validator base, honeypot and bot challenge (REQ-081, REQ-122, REQ-046).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Core\Platform\Forms\BotChallenge;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\FormToken;
use WhoIsWho\Core\Platform\Forms\NullChallenge;
use WhoIsWho\Core\Platform\Forms\Validator;
use WhoIsWho\Tests\TestCase;

final class ValidatorTest extends TestCase {

	private function validator(): Validator {
		return new class() extends Validator {
			public function form(): string {
				return 'contact';
			}

			protected function fields(): array {
				return array(
					'name'    => array(
						'type'     => 'name',
						'required' => true,
						'max'      => 20,
					),
					'email'   => array(
						'type'     => 'email',
						'required' => true,
					),
					'message' => array(
						'type' => 'textarea',
						'max'  => 30,
					),
				);
			}
		};
	}

	public function test_REQ_081_honeypot_rejects_bot_submission(): void {
		$clock     = new \stdClass();
		$clock->t  = 1000000;
		$token     = new FormToken( 'k', static fn (): int => $clock->t );
		$issued    = $token->issue( 'contact' );
		$clock->t += 10;
		$now       = $clock->t;
		$challenge = new NullChallenge( $token );

		$human = new FormInput( array( NullChallenge::TOKEN_FIELD => $issued ) );
		$this->assertSame( 'ok', $challenge->verify( $human, 'contact' ) );

		$bot = new FormInput(
			array(
				NullChallenge::TOKEN_FIELD    => $issued,
				NullChallenge::HONEYPOT_FIELD => 'http://spam.example',
			)
		);
		$this->assertSame( 'honeypot', $challenge->verify( $bot, 'contact' ) );

		// Too fast and for the wrong form are also refused.
		$fast = new FormToken( 'k', fn (): int => $now );
		$this->assertSame( 'too_fast', ( new NullChallenge( $fast ) )->verify( new FormInput( array( NullChallenge::TOKEN_FIELD => $fast->issue( 'contact' ) ) ), 'contact' ) );
		$this->assertSame( 'invalid', $challenge->verify( $human, 'claim' ) );
	}

	public function test_REQ_122_validator_enforces_length_limits_and_unicode_names(): void {
		$validator = $this->validator();

		$ok = $validator->validate(
			new FormInput(
				array(
					'name'    => "  Chiamaka  Nwọsu\x00 ",
					'email'   => 'a@example.org',
					'message' => 'Hello',
				)
			)
		);
		$this->assertTrue( $ok->is_valid() );
		$this->assertSame( 'Chiamaka Nwọsu', $ok->value( 'name' ) );

		foreach ( array( 'Zoë Müller-Åberg', "N'Golo Kanté", 'Ṣadé Ọlá', 'Мария' ) as $name ) {
			$input = new FormInput(
				array(
					'name'  => $name,
					'email' => 'a@example.org',
				)
			);
			$this->assertTrue( $validator->validate( $input )->is_valid(), $name );
		}

		$bad = $validator->validate(
			new FormInput(
				array(
					'name'    => str_repeat( 'ọ', 21 ),
					'email'   => 'not-an-email',
					'message' => str_repeat( 'x', 31 ),
				)
			)
		);
		$this->assertSame(
			array(
				'name'    => 'too_long',
				'email'   => 'invalid_email',
				'message' => 'too_long',
			),
			$bad->errors()
		);

		$input   = new FormInput(
			array(
				'name' => '12345',
				'tags' => array( 'x' ),
			)
		);
		$missing = $validator->validate( $input );
		$this->assertSame( 'invalid_name', $missing->errors()['name'] );
		$this->assertSame( 'required', $missing->errors()['email'] );
	}

	public function test_REQ_081_bot_challenge_is_swappable(): void {
		$swap = new class() implements BotChallenge {
			public function verify( FormInput $input, string $form ): string {
				return 'cf-turnstile-response' === $input->raw( 'cf-turnstile-response' ) ? self::OK : 'challenge_failed';
			}
		};
		$this->assertSame( 'ok', $swap->verify( new FormInput( array( 'cf-turnstile-response' => 'cf-turnstile-response' ) ), 'contact' ) );
		$this->assertSame( 'challenge_failed', $swap->verify( new FormInput( array() ), 'contact' ) );
		$this->assertInstanceOf( BotChallenge::class, new NullChallenge( new FormToken( 'k' ) ) );
	}
}
