<?php
/**
 * Newsletter subscriber data in exports, erasure and retention (REQ-058, REQ-061). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Newsletter;

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Content\Newsletter\Privacy\SubscriberPrivacy;
use WhoIsWho\Core\Content\Newsletter\SubscriberRepository;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\TestCase;

final class SubscriberPrivacyTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		Schema::create_table( ConsentRecorder::TABLE, ConsentRecorder::TABLE_DEFINITION );
		$wpdb->query( 'DELETE FROM ' . SubscriberRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
	}

	protected function tearDown(): void {
		remove_all_filters( 'wiw_newsletter_unsubscribed_retention_days' );
		parent::tearDown();
	}

	private function repo( string $now = '2026-11-12 10:00:00' ): SubscriberRepository {
		return new SubscriberRepository( static fn (): string => $now, new SettingsService( SettingsService::default_registry() ) );
	}

	public function test_REQ_058_subscriber_appears_in_personal_data_export(): void {
		$privacy = new SubscriberPrivacy();
		$privacy->register();
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
		$this->assertArrayHasKey( SubscriberPrivacy::GROUP, $exporters );

		$repo  = $this->repo();
		$token = (string) $repo->add_pending( 'Ada@Example.test' )['token'];
		$repo->confirm( $token );
		$repo->add_pending( 'other@example.test' );

		$out = call_user_func( $exporters[ SubscriberPrivacy::GROUP ]['callback'], 'ada@example.test', 1 );
		$this->assertTrue( $out['done'] );
		$this->assertCount( 1, $out['data'] );
		$flat = (string) wp_json_encode( $out['data'] );
		$this->assertStringContainsString( 'ada@example.test', $flat );
		$this->assertStringContainsString( 'Subscribed', $flat );
		$this->assertStringNotContainsString( 'other@example.test', $flat, 'only the requester is exported' );
		$this->assertStringNotContainsString( $token, $flat, 'no token or token hash is exported' );
		$this->assertStringNotContainsString( SubscriberRepository::hash_token( $token ), $flat );

		$none = call_user_func( $exporters[ SubscriberPrivacy::GROUP ]['callback'], 'nobody@example.test', 1 );
		$this->assertSame( array(), $none['data'] );
		$entry = ( new PiiCatalogue() )->find( 'wiw_consents.email' );
		$this->assertSame( 'include', $entry['export'] ?? null, 'the catalogue says the address is exported' );
	}

	public function test_REQ_058_subscriber_is_erased_on_request(): void {
		( new SubscriberPrivacy() )->register();
		$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
		$this->assertArrayHasKey( SubscriberPrivacy::GROUP, $erasers );
		$repo = $this->repo();
		$repo->add_pending( 'ada@example.test' );
		$repo->add_pending( 'other@example.test' );

		$out = call_user_func( $erasers[ SubscriberPrivacy::GROUP ]['callback'], 'ada@example.test', 1 );
		$this->assertTrue( $out['items_removed'] );
		$this->assertFalse( $out['items_retained'] );
		$this->assertTrue( $out['done'] );
		$this->assertNull( $repo->find_by_email_hash( ConsentRecorder::hash_email( 'ada@example.test' ) ) );
		$this->assertNotNull( $repo->find_by_email_hash( ConsentRecorder::hash_email( 'other@example.test' ) ), 'other subscribers stay' );

		$again = call_user_func( $erasers[ SubscriberPrivacy::GROUP ]['callback'], 'ada@example.test', 1 );
		$this->assertFalse( $again['items_removed'] );
	}

	public function test_REQ_061_unsubscribed_addresses_are_purged_only_after_the_period_compliance_sets(): void {
		$privacy = new SubscriberPrivacy();
		$old     = $this->repo( gmdate( 'Y-m-d H:i:s', time() - 400 * DAY_IN_SECONDS ) );
		$old->add_pending( 'old@example.test' );
		$old->unsubscribe( 'old@example.test' );
		$recent = $this->repo( gmdate( 'Y-m-d H:i:s', time() - 5 * DAY_IN_SECONDS ) );
		$recent->add_pending( 'recent@example.test' );
		$recent->unsubscribe( 'recent@example.test' );
		$this->repo()->add_pending( 'pending@example.test' );

		$this->assertSame( 0, $privacy->purge_unsubscribed(), 'no period set: nothing is purged' );
		add_filter( 'wiw_newsletter_unsubscribed_retention_days', static fn (): int => 90 );
		$this->assertSame( 1, $privacy->purge_unsubscribed() );
		$repo = $this->repo();
		$this->assertNull( $repo->find_by_email_hash( ConsentRecorder::hash_email( 'old@example.test' ) ) );
		$this->assertNotNull( $repo->find_by_email_hash( ConsentRecorder::hash_email( 'recent@example.test' ) ) );
		$this->assertNotNull( $repo->find_by_email_hash( ConsentRecorder::hash_email( 'pending@example.test' ) ) );
	}
}
