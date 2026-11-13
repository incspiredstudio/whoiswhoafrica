<?php
/**
 * Commenter data in the PII catalogue, data-request export and erasure (REQ-258, REQ-059). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Content;

use WhoIsWho\Core\Content\Comments\CommentPrivacy;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Tests\WpTestCase;

final class CommentPrivacyTest extends WpTestCase {

	private function comment( string $email, string $name = 'Guest Zq' ): int {
		$post = self::factory()->post->create();
		return (int) wp_insert_comment(
			array(
				'comment_post_ID'      => $post,
				'comment_author'       => $name,
				'comment_author_email' => $email,
				'comment_author_IP'    => '203.0.113.9',
				'comment_content'      => 'A thoughtful comment.',
				'comment_approved'     => '1',
			)
		);
	}

	/**
	 * Run every registered exporter for an address and return all exported item data as one string.
	 *
	 * @param string $email Address.
	 */
	private function export_all( string $email ): string {
		CommentPrivacy::hooks();
		$out = array();
		foreach ( apply_filters( 'wp_privacy_personal_data_exporters', array() ) as $exporter ) {
			$page = 1;
			do {
				$result = call_user_func( $exporter['callback'], $email, $page );
				$out[]  = $result['data'];
				++$page;
			} while ( empty( $result['done'] ) );
		}
		return (string) wp_json_encode( $out );
	}

	public function test_REQ_258_export_for_email_includes_comments(): void {
		$this->comment( 'guest.zq@example.test' );
		$this->comment( 'someone.else@example.test', 'Other' );
		$export = $this->export_all( 'guest.zq@example.test' );
		$this->assertStringContainsString( 'A thoughtful comment.', $export );
		$this->assertStringContainsString( 'guest.zq@example.test', $export );
		$this->assertStringNotContainsString( 'someone.else', $export, 'only that address comments' );
	}

	public function test_REQ_258_erase_request_removes_or_anonymises_comments(): void {
		$id = $this->comment( 'guest.zq@example.test' );
		CommentPrivacy::hooks();
		$removed = false;
		foreach ( apply_filters( 'wp_privacy_personal_data_erasers', array() ) as $eraser ) {
			$result  = call_user_func( $eraser['callback'], 'guest.zq@example.test', 1 );
			$removed = $removed || ! empty( $result['items_removed'] );
		}
		$this->assertTrue( $removed );
		$comment = get_comment( $id );
		$this->assertSame( '', (string) $comment->comment_author_email, 'the address is gone' );
		$this->assertNotSame( 'Guest Zq', $comment->comment_author, 'the name is anonymised' );
		$this->assertNotSame( '203.0.113.9', $comment->comment_author_IP, 'the IP is anonymised' );
	}

	public function test_REQ_258_wpdiscuz_subscriptions_are_exported_and_erased(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wc_comments_subscription';
		$wpdb->query( "CREATE TABLE IF NOT EXISTS {$table} ( id int unsigned NOT NULL AUTO_INCREMENT, post_id int NOT NULL, email varchar(254) NOT NULL, add_date datetime NULL, PRIMARY KEY (id) )" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->insert(
			$table,
			array(
				'post_id'  => 5,
				'email'    => 'sub.zq@example.test',
				'add_date' => '2026-11-12 10:00:00',
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			array(
				'post_id'  => 6,
				'email'    => 'other.zq@example.test',
				'add_date' => '2026-11-12 10:00:00',
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$export = ( new CommentPrivacy() )->export( 'sub.zq@example.test' );
		$this->assertCount( 1, $export['data'] );
		$this->assertStringContainsString( 'sub.zq@example.test', (string) wp_json_encode( $export ) );
		$this->assertSame( array(), ( new CommentPrivacy() )->export( 'nobody@example.test' )['data'] );

		$this->assertTrue( ( new CommentPrivacy() )->erase( 'sub.zq@example.test' )['items_removed'] );
		$this->assertSame( 1, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ), 'only that address was deleted' ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$this->assertFalse( ( new CommentPrivacy() )->erase( 'sub.zq@example.test' )['items_removed'], 'a second erase finds nothing' );
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$this->assertSame( array(), ( new CommentPrivacy() )->export( 'sub.zq@example.test' )['data'], 'no wpDiscuz tables, no error' );
	}

	public function test_REQ_258_wpdiscuz_tables_are_in_the_pii_catalogue(): void {
		$names = array_column( ( new PiiCatalogue() )->entries(), 'name' );
		foreach ( array( 'wc_comments_subscription.email', 'wc_follow_users.user_email', 'wc_follow_users.follower_email', 'comments.comment_author_email' ) as $expected ) {
			$this->assertContains( $expected, $names );
		}
		foreach ( CommentPrivacy::TABLES as $table => $spec ) {
			foreach ( $spec['emails'] as $column ) {
				$this->assertContains( $table . '.' . $column, $names, 'every email column the privacy handler reads is catalogued' );
			}
		}
		$base      = dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/config/';
		$processor = array_column( require $base . 'processors.php', 'name' );
		$this->assertContains( 'wpDiscuz', $processor, 'wpDiscuz is in the processor register' );
		$this->assertContains( 'Akismet', $processor );
		$retention = require $base . 'retention.php';
		$this->assertArrayHasKey( 'comments_days', $retention, 'the retention schedule has a comments entry' );
	}
}
