<?php
/**
 * SendGuard: the newsletter's answer to "may this address get marketing mail?" and the unsubscribe that withdraws consent
 * (REQ-061, REQ-249). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Newsletter;

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Content\Newsletter\SendGuard;
use WhoIsWho\Core\Content\Newsletter\SubscriberRepository;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Platform\Mail\Marketing\Marketing;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\Support\MemoryLinkStore;
use WhoIsWho\Tests\TestCase;

final class SendGuardTest extends TestCase {

	/**
	 * Current time.
	 *
	 * @var string
	 */
	private string $now = '2026-11-12 10:00:00';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		\WhoIsWho\Core\Platform\Migrations\Schema::create_table( ConsentRecorder::TABLE, ConsentRecorder::TABLE_DEFINITION );
		$wpdb->query( 'DELETE FROM ' . SubscriberRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
	}

	protected function tearDown(): void {
		remove_all_filters( 'wiw_marketing_consent_checker' );
		remove_all_actions( 'wiw_marketing_unsubscribed' );
		parent::tearDown();
	}

	private function repo(): SubscriberRepository {
		return new SubscriberRepository( fn (): string => $this->now, new SettingsService( SettingsService::default_registry() ) );
	}

	private function subscribe( SubscriberRepository $repo, string $email ): void {
		$token = (string) $repo->add_pending( $email )['token'];
		$repo->confirm( $token );
	}

	public function test_REQ_061_unsubscribed_address_receives_no_marketing_email(): void {
		$repo  = $this->repo();
		$guard = new SendGuard( $repo );
		$this->subscribe( $repo, 'ada@example.test' );
		$this->assertTrue( $guard->canSend( 'Ada@Example.test' ) );
		$repo->unsubscribe( 'ada@example.test' );
		$this->assertFalse( $guard->canSend( 'ada@example.test' ) );
		$this->assertFalse( $guard->has_opted_in( 'ada@example.test' ) );
	}

	public function test_REQ_061_guard_refuses_unconfirmed_and_unknown_addresses(): void {
		$repo  = $this->repo();
		$guard = new SendGuard( $repo );
		$repo->add_pending( 'pending@example.test' );
		$this->assertFalse( $guard->canSend( 'pending@example.test' ) );
		$this->assertFalse( $guard->canSend( 'nobody@example.test' ) );
		$this->assertFalse( $guard->canSend( 'not-an-address' ) );
	}

	public function test_REQ_249_unsubscribe_link_records_time_and_keeps_the_record(): void {
		$repo = $this->repo();
		$this->subscribe( $repo, 'ada@example.test' );
		$guard = new SendGuard( $repo );
		$guard->register();
		$links = new SignedLinks( new MemoryLinkStore() );
		$gate  = new Marketing( $guard, $links, static fn ( string $a ): string => hash( 'sha256', strtolower( $a ) ) );
		$gate->register();
		$url   = $gate->unsubscribe_url( 'ada@example.test' );
		$token = basename( rtrim( $url, '/' ) );
		$this->assertNotNull( $links->use_link( $token ), 'The unsubscribe link is usable without logging in.' );
		$row = $repo->find_by_email_hash( ConsentRecorder::hash_email( 'ada@example.test' ) );
		$this->assertNotNull( $row, 'The record is kept.' );
		$this->assertNotNull( $row['revoked_at'] );
		$this->assertFalse( $guard->canSend( 'ada@example.test' ) );
		$this->assertFalse( $gate->may_send( 'ada@example.test' ) );
	}

	public function test_REQ_061_newsletter_message_has_one_click_unsubscribe(): void {
		$headers = Marketing::headers( 'https://example.test/link/abc' );
		$this->assertContains( 'List-Unsubscribe-Post: List-Unsubscribe=One-Click', $headers );
		$this->assertStringContainsString( 'List-Unsubscribe: <https://example.test/link/abc>', implode( "\n", $headers ) );
		( new SendGuard( $this->repo() ) )->register();
		$checker = apply_filters( 'wiw_marketing_consent_checker', new \WhoIsWho\Core\Platform\Mail\Marketing\DenyAllConsent() );
		$this->assertInstanceOf( SendGuard::class, $checker );
	}
}
