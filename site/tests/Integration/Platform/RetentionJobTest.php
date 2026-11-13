<?php
/**
 * The scheduled data-retention job (REQ-063). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Core\Platform\Retention\RetentionJob;
use WhoIsWho\Tests\TestCase;

final class RetentionJobTest extends TestCase {

	private const NOW = 1794000000; // 2026-11-06 or so; only differences matter.

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		Schema::create_table( ConsentRecorder::TABLE, ConsentRecorder::TABLE_DEFINITION );
		Schema::create_table( 'wiw_email_events', EmailLogStore::TABLE_DEFINITION );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'wiw_consents' ); // phpcs:ignore WordPress.DB
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'wiw_email_events' ); // phpcs:ignore WordPress.DB
		delete_option( RetentionJob::OPTION_PERIODS );
	}

	private function job(): RetentionJob {
		return new RetentionJob( static fn (): int => self::NOW );
	}

	private function ago( int $days ): string {
		return gmdate( 'Y-m-d H:i:s', self::NOW - $days * 86400 );
	}

	private function consent( string $email, ?string $revoked ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB
			$wpdb->prefix . 'wiw_consents',
			array(
				'email'          => $email,
				'email_hash'     => ConsentRecorder::hash_email( $email ),
				'purpose'        => 'stories_newsletter',
				'policy_version' => '1',
				'granted_at'     => $this->ago( 900 ),
				'revoked_at'     => $revoked,
			)
		);
	}

	private function rows( string $table ): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . $table ); // phpcs:ignore WordPress.DB
	}

	public function test_REQ_063_does_nothing_until_a_period_is_set(): void {
		$this->consent( 'a@example.com', $this->ago( 3000 ) );
		$report = $this->job()->run( false );
		$this->assertSame( 1, $this->rows( 'wiw_consents' ) );
		$this->assertSame( array(), array_filter( $report ) );
	}

	public function test_REQ_063_retention_job_anonymises_expired_data(): void {
		update_option(
			RetentionJob::OPTION_PERIODS,
			array(
				'consent_life' => 365,
				'log_life'     => 30,
			)
		);
		$this->consent( 'old@example.com', $this->ago( 400 ) );
		$this->consent( 'recent@example.com', $this->ago( 10 ) );
		$this->consent( 'active@example.com', null );
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB
			$wpdb->prefix . 'wiw_email_events',
			array(
				'message_id'    => 'm1',
				'event'         => 'sent',
				'recipient_ref' => str_repeat( 'a', 64 ),
				'type'          => 'txn',
				'result'        => 'ok',
				'created_at'    => $this->ago( 40 ),
			)
		);
		$this->job()->run( false );
		$this->assertSame( 2, $this->rows( 'wiw_consents' ), 'Only the consent revoked long ago goes.' );
		$this->assertSame( 0, $this->rows( 'wiw_email_events' ) );
	}

	public function test_REQ_063_dry_run_counts_without_changing(): void {
		update_option( RetentionJob::OPTION_PERIODS, array( 'consent_life' => 365 ) );
		$this->consent( 'old@example.com', $this->ago( 400 ) );
		$report = $this->job()->run( true );
		$this->assertSame( 1, $report['consent_life'] );
		$this->assertSame( 1, $this->rows( 'wiw_consents' ) );
	}

	public function test_REQ_063_comment_ip_is_cleared_when_old_and_other_comments_keep_their_text(): void {
		update_option( RetentionJob::OPTION_PERIODS, array( 'log_life' => 30 ) );
		$post = wp_insert_post(
			array(
				'post_title'  => 'x',
				'post_status' => 'publish',
			)
		);
		$old  = wp_insert_comment(
			array(
				'comment_post_ID'      => $post,
				'comment_author'       => 'Ada',
				'comment_author_email' => 'ada@example.com',
				'comment_author_IP'    => '203.0.113.9',
				'comment_content'      => 'Hello',
				'comment_date_gmt'     => $this->ago( 60 ),
				'comment_date'         => $this->ago( 60 ),
				'comment_approved'     => 1,
			)
		);
		$this->job()->run( false );
		$comment = get_comment( $old );
		$this->assertSame( '', $comment->comment_author_IP );
		$this->assertSame( 'Hello', $comment->comment_content );
		$this->assertSame( 'ada@example.com', $comment->comment_author_email );
	}

	public function test_REQ_063_every_retention_class_in_use_has_a_rule_or_is_kept_on_purpose(): void {
		$classes = array();
		foreach ( ( new PiiCatalogue() )->entries() as $entry ) {
			$classes[ $entry['retention'] ] = true;
		}
		foreach ( array_keys( $classes ) as $class ) {
			$this->assertContains( $class, RetentionJob::known_classes(), 'Retention class ' . $class . ' is not accounted for.' );
		}
	}
}
