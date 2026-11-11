<?php
/**
 * Signed links and the /link route logic (REQ-233, REQ-122).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Core\Platform\Links\LinkRoute;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Tests\Support\MemoryLinkStore;
use WhoIsWho\Tests\Support\MemoryStore;
use WhoIsWho\Tests\TestCase;

final class SignedLinksTest extends TestCase {

	/**
	 * Clock.
	 *
	 * @var \stdClass
	 */
	private \stdClass $clock;

	/**
	 * Store.
	 *
	 * @var MemoryLinkStore
	 */
	private MemoryLinkStore $store;

	protected function setUp(): void {
		parent::setUp();
		$this->clock    = new \stdClass();
		$this->clock->t = 1800000000;
		$this->store    = new MemoryLinkStore();
	}

	private function links(): SignedLinks {
		$clock = $this->clock;
		$links = new SignedLinks( $this->store, static fn (): int => $clock->t );
		$links->register(
			'verify_correction',
			static fn ( array $link ): string => 'Confirmed ' . $link['subject_id']
		);
		return $links;
	}

	public function test_REQ_233_link_expires_and_cannot_be_reused(): void {
		$links = $this->links();
		$token = $links->issue( 'verify_correction', 'support_item', '12', 'jane@example.org' );

		$this->assertSame( 'Confirmed 12', $links->use_link( $token ) );
		$this->assertNull( $links->use_link( $token ), 'Second use is refused.' );

		$late            = $links->issue( 'verify_correction', 'support_item', '13', null, 3600 );
		$this->clock->t += 3601;
		$this->assertNull( $links->use_link( $late ), 'Expired.' );

		$revoked = $links->issue( 'verify_correction', 'support_item', '14' );
		$links->revoke_for( 'verify_correction', 'support_item', '14' );
		$this->assertNull( $links->use_link( $revoked ), 'Revoked.' );

		$fresh = $links->reissue( 'verify_correction', 'support_item', '14' );
		$this->assertSame( 'Confirmed 14', $links->use_link( $fresh ) );
	}

	public function test_REQ_233_scanner_style_get_does_not_consume_link(): void {
		$links = $this->links();
		$route = new LinkRoute( $links, new RateLimiter( new MemoryStore(), 'k', LinkRoute::LIMITS + RateLimiter::LIMITS ) );
		$token = $links->issue( 'verify_correction', 'support_item', '7' );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertSame( 'confirm', $route->respond( 'GET', $token, '203.0.113.' . $i )['state'] );
		}
		$this->assertSame( 'done', $route->respond( 'POST', $token, '203.0.113.9' )['state'] );
		$this->assertSame( 'expired', $route->respond( 'GET', $token, '203.0.113.9' )['state'] );

		// Unknown, used and expired all look the same.
		$unknown = $route->respond( 'GET', 'does-not-exist-does-not-exist', '203.0.113.9' );
		$used    = $route->respond( 'POST', $token, '203.0.113.9' );
		$this->assertSame( $unknown, $used );
	}

	public function test_REQ_233_token_is_stored_only_as_hash(): void {
		$links = $this->links();
		$token = $links->issue( 'verify_correction', 'support_item', '7', 'jane@example.org' );

		$this->assertSame( 43, strlen( $token ) );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9_-]+$/', $token );
		$this->assertArrayHasKey( SignedLinks::hash( $token ), $this->store->rows );
		$this->assertStringNotContainsString( $token, (string) json_encode( $this->store->rows ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$this->assertStringNotContainsString( 'jane', $links->url( $token ), 'No name or address in the URL.' );
		$this->assertStringNotContainsString( '@', $links->url( $token ) );
	}

	public function test_REQ_122_link_attempts_are_rate_limited(): void {
		$links   = $this->links();
		$limiter = new RateLimiter( new MemoryStore(), 'k', LinkRoute::LIMITS + RateLimiter::LIMITS, fn (): int => 1800000000 );
		$route   = new LinkRoute( $links, $limiter );

		$states = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$states[] = $route->respond( 'GET', 'guessing-guessing-guessing', '203.0.113.' . $i )['state'];
		}
		$this->assertSame( 'limited', $states[11], 'Per-token limit applies even from fresh IPs.' );

		$single = new LinkRoute( $links, new RateLimiter( new MemoryStore(), 'k', LinkRoute::LIMITS + RateLimiter::LIMITS, fn (): int => 1800000000 ) );
		$last   = '';
		for ( $i = 0; $i < 31; $i++ ) {
			$last = $single->respond( 'GET', 'token-number-' . str_pad( (string) $i, 12, 'x' ), '198.51.100.7' )['state'];
		}
		$this->assertSame( 'limited', $last, 'Per-IP limit applies across tokens.' );
	}
}
