<?php
/**
 * Rate limiter, form token and form gate (REQ-122, REQ-081, REQ-046).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Core\Platform\Forms\FormGate;
use WhoIsWho\Core\Platform\Forms\FormToken;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Tests\Support\MemoryStore;
use WhoIsWho\Tests\TestCase;

final class FormGateTest extends TestCase {

	private int $now = 1000000;

	private function limiter( MemoryStore $store ): RateLimiter {
		return new RateLimiter(
			$store,
			'secret',
			array(
				'default' => array(
					'ip'      => array( 3, 600 ),
					'subject' => array( 2, 600 ),
				),
			),
			fn (): int => $this->now
		);
	}

	public function test_REQ_122_rate_limit_blocks_after_threshold_per_ip_and_per_email(): void {
		$limiter = $this->limiter( new MemoryStore() );

		// Per IP: the fourth attempt in the window is blocked, a different IP is not.
		$this->assertTrue( $limiter->allow( 'claim', '203.0.113.5' ) );
		$this->assertTrue( $limiter->allow( 'claim', '203.0.113.5' ) );
		$this->assertTrue( $limiter->allow( 'claim', '203.0.113.5' ) );
		$this->assertFalse( $limiter->allow( 'claim', '203.0.113.5' ) );
		$this->assertTrue( $limiter->allow( 'claim', '203.0.113.9' ) );

		// Per email: the third attempt is blocked even from fresh IPs; case and spaces do not matter.
		$this->assertTrue( $limiter->allow( 'claim', '198.51.100.1', 'Jane@Example.org' ) );
		$this->assertTrue( $limiter->allow( 'claim', '198.51.100.2', ' jane@example.org ' ) );
		$this->assertFalse( $limiter->allow( 'claim', '198.51.100.3', 'jane@example.org' ) );

		// The window slides: after it passes, attempts are allowed again.
		$this->now += 601;
		$this->assertTrue( $limiter->allow( 'claim', '203.0.113.5' ) );
	}

	public function test_REQ_122_limiter_stores_no_raw_ip_or_email(): void {
		$store   = new MemoryStore();
		$limiter = $this->limiter( $store );
		$limiter->allow( 'claim', '203.0.113.5', 'jane@example.org' );

		$this->assertNotEmpty( $store->data );
		$dump = json_encode( $store->data );
		$this->assertStringNotContainsString( '203.0.113.5', (string) $dump );
		$this->assertStringNotContainsString( 'jane@example.org', (string) $dump );
		foreach ( array_keys( $store->data ) as $key ) {
			$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $key, 'keys are keyed hashes' );
		}
	}

	public function test_REQ_081_form_token_rejects_too_fast_and_expired_submissions(): void {
		$clock = fn (): int => $this->now;
		$token = new FormToken( 'signing-key', $clock );
		$value = $token->issue( 'claim' );

		$this->assertSame( FormToken::TOO_FAST, $token->verify( $value, 'claim' ), 'submitted instantly' );
		$this->now += 10;
		$this->assertSame( FormToken::OK, $token->verify( $value, 'claim' ) );
		$this->now += 4000;
		$this->assertSame( FormToken::EXPIRED, $token->verify( $value, 'claim' ), 'older than the lifetime' );
	}

	public function test_REQ_081_form_token_rejects_tampering_and_other_forms(): void {
		$token      = new FormToken( 'signing-key', fn (): int => $this->now );
		$value      = $token->issue( 'claim' );
		$this->now += 10;

		$this->assertSame( FormToken::INVALID, $token->verify( $value, 'enquiry' ), 'token for another form' );
		$this->assertSame( FormToken::INVALID, $token->verify( 'garbage', 'claim' ) );
		$this->assertSame( FormToken::INVALID, $token->verify( $value . 'x', 'claim' ), 'altered' );
		$this->assertSame( FormToken::INVALID, ( new FormToken( 'other-key', fn (): int => $this->now ) )->verify( $value, 'claim' ), 'signed with another key' );
	}

	public function test_REQ_046_gate_gives_the_same_public_response_whatever_happened(): void {
		$store      = new MemoryStore();
		$gate       = new FormGate( new FormToken( 'k', fn (): int => $this->now ), $this->limiter( $store ) );
		$token      = ( new FormToken( 'k', fn (): int => $this->now ) )->issue( 'lookup' );
		$this->now += 10;

		$this->assertSame( 'ok', $gate->check( 'lookup', $token, '203.0.113.5', 'a@example.org' ) );
		$this->assertSame( 'invalid', $gate->check( 'lookup', 'bad', '203.0.113.5' ) );
		$this->assertSame( FormGate::public_response(), FormGate::public_response(), 'one shape for every outcome' );
		$this->assertSame( array( 'status', 'message' ), array_keys( FormGate::public_response() ) );
	}
}
