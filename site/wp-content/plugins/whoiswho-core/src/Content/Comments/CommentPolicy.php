<?php
/**
 * The comment rules of the site (REQ-258, REQ-229): only stories take comments, guests need no account but must give a name, a
 * valid email and a consent tick, every comment is held until a user with moderate_comments approves it, Gravatar is off and the
 * commenter's email never reaches public HTML. These rules sit under whichever comment form is on the page (wpDiscuz or core), so
 * they hold even if the form plugin is missing or misconfigured.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Comments;

use WhoIsWho\Core\Platform\Forms\FormInput;

/**
 * Comment policy.
 */
final class CommentPolicy {

	public const STORY_TYPE    = 'post';
	public const CONSENT_FIELD = 'wiw_comment_consent';
	public const CLOSE_DAYS    = 90;
	public const WPDISCUZ_FILE = 'wpdiscuz/class.WpdiscuzCore.php';

	/**
	 * Core options forced to the values below, whatever is saved.
	 */
	private const FORCED_OPTIONS = array(
		'comment_moderation'          => '1',
		'comment_previously_approved' => '0',
		'comment_registration'        => '0',
		'require_name_email'          => '1',
		'show_avatars'                => '0',
		'comments_notify'             => '0',
		'default_ping_status'         => 'closed',
	);

	/**
	 * Register the hooks.
	 */
	public function register(): void {
		foreach ( self::FORCED_OPTIONS as $option => $value ) {
			add_filter( 'pre_option_' . $option, static fn () => $value );
		}
		add_filter( 'comments_open', array( $this, 'comments_open' ), 20, 2 );
		add_filter( 'pings_open', '__return_false', 20 );
		add_filter( 'preprocess_comment', array( $this, 'require_consent' ), 5 );
		add_filter( 'pre_comment_approved', array( $this, 'hold' ), 20 );
		add_filter( 'get_avatar_url', '__return_false', 20 );
		add_filter( 'get_comment_author_email', array( $this, 'hide_email' ), 20 );
		add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
	}

	/**
	 * Comments are open on a story only, and close a set number of days after publication (WIW_COMMENT_CLOSE_DAYS filter, 0 keeps them open).
	 *
	 * @param mixed        $open Whether open.
	 * @param int|\WP_Post $post Post or id.
	 */
	public function comments_open( $open, $post ): bool {
		$post = get_post( $post );
		if ( ! $post instanceof \WP_Post || self::STORY_TYPE !== $post->post_type ) {
			return false;
		}
		$days = (int) apply_filters( 'wiw_comment_close_days', self::CLOSE_DAYS );
		if ( $days > 0 && (int) get_post_time( 'U', true, $post ) < time() - $days * DAY_IN_SECONDS ) {
			return false;
		}
		return (bool) $open;
	}

	/**
	 * A guest comment needs the consent tick. Staff comments made in the admin are exempt.
	 *
	 * @param array<string, mixed> $commentdata Comment data.
	 * @return array<string, mixed>
	 */
	public function require_consent( $commentdata ) {
		if ( is_admin() || current_user_can( 'moderate_comments' ) ) {
			return $commentdata;
		}
		if ( '' === FormInput::from_globals()->raw( self::CONSENT_FIELD ) ) {
			wp_die(
				new \WP_Error( 'wiw_comment_consent', esc_html__( 'Please tick the box to agree before you comment.', 'whoiswho' ), array( 'response' => 409 ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the message inside the error is escaped.
				'',
				array( 'back_link' => true )
			);
		}
		return $commentdata;
	}

	/**
	 * Every comment from a visitor waits for a moderator; spam and trash verdicts stay as they are.
	 *
	 * @param int|string|\WP_Error $approved Verdict.
	 * @return int|string|\WP_Error
	 */
	public function hold( $approved ) {
		if ( 'spam' === $approved || 'trash' === $approved || is_wp_error( $approved ) ) {
			return $approved;
		}
		return current_user_can( 'moderate_comments' ) ? $approved : 0;
	}

	/**
	 * The commenter's email is for staff only: it is empty everywhere a visitor could see it.
	 *
	 * @param string $email Email.
	 */
	public function hide_email( $email ): string {
		return ( is_admin() && current_user_can( 'moderate_comments' ) ) ? (string) $email : '';
	}

	/**
	 * Tell Administrators when the comment form plugin is not active (the rules above still hold).
	 */
	public function dependency_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active( self::WPDISCUZ_FILE ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'The comment form plugin (wpDiscuz) is not active. Comments follow the site rules but use the plain WordPress form.', 'whoiswho' ) . '</p></div>';
		}
	}
}
