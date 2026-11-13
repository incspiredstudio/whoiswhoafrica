<?php
/**
 * Newsletter subscriber storage on the consent record (REQ-058, REQ-061, REQ-249). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Newsletter;

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Content\Newsletter\SubscriberRepository;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\TestCase;

final class SubscriberRepositoryTest extends TestCase {

	/**
	 * Current time the repository sees.
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
		delete_option( SettingsService::OPTION_PREFIX . SettingsService::POLICY_VERSION );
	}

	private function repo(): SubscriberRepository {
		return new SubscriberRepository( fn (): string => $this->now, new SettingsService( SettingsService::default_registry() ) );
	}

	public function test_REQ_058_newsletter_consent_stores_policy_version(): void {
		$repo   = $this->repo();
		$result = $repo->add_pending( 'Ada@Example.test' );
		$this->assertTrue( $result['created'] );
		$this->assertNotEmpty( $result['token'] );
		$row = $repo->find_by_email_hash( ConsentRecorder::hash_email( 'ada@example.test' ) );
		$this->assertNotNull( $row );
		$this->assertSame( 'stories_newsletter', $row['purpose'] );
		$this->assertSame( '2026-11-12 10:00:00', $row['granted_at'] );
		$this->assertNotSame( '', $row['policy_version'] );
		$this->assertNull( $row['confirmed_at'] );
		$this->assertSame( SubscriberRepository::hash_token( (string) $result['token'] ), $row['token_hash'], 'Only the hash of the token is stored.' );
		$this->assertNotSame( $result['token'], $row['token_hash'] );
		$this->assertFalse( $repo->is_subscribed( 'ada@example.test' ), 'Pending is not a subscription.' );
		$this->assertSame( 'ada@example.test', $repo->list( 'pending' )[0]['email'] );
	}

	public function test_REQ_058_confirm_works_once_and_unsubscribe_withdraws(): void {
		$repo  = $this->repo();
		$token = (string) $repo->add_pending( 'ada@example.test' )['token'];
		$this->assertFalse( $repo->confirm( 'wrong-token' ) );
		$this->now = '2026-11-12 11:00:00';
		$this->assertTrue( $repo->confirm( $token ) );
		$this->assertFalse( $repo->confirm( $token ), 'A token works once.' );
		$this->assertTrue( $repo->is_subscribed( 'ada@example.test' ) );
		$this->assertSame( '2026-11-12 11:00:00', $repo->find_by_email_hash( ConsentRecorder::hash_email( 'ada@example.test' ) )['confirmed_at'] );
		$this->assertCount( 1, $repo->list() );
		$this->now = '2026-11-13 09:00:00';
		$this->assertTrue( $repo->unsubscribe( 'ada@example.test' ) );
		$this->assertFalse( $repo->is_subscribed( 'ada@example.test' ) );
		$this->assertFalse( $repo->unsubscribe( 'ada@example.test' ) );
		$this->assertSame( array(), $repo->list() );
		$this->assertCount( 1, $repo->list( 'unsubscribed' ) );
	}

	public function test_REQ_061_duplicate_signup_does_not_create_second_row(): void {
		global $wpdb;
		$repo  = $this->repo();
		$first = $repo->add_pending( 'ada@example.test' );
		$again = $repo->add_pending( 'ADA@example.test' );
		$this->assertFalse( $again['created'] );
		$this->assertNotNull( $again['token'], 'An unconfirmed repeat gets a fresh confirmation.' );
		$this->assertNotSame( $first['token'], $again['token'] );
		$this->assertFalse( $repo->confirm( (string) $first['token'] ), 'The earlier token no longer works.' );
		$this->assertTrue( $repo->confirm( (string) $again['token'] ) );
		$this->assertNull( $repo->add_pending( 'ada@example.test' )['token'], 'A confirmed subscriber is not asked again.' );
		$this->assertSame( '1', (string) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SubscriberRepository::table() ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed name.
		$repo->unsubscribe( 'ada@example.test' );
		$back = $repo->add_pending( 'ada@example.test' );
		$this->assertNotNull( $back['token'], 'Someone who left may sign up again.' );
		$this->assertSame( '1', (string) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SubscriberRepository::table() ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed name.
	}

	public function test_REQ_058_a_bad_address_is_refused(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->repo()->add_pending( 'not-an-email' );
	}

	public function test_REQ_058_newsletter_columns_are_in_pii_catalogue(): void {
		$this->assertSame( array(), ( new PiiCatalogue() )->missing( array( 'wiw_consents.email', 'wiw_consents.email_hash', 'wiw_consents.token_hash' ) ) );
	}

	public function test_REQ_249_a_signup_never_enters_enquiries_or_customers(): void {
		global $wpdb;
		$tables = array( $wpdb->prefix . 'wiw_support_items', $wpdb->prefix . 'wiw_customers' );
		$counts = array();
		foreach ( $tables as $table ) {
			if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$counts[ $table ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed names.
			}
		}
		$this->repo()->add_pending( 'ada@example.test' );
		foreach ( $counts as $table => $before ) {
			$this->assertSame( $before, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ), $table ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed names.
		}
		$this->assertStringNotContainsString( 'Support', (string) file_get_contents( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/src/Content/Newsletter/SubscriberRepository.php' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
