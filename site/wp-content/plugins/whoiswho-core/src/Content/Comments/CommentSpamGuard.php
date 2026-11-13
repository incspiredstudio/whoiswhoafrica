<?php
/**
 * Spam protection for story comments (REQ-258, REQ-122): a honeypot field that a person never fills, a per-IP rate limit through
 * the Platform throttle, and an optional outside filter (Akismet or an equivalent, added through the wiw_comment_is_spam filter)
 * that stays off until the processor decision is made. A comment caught by the honeypot or the filter is marked spam, so it
 * never enters the moderation queue and is never published; a flood from one IP is refused before anything is stored. The
 * throttle keeps no raw IP or email.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Comments;

use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Core\Platform\Forms\TransientStore;

/**
 * Comment spam guard.
 */
final class CommentSpamGuard {

	public const HONEYPOT = 'wiw_comment_url';
	public const SETTING  = 'wiw_comment_spam_filter';
	public const FORM     = 'comment';
	public const LIMIT    = 5;
	public const WINDOW   = 600;
	public const LIMITS   = array(
		'comment' => array(
			'ip'      => array( self::LIMIT, self::WINDOW ),
			'subject' => array( self::LIMIT, self::WINDOW ),
		),
	);

	/**
	 * Throttle.
	 *
	 * @var RateLimiter|null
	 */
	private ?RateLimiter $limiter;

	/**
	 * Whether the current submission was caught by the honeypot or the filter.
	 *
	 * @var bool
	 */
	private bool $flagged = false;

	/**
	 * Constructor.
	 *
	 * @param RateLimiter|null $limiter Throttle; the site's transient-backed one by default.
	 */
	public function __construct( ?RateLimiter $limiter = null ) {
		$this->limiter = $limiter;
	}

	/**
	 * Register the hooks.
	 */
	public function register(): void {
		add_filter( 'preprocess_comment', array( $this, 'screen' ), 4 );
		add_filter( 'pre_comment_approved', array( $this, 'verdict' ), 6, 2 );
	}

	/**
	 * Refuse a flood, and note a honeypot or filter hit. Staff comments are not screened.
	 *
	 * @param array<string, mixed> $commentdata Comment data.
	 * @return array<string, mixed>
	 */
	public function screen( $commentdata ) {
		$this->flagged = false;
		if ( is_admin() || current_user_can( 'moderate_comments' ) ) {
			return $commentdata;
		}
		$ip    = (string) ( $commentdata['comment_author_IP'] ?? '' );
		$email = (string) ( $commentdata['comment_author_email'] ?? '' );
		if ( ! $this->limiter()->allow( self::FORM, $ip, $email ) ) {
			wp_die(
				new \WP_Error( 'wiw_comment_rate', esc_html__( 'Too many comments from this device. Please try again later.', 'whoiswho' ), array( 'response' => 429 ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the message inside the error is escaped.
				'',
				array( 'back_link' => true )
			);
		}
		if ( '' !== FormInput::from_globals()->raw( self::HONEYPOT ) ) {
			$this->flagged = true;
		} elseif ( '1' === (string) get_option( self::SETTING, '0' ) ) {
			/**
			 * Whether an outside filter judges this comment to be spam. Used only when the setting is on.
			 *
			 * @param bool                 $spam        False by default.
			 * @param array<string, mixed> $commentdata Comment data.
			 */
			$this->flagged = (bool) apply_filters( 'wiw_comment_is_spam', false, $commentdata );
		}
		return $commentdata;
	}

	/**
	 * A flagged comment is spam: not published, and not in the queue.
	 *
	 * @param int|string|\WP_Error $approved Verdict.
	 * @param array<string, mixed> $commentdata Comment data.
	 * @return int|string|\WP_Error
	 */
	public function verdict( $approved, $commentdata = array() ) {
		unset( $commentdata );
		if ( $this->flagged ) {
			$this->flagged = false;
			return 'spam';
		}
		return $approved;
	}

	/**
	 * The throttle.
	 */
	private function limiter(): RateLimiter {
		if ( null === $this->limiter ) {
			$this->limiter = new RateLimiter( new TransientStore(), wp_salt( 'auth' ), self::LIMITS + RateLimiter::LIMITS );
		}
		return $this->limiter;
	}
}
