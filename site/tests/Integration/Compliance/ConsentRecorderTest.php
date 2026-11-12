<?php
/**
 * The consent recorder (REQ-058, REQ-061, REQ-063). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\TestCase;

final class ConsentRecorderTest extends TestCase {

	/**
	 * Current time the recorder sees.
	 *
	 * @var string
	 */
	private string $now = '2026-11-11 10:00:00';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		Schema::create_table( 'wiw_consents', ConsentRecorder::TABLE_DEFINITION );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'wiw_consents' ); // phpcs:ignore WordPress.DB
		delete_option( SettingsService::OPTION_PREFIX . SettingsService::POLICY_VERSION );
	}

	private function recorder(): ConsentRecorder {
		return new ConsentRecorder( fn (): string => $this->now, new SettingsService( SettingsService::default_registry() ) );
	}

	public function test_REQ_058_consent_stores_timestamp_and_policy_version(): void {
		update_option( SettingsService::OPTION_PREFIX . SettingsService::POLICY_VERSION, '2026-11' );
		$row = $this->recorder()->grant( 'Ada@Example.com ', ConsentRecorder::STORIES_NEWSLETTER, 'newsletter form' );
		$this->assertSame( '2026-11-11 10:00:00', $row['granted_at'] );
		$this->assertSame( '2026-11', $row['policy_version'] );
		$this->assertSame( 'ada@example.com', $row['email'] );
		$this->assertNull( $row['revoked_at'] );
		$this->assertSame( 'newsletter form', $row['source'] );
	}

	public function test_REQ_058_policy_version_defaults_when_unset(): void {
		$row = $this->recorder()->grant( 'a@example.com', ConsentRecorder::ENQUIRY );
		$this->assertSame( '1', $row['policy_version'] );
	}

	public function test_REQ_061_marketing_consent_is_separate_purpose(): void {
		$r = $this->recorder();
		$r->grant( 'a@example.com', ConsentRecorder::ENQUIRY );
		$this->assertTrue( $r->has_consent( 'a@example.com', ConsentRecorder::ENQUIRY ) );
		$this->assertFalse( $r->has_consent( 'a@example.com', ConsentRecorder::MARKETING_EMAIL ), 'Another consent is not marketing consent.' );
		$this->assertFalse( $r->has_consent( 'a@example.com', ConsentRecorder::STORIES_NEWSLETTER ) );
		$r->grant( 'a@example.com', ConsentRecorder::MARKETING_EMAIL );
		$this->assertTrue( $r->has_consent( 'a@example.com', ConsentRecorder::MARKETING_EMAIL ) );
		$this->assertCount( 2, $r->for_email( 'A@example.com' ) );
	}

	public function test_REQ_061_revoke_marks_time_and_repeat_grant_creates_no_second_row(): void {
		$r = $this->recorder();
		$r->grant( 'a@example.com', ConsentRecorder::MARKETING_EMAIL );
		$r->grant( 'A@example.com', ConsentRecorder::MARKETING_EMAIL );
		$this->assertCount( 1, $r->for_email( 'a@example.com' ) );
		$this->now = '2026-11-12 09:00:00';
		$this->assertTrue( $r->revoke( 'a@example.com', ConsentRecorder::MARKETING_EMAIL ) );
		$this->assertFalse( $r->has_consent( 'a@example.com', ConsentRecorder::MARKETING_EMAIL ) );
		$this->assertSame( '2026-11-12 09:00:00', $r->find( 'a@example.com', ConsentRecorder::MARKETING_EMAIL )['revoked_at'] );
		$this->assertFalse( $r->revoke( 'a@example.com', ConsentRecorder::MARKETING_EMAIL ), 'Already revoked.' );
		$this->now = '2026-11-13 09:00:00';
		$again     = $r->grant( 'a@example.com', ConsentRecorder::MARKETING_EMAIL );
		$this->assertNull( $again['revoked_at'] );
		$this->assertSame( '2026-11-13 09:00:00', $again['granted_at'] );
		$this->assertCount( 1, $r->for_email( 'a@example.com' ) );
	}

	public function test_REQ_058_unknown_purpose_or_bad_email_is_refused(): void {
		$r = $this->recorder();
		try {
			$r->grant( 'a@example.com', 'selling_your_data' );
			$this->fail( 'Unknown purpose accepted.' );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( array(), $r->for_email( 'a@example.com' ) );
		}
		$this->expectException( \InvalidArgumentException::class );
		$r->grant( 'not an email', ConsentRecorder::ENQUIRY );
	}

	public function test_REQ_058_consents_in_pii_catalogue(): void {
		$c = new PiiCatalogue();
		$this->assertSame( array(), $c->missing( array( 'wiw_consents.email', 'wiw_consents.email_hash' ) ) );
	}
}
