<?php
/**
 * Comment spam protection: honeypot, per-IP rate limit and the optional outside filter (REQ-258, REQ-122). Needs the WordPress
 * test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Comments;

use WhoIsWho\Core\Content\Comments\CommentPolicy;
use WhoIsWho\Core\Content\Comments\CommentSpamGuard;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Tests\Support\MemoryStore;
use WhoIsWho\Tests\TestCase;

final class CommentSpamGuardTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		wp_set_current_user( 0 );
		add_filter( 'wp_is_comment_flood', '__return_false', 99 );
		( new CommentPolicy() )->register();
		$_POST = array( CommentPolicy::CONSENT_FIELD => '1' );
	}

	protected function tearDown(): void {
		$_POST = array();
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
		}
		$this->posts = array();
		delete_option( CommentSpamGuard::SETTING );
		remove_filter( 'wp_is_comment_flood', '__return_false', 99 );
		foreach ( array( 'comment_moderation', 'comment_previously_approved', 'comment_registration', 'require_name_email', 'show_avatars', 'comments_notify', 'default_ping_status' ) as $option ) {
			remove_all_filters( 'pre_option_' . $option );
		}
		remove_all_filters( 'wp_die_handler' );
		remove_all_filters( 'comments_open' );
		remove_all_filters( 'preprocess_comment' );
		remove_all_filters( 'pre_comment_approved' );
		remove_all_filters( 'get_avatar_url' );
		remove_all_filters( 'get_comment_author_email' );
		remove_all_filters( 'wiw_comment_is_spam' );
		parent::tearDown();
	}

	private function guard( int $limit = 5 ): CommentSpamGuard {
		$limiter = new RateLimiter(
			new MemoryStore(),
			'secret',
			array(
				'comment' => array(
					'ip'      => array( $limit, 600 ),
					'subject' => array( 100, 600 ),
				),
				'default' => array(
					'ip'      => array( 100, 600 ),
					'subject' => array( 100, 600 ),
				),
			)
		);
		$guard   = new CommentSpamGuard( $limiter );
		$guard->register();
		return $guard;
	}

	private function story(): int {
		$id            = (int) wp_insert_post(
			array(
				'post_title'   => 'A story',
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_content' => 'Body.',
			)
		);
		$this->posts[] = $id;
		return $id;
	}

	/**
	 * Submit a guest comment the way the comment form does.
	 *
	 * @param int $post_id Post.
	 * @return \WP_Comment|\WP_Error
	 */
	private function submit( int $post_id ) {
		return wp_handle_comment_submission(
			array(
				'comment_post_ID' => $post_id,
				'author'          => 'Ada Obi',
				'email'           => 'ada@example.test',
				'comment'         => 'A thoughtful comment ' . wp_generate_password( 8, false ),
			)
		);
	}

	public function test_REQ_258_honeypot_comment_is_not_published(): void {
		$this->guard();
		$post                                = $this->story();
		$_POST[ CommentSpamGuard::HONEYPOT ] = 'http://spam.example';
		$comment                             = $this->submit( $post );
		$this->assertInstanceOf( \WP_Comment::class, $comment );
		$this->assertSame( 'spam', (string) $comment->comment_approved, 'marked spam, not held in the queue' );
		$this->assertSame(
			array(),
			get_comments(
				array(
					'post_id' => $post,
					'status'  => 'approve',
				)
			)
		);
		$this->assertSame(
			array(),
			get_comments(
				array(
					'post_id' => $post,
					'status'  => 'hold',
				)
			)
		);

		unset( $_POST[ CommentSpamGuard::HONEYPOT ] );
		$ok = $this->submit( $post );
		$this->assertSame( '0', (string) $ok->comment_approved, 'a clean comment is held for a moderator as before' );
	}

	public function test_REQ_258_flagged_by_the_outside_filter_is_spam_only_when_the_setting_is_on(): void {
		$this->guard();
		add_filter( 'wiw_comment_is_spam', '__return_true' );
		$post = $this->story();
		$this->assertSame( '0', (string) $this->submit( $post )->comment_approved, 'the filter is ignored while the setting is off' );
		update_option( CommentSpamGuard::SETTING, '1' );
		$this->assertSame( 'spam', (string) $this->submit( $post )->comment_approved );
	}

	public function test_REQ_258_comment_rate_limit_per_ip_is_enforced(): void {
		$this->guard( 2 );
		add_filter(
			'wp_die_handler',
			static fn () => static function ( $message ): void {
				$code = $message instanceof \WP_Error ? $message->get_error_code() : 'died';
				throw new \RuntimeException( esc_html( (string) $code ) );
			}
		);
		$post = $this->story();
		$this->assertInstanceOf( \WP_Comment::class, $this->submit( $post ) );
		$this->assertInstanceOf( \WP_Comment::class, $this->submit( $post ) );
		$stored = (int) get_comments_number( $post );
		try {
			$this->submit( $post );
			$this->fail( 'The third comment from one IP in the window must be refused.' );
		} catch ( \RuntimeException $refusal ) {
			$this->assertSame( 'wiw_comment_rate', $refusal->getMessage() );
		}
		$this->assertCount(
			2,
			get_comments(
				array(
					'post_id' => $post,
					'status'  => 'all',
				)
			),
			'nothing was stored for the refused one'
		);
		unset( $stored );
	}
}
