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
		// The table exactly as wpDiscuz 7.7.0 creates it (includes/class.WpdiscuzDBManager.php).
		$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$table}`(`id` INT(11) NOT NULL AUTO_INCREMENT, `email` VARCHAR(100) NOT NULL, `subscribtion_id` INT(11) NOT NULL, `post_id` INT(11) NOT NULL, `subscribtion_type` VARCHAR(20) NOT NULL, `activation_key` VARCHAR(32) NOT NULL, `confirm` TINYINT DEFAULT 0, `subscription_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `imported_from` VARCHAR(25) NOT NULL DEFAULT '', PRIMARY KEY (`id`), KEY `subscribtion_id` (`subscribtion_id`), KEY `post_id` (`post_id`), KEY `confirm`(`confirm`), UNIQUE KEY `subscribe_unique_index` (`subscribtion_id`,`email`,`post_id`))" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		foreach ( array(
			'sub.zq@example.test'   => 5,
			'other.zq@example.test' => 6,
		) as $email => $post ) {
			$wpdb->insert(
				$table,
				array(
					'email'             => $email,
					'subscribtion_id'   => $post,
					'post_id'           => $post,
					'subscribtion_type' => 'post',
					'activation_key'    => md5( $email ),
					'subscription_date' => '2026-11-12 10:00:00',
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		$export = ( new CommentPrivacy() )->export( 'sub.zq@example.test' );
		$this->assertCount( 1, $export['data'] );
		$this->assertStringContainsString( 'sub.zq@example.test', (string) wp_json_encode( $export ) );
		$this->assertStringContainsString( '2026-11-12 10:00:00', (string) wp_json_encode( $export ), 'the subscription date column is the one wpDiscuz uses' );
		$this->assertSame( array(), ( new CommentPrivacy() )->export( 'nobody@example.test' )['data'] );

		$this->assertTrue( ( new CommentPrivacy() )->erase( 'sub.zq@example.test' )['items_removed'] );
		$this->assertSame( 1, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ), 'only that address was deleted' ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$this->assertFalse( ( new CommentPrivacy() )->erase( 'sub.zq@example.test' )['items_removed'], 'a second erase finds nothing' );
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$this->assertSame( array(), ( new CommentPrivacy() )->export( 'sub.zq@example.test' )['data'], 'no wpDiscuz tables, no error' );
	}

	public function test_REQ_059_wpdiscuz_follows_are_exported_with_names_and_erased(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wc_follow_users';
		// The table exactly as wpDiscuz 7.7.0 creates it.
		$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$table}` (`id` int(11) NOT NULL AUTO_INCREMENT, `post_id` int(11) NOT NULL DEFAULT '0', `user_id` int(11) NOT NULL DEFAULT '0', `user_email` varchar(100) NOT NULL, `user_name` varchar(255) NOT NULL, `follower_id` int(11) NOT NULL DEFAULT '0', `follower_email` varchar(100) NOT NULL, `follower_name` varchar(255) NOT NULL, `activation_key` varchar(32) NOT NULL, `confirm` tinyint(1) NOT NULL DEFAULT '0', `follow_timestamp` int(11) NOT NULL, `follow_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), UNIQUE KEY `follow_unique_key` (`user_email`, `follower_email`))" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->insert(
			$table,
			array(
				'post_id'          => 5,
				'user_email'       => 'followed.zq@example.test',
				'user_name'        => 'Followed Zq',
				'follower_email'   => 'follower.zq@example.test',
				'follower_name'    => 'Follower Zq',
				'activation_key'   => 'k',
				'follow_timestamp' => 1,
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		try {
			$export = (string) wp_json_encode( ( new CommentPrivacy() )->export( 'follower.zq@example.test' ) );
			$this->assertStringContainsString( 'Follower Zq', $export );
			$this->assertStringContainsString( 'Followed Zq', $export );
			$this->assertTrue( ( new CommentPrivacy() )->erase( 'follower.zq@example.test' )['items_removed'] );
			$this->assertSame( 0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		} finally {
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		}
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
