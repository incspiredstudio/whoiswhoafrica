<?php
/**
 * Requester email confirmation by a single-use signed link (REQ-233, REQ-232, REQ-157). Plain TestCase for the real support table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Confirm\RequesterConfirmation;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Core\Platform\Links\LinkRoute;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\Support\MemoryLinkStore;
use WhoIsWho\Tests\Support\MemoryStore;
use WhoIsWho\Tests\TestCase;

final class RequesterConfirmationTest extends TestCase {

	/**
	 * Clock.
	 *
	 * @var \stdClass
	 */
	private \stdClass $clock;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$wpdb->query( 'TRUNCATE TABLE ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$this->clock    = new \stdClass();
		$this->clock->t = 1800000000;
	}

	/**
	 * Links and a route sharing one in-memory store.
	 *
	 * @return array{0: SignedLinks, 1: LinkRoute, 2: RequesterConfirmation}
	 */
	private function stack(): array {
		$clock = $this->clock;
		$links = new SignedLinks( new MemoryLinkStore(), static fn (): int => $clock->t );
		$conf  = new RequesterConfirmation( new SupportItems(), $links );
		$links->register( RequesterConfirmation::PURPOSE, array( $conf, 'handle' ) );
		$route = new LinkRoute( $links, new RateLimiter( new MemoryStore(), 'k', LinkRoute::LIMITS + RateLimiter::LIMITS, static fn (): int => $clock->t ) );
		return array( $links, $route, $conf );
	}

	/**
	 * A stored correction.
	 *
	 * @return array<string, mixed>
	 */
	private function item( string $type = ItemType::CORRECTION ): array {
		return ( new SupportItems() )->create(
			array(
				'type'    => $type,
				'name'    => 'Ada',
				'email'   => 'ada@example.org',
				'message' => 'The city is wrong.',
			)
		);
	}

	private function token_of( string $url ): string {
		return (string) substr( $url, (int) strrpos( $url, '/' ) + 1 );
	}

	public function test_REQ_233_signed_link_is_single_use_and_get_does_not_consume_it(): void {
		list( , $route, $conf ) = $this->stack();
		$item                   = $this->item();
		$token                  = $this->token_of( $conf->issue( $item ) );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertSame( 'confirm', $route->respond( 'GET', $token, '203.0.113.' . $i )['state'] );
		}
		$this->assertNull( ( new SupportItems() )->get( (int) $item['id'] )['confirmed_at'], 'A GET confirms nothing.' );

		$done = $route->respond( 'POST', $token, '203.0.113.9' );
		$this->assertSame( 'done', $done['state'] );
		$this->assertStringContainsString( (string) $item['reference'], $done['message'] );
		$this->assertNotNull( ( new SupportItems() )->get( (int) $item['id'] )['confirmed_at'] );
		$this->assertSame( 'expired', $route->respond( 'POST', $token, '203.0.113.9' )['state'], 'The link works once.' );
	}

	public function test_REQ_232_correction_needs_verified_email_before_counting(): void {
		$item = $this->item();
		$this->assertSame( 'Email not confirmed', RequesterConfirmation::label( $item ) );
		$this->assertFalse( RequesterConfirmation::is_confirmed( $item ) );

		list( , $route, $conf ) = $this->stack();
		$route->respond( 'POST', $this->token_of( $conf->issue( $item ) ), '203.0.113.1' );
		$fresh = (array) ( new SupportItems() )->get( (int) $item['id'] );
		$this->assertSame( 'Email confirmed', RequesterConfirmation::label( $fresh ) );
		$this->assertTrue( RequesterConfirmation::is_confirmed( $fresh ) );
	}

	public function test_REQ_233_expired_link_shows_expired_page_and_changes_nothing(): void {
		list( , $route, $conf ) = $this->stack();
		$item                   = $this->item( ItemType::REMOVAL );
		$token                  = $this->token_of( $conf->issue( $item ) );
		$this->clock->t        += SignedLinks::DEFAULT_TTL + 5;

		$this->assertSame( 'expired', $route->respond( 'GET', $token, '203.0.113.1' )['state'] );
		$this->assertSame( 'expired', $route->respond( 'POST', $token, '203.0.113.1' )['state'] );
		$this->assertNull( ( new SupportItems() )->get( (int) $item['id'] )['confirmed_at'] );
	}

	public function test_REQ_233_a_new_link_replaces_the_old_one(): void {
		list( , $route, $conf ) = $this->stack();
		$item                   = $this->item( ItemType::REPORT );
		$first                  = $this->token_of( $conf->issue( $item ) );
		$second                 = $this->token_of( $conf->issue( $item ) );

		$this->assertSame( 'expired', $route->respond( 'POST', $first, '203.0.113.1' )['state'] );
		$this->assertSame( 'done', $route->respond( 'POST', $second, '203.0.113.1' )['state'] );
	}

	public function test_REQ_233_the_link_carries_no_name_or_address(): void {
		list( , , $conf ) = $this->stack();
		$url              = $conf->issue( $this->item() );
		$this->assertStringNotContainsString( 'ada', strtolower( $url ) );
		$this->assertStringNotContainsString( '@', $url );
	}

	public function test_REQ_157_an_enquiry_needs_no_confirmation(): void {
		list( , , $conf ) = $this->stack();
		$enquiry          = $this->item( ItemType::ENQUIRY );
		$this->assertSame( '', RequesterConfirmation::label( $enquiry ) );
		$this->expectException( \InvalidArgumentException::class );
		$conf->issue( $enquiry );
	}
}
