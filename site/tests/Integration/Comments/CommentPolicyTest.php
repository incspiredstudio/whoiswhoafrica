<?php
/**
 * The comment rules (REQ-258, REQ-229). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Comments;

use WhoIsWho\Core\Content\Comments\CommentPolicy;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

final class CommentPolicyTest extends TestCase {

	/**
	 * The policy under test, registered once per test.
	 *
	 * @var CommentPolicy
	 */
	private CommentPolicy $policy;

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
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
		parent::tearDown();
	}

	/**
	 * Remember a post for cleanup.
	 *
	 * @param int $id Post id.
	 */
	private function track( int $id ): int {
		$this->posts[] = $id;
		return $id;
	}

	/**
	 * A published story.
	 *
	 * @param string $date Post date.
	 */
	private function story( string $date = '' ): int {
		$args = array(
			'post_title'   => 'A story',
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_content' => 'Body.',
		);
		if ( '' !== $date ) {
			$args['post_date'] = $date;
		}
		return $this->track( (int) wp_insert_post( $args ) );
	}

	/**
	 * Submit a guest comment the way the comment form does.
	 *
	 * @param int                  $post_id Post.
	 * @param array<string, mixed> $extra   Overrides.
	 * @return \WP_Comment|\WP_Error
	 */
	private function submit( int $post_id, array $extra = array() ) {
		return wp_handle_comment_submission(
			array_merge(
				array(
					'comment_post_ID' => $post_id,
					'author'          => 'Ada Obi',
					'email'           => 'ada@example.test',
					'comment'         => 'A thoughtful comment ' . wp_generate_password( 8, false ),
				),
				$extra
			)
		);
	}

	public function test_REQ_258_comment_is_held_until_approved(): void {
		$post    = $this->story();
		$comment = $this->submit( $post );
		$this->assertInstanceOf( \WP_Comment::class, $comment );
		$this->assertSame( '0', (string) $comment->comment_approved );
		$this->assertSame(
			array(),
			get_comments(
				array(
					'post_id' => $post,
					'status'  => 'approve',
				)
			),
			'Other visitors do not see it.'
		);
		wp_set_comment_status( $comment->comment_ID, 'approve' );
		$this->assertCount(
			1,
			get_comments(
				array(
					'post_id' => $post,
					'status'  => 'approve',
				)
			)
		);
	}

	public function test_REQ_258_comments_closed_on_profiles_and_pages(): void {
		$story   = $this->story();
		$page    = $this->track(
			(int) wp_insert_post(
				array(
					'post_title'  => 'About',
					'post_status' => 'publish',
					'post_type'   => 'page',
				)
			)
		);
		$profile = $this->track(
			(int) wp_insert_post(
				array(
					'post_title'     => 'A profile',
					'post_status'    => 'publish',
					'post_type'      => ProfileType::POST_TYPE,
					'comment_status' => 'open',
				)
			)
		);
		$this->assertTrue( comments_open( $story ) );
		$this->assertFalse( comments_open( $page ) );
		$this->assertFalse( comments_open( $profile ) );
		$this->assertFalse( pings_open( $story ) );
		$this->assertInstanceOf( \WP_Error::class, $this->submit( $page ) );
	}

	public function test_REQ_258_comments_close_ninety_days_after_publication(): void {
		$old = $this->story( gmdate( 'Y-m-d H:i:s', time() - 91 * DAY_IN_SECONDS ) );
		$new = $this->story( gmdate( 'Y-m-d H:i:s', time() - 89 * DAY_IN_SECONDS ) );
		$this->assertFalse( comments_open( $old ) );
		$this->assertTrue( comments_open( $new ) );
	}

	public function test_REQ_258_comment_requires_name_email_and_consent(): void {
		$post = $this->story();
		$this->assertInstanceOf( \WP_Error::class, $this->submit( $post, array( 'author' => '' ) ) );
		$this->assertInstanceOf( \WP_Error::class, $this->submit( $post, array( 'email' => 'not-an-email' ) ) );
		$this->assertInstanceOf( \WP_Error::class, $this->submit( $post, array( 'email' => '' ) ) );
		$_POST = array();
		add_filter(
			'wp_die_handler',
			static fn () => static function ( $message ): void {
				unset( $message );
				throw new \RuntimeException( 'wp_die called' );
			}
		);
		$this->expectException( \RuntimeException::class );
		$this->submit( $post );
	}

	public function test_REQ_258_no_gravatar_request(): void {
		$post    = $this->story();
		$comment = $this->submit( $post );
		wp_set_comment_status( $comment->comment_ID, 'approve' );
		$this->assertFalse( get_avatar( $comment ), 'No avatar markup at all.' );
		$html = wp_list_comments(
			array(
				'echo'  => false,
				'style' => 'div',
			),
			array( get_comment( $comment->comment_ID ) )
		);
		$this->assertStringNotContainsString( 'gravatar', (string) $html );
		$this->assertStringNotContainsString( '<img', (string) $html );
	}

	public function test_REQ_258_commenter_email_never_in_public_html(): void {
		$post    = $this->story();
		$comment = $this->submit( $post );
		wp_set_comment_status( $comment->comment_ID, 'approve' );
		$this->assertSame( '', get_comment_author_email( $comment->comment_ID ) );
		$html = (string) wp_list_comments(
			array(
				'echo'  => false,
				'style' => 'div',
			),
			array( get_comment( $comment->comment_ID ) )
		);
		$this->assertStringContainsString( 'Ada Obi', $html );
		$this->assertStringNotContainsString( 'ada@example.test', $html );
		$this->assertStringNotContainsString( md5( 'ada@example.test' ), $html );
	}

	public function test_REQ_229_no_login_is_needed_to_comment(): void {
		$this->assertSame( '0', (string) get_option( 'comment_registration' ) );
		$this->assertSame( '1', (string) get_option( 'comment_moderation' ) );
		$this->assertSame( '0', (string) get_option( 'show_avatars' ) );
	}
}
