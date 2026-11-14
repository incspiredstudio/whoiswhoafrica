<?php
/**
 * The wpDiscuz plugin set to the site's comment rules (REQ-258, REQ-229), without editing any wpDiscuz file. CommentPolicy already holds the
 * rules underneath any comment form; this class makes wpDiscuz's own screens and form agree with them:
 *
 * - Its global settings (the wc_options option) are filtered on every read, so a change on its settings screen cannot switch back
 *   on what the rules forbid: no avatars or Gravatar, no votes (they store a hash of the visitor's IP address), no social logins or
 *   share buttons, no login link, profile links or user tabs, no mentions or user follows, no file uploads by visitors, no rating
 *   schema, the commenter is not remembered in a long-lived cookie, reply notifications are opt-in and confirmed by email, and no
 *   "powered by" link. The same values are also saved once, so its settings screen shows them.
 * - Its comment forms (wpdiscuz_form posts) are set once, when wpDiscuz is activated or first set up: stories only, guests may
 *   comment, name and email required, no website field, no star rating (ratings store the visitor's IP address).
 * - The consent tick the rules require is added to its form, for visitors.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Comments;

/**
 * Settings for the wpDiscuz plugin.
 */
final class WpdiscuzSettings {

	public const OPTION         = 'wc_options';
	public const FORM_TYPE      = 'wpdiscuz_form';
	public const FORM_GENERAL   = 'wpdiscuz_form_general_options';
	public const FORM_STRUCTURE = 'wpdiscuz_form_structure';
	public const APPLIED        = 'wiw_wpdiscuz_applied';
	public const VERSION        = '1';
	public const WIZARD_DONE    = 'wpdiscuz_wizard_completed';

	/**
	 * The global settings of wpDiscuz, forced to these values: tab => setting => value.
	 */
	public const FORCED = array(
		'form'           => array(
			'storeCommenterData' => 0,
		),
		'login'          => array(
			'showLoginLinkForGuests' => 0,
			'showActivityTab'        => 0,
			'showSubscriptionsTab'   => 0,
			'showFollowsTab'         => 0,
			'enableProfileURLs'      => 0,
			'websiteAsProfileUrl'    => 0,
		),
		'social'         => array(
			'displaySocialAvatar'       => 0,
			'enableFbLogin'             => 0,
			'enableFbShare'             => 0,
			'enableTwitterLogin'        => 0,
			'enableTwitterShare'        => 0,
			'enableGoogleLogin'         => 0,
			'enableTelegramLogin'       => 0,
			'enableDisqusLogin'         => 0,
			'enableWordpressLogin'      => 0,
			'enableInstagramLogin'      => 0,
			'enableLinkedinLogin'       => 0,
			'enableLinkedinLoginOpenID' => 0,
			'enableWhatsappShare'       => 0,
			'enableYandexLogin'         => 0,
			'enableWeiboLogin'          => 0,
			'enableWechatLogin'         => 0,
			'enableQQLogin'             => 0,
			'enableBaiduLogin'          => 0,
			'enableVkLogin'             => 0,
		),
		'rating'         => array(
			'enablePostRatingSchema' => 0,
		),
		'thread_layouts' => array(
			'showAvatars'         => 0,
			'showVotingButtons'   => 0,
			'enableDislikeButton' => 0,
			'isGuestCanVote'      => 0,
		),
		'subscription'   => array(
			'enableUserMentioning'     => 0,
			'sendMailToMentionedUsers' => 0,
			'isNotifyOnCommentApprove' => 0,
			'enableGuestsConfirm'      => 1,
			'isReplyDefaultChecked'    => 0,
			'isFollowActive'           => 0,
		),
		'content'        => array(
			'wmuIsEnabled'      => 0,
			'wmuIsGuestAllowed' => 0,
			'enableShortcodes'  => 0,
		),
		'general'        => array(
			'isEnableOnHome'          => 0,
			'showPluginPoweredByLink' => 0,
		),
	);

	/**
	 * Comment form settings set on every wpDiscuz form.
	 */
	public const FORM_SETTINGS = array(
		'guest_can_comment'         => 1,
		'guest_can_see_comments'    => 1,
		'roles_cannot_comment'      => array(),
		'enable_post_rating'        => 0,
		'allow_guests_rate_on_post' => 0,
		'wpdiscuz_form_post_types'  => array( CommentPolicy::STORY_TYPE => CommentPolicy::STORY_TYPE ),
	);

	/**
	 * True while apply() reads the stored settings as they are.
	 *
	 * @var bool
	 */
	private static bool $raw = false;

	/**
	 * Attach hooks. They do nothing while wpDiscuz is not installed.
	 */
	public function register(): void {
		add_filter( 'option_' . self::OPTION, array( $this, 'force' ), 99 );
		add_action( 'activated_plugin', array( $this, 'on_activated' ), 10, 1 );
		add_action( 'admin_init', array( $this, 'maybe_apply' ), 99 );
		add_action( 'comment_form', array( $this, 'consent_field' ), 20, 2 );
	}

	/**
	 * Force the rule values onto wpDiscuz's global settings as they are read.
	 *
	 * @param mixed $value Stored value (an array, or serialised).
	 * @return mixed
	 */
	public function force( $value ) {
		$options = maybe_unserialize( $value );
		if ( self::$raw && 'option_' . self::OPTION === current_filter() ) {
			return $value;
		}
		if ( ! is_array( $options ) ) {
			return $value;
		}
		foreach ( self::FORCED as $tab => $settings ) {
			$current         = isset( $options[ $tab ] ) && is_array( $options[ $tab ] ) ? $options[ $tab ] : array();
			$options[ $tab ] = array_merge( $current, $settings );
		}
		return $options;
	}

	/**
	 * After wpDiscuz or this plugin is activated, apply the settings again (wpDiscuz creates its form on its first admin load).
	 *
	 * @param string $plugin Plugin file just activated.
	 */
	public function on_activated( $plugin ): void {
		if ( CommentPolicy::WPDISCUZ_FILE === (string) $plugin || 'whoiswho-core/whoiswho-core.php' === (string) $plugin ) {
			delete_option( self::APPLIED );
			$this->apply();
		}
	}

	/**
	 * Apply once per settings version while wpDiscuz is loaded and has its forms.
	 */
	public function maybe_apply(): void {
		if ( self::VERSION === (string) get_option( self::APPLIED, '' ) || ! class_exists( '\WpdiscuzCore' ) ) {
			return;
		}
		$this->apply();
	}

	/**
	 * Save the rule values into wpDiscuz's stored settings and every comment form. Marked done only when a form was found, so a
	 * form wpDiscuz creates later is still set.
	 *
	 * @return int Forms set.
	 */
	public function apply(): int {
		// Read and compare the stored value, not the forced one, or update_option() would see no change and save nothing.
		self::$raw = true;
		try {
			$stored = get_option( self::OPTION );
			if ( is_array( $stored ) ) {
				update_option( self::OPTION, $this->force( $stored ) );
			}
		} finally {
			self::$raw = false;
		}
		// Its set-up wizard would only ask again for what is set here, so it is marked done (no "complete the wizard" banner).
		update_option( self::WIZARD_DONE, '1', false );
		$forms = get_posts(
			array(
				'post_type'        => self::FORM_TYPE,
				'post_status'      => 'any',
				'numberposts'      => 50,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		foreach ( $forms as $form_id ) {
			$this->apply_form( (int) $form_id );
		}
		if ( array() !== $forms ) {
			update_option( self::APPLIED, self::VERSION, false );
		}
		return count( $forms );
	}

	/**
	 * Set one wpDiscuz form: the general settings, then name and email required and no website field.
	 *
	 * @param int $form_id Form post id.
	 */
	public function apply_form( int $form_id ): void {
		$general = get_post_meta( $form_id, self::FORM_GENERAL, true );
		$general = is_array( $general ) ? $general : array();
		update_post_meta( $form_id, self::FORM_GENERAL, array_merge( $general, self::FORM_SETTINGS ) );

		$structure = get_post_meta( $form_id, self::FORM_STRUCTURE, true );
		if ( ! is_array( $structure ) ) {
			return;
		}
		foreach ( $structure as $row_key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			foreach ( array( 'left', 'right', 'full' ) as $column ) {
				if ( ! isset( $row[ $column ] ) || ! is_array( $row[ $column ] ) ) {
					continue;
				}
				foreach ( $row[ $column ] as $field_key => $field ) {
					$row[ $column ][ $field_key ] = self::field( is_array( $field ) ? $field : array() );
				}
			}
			$structure[ $row_key ] = $row;
		}
		update_post_meta( $form_id, self::FORM_STRUCTURE, wp_slash( $structure ) );
	}

	/**
	 * One form field set to the rules: name and email required, the website field off.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return array<string, mixed>
	 */
	private static function field( array $field ): array {
		$type = isset( $field['type'] ) ? (string) $field['type'] : '';
		if ( str_ends_with( $type, 'DefaultField\\Name' ) || str_ends_with( $type, 'DefaultField\\Email' ) ) {
			$field['required'] = '1';
		}
		if ( str_ends_with( $type, 'DefaultField\\Website' ) ) {
			$field['enable'] = '0';
		}
		return $field;
	}

	/**
	 * The consent tick inside wpDiscuz's form, for visitors. wpDiscuz passes the post id and the current user to comment_form; the
	 * core form passes only the post id and already carries the tick, so it gets nothing here.
	 *
	 * @param mixed $post_id Post id.
	 * @param mixed $user    The current user (wpDiscuz only).
	 */
	public function consent_field( $post_id = 0, $user = null ): void {
		unset( $post_id );
		if ( ! is_object( $user ) || is_user_logged_in() ) {
			return;
		}
		echo self::consent_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}

	/**
	 * The consent tick markup.
	 */
	public static function consent_html(): string {
		$privacy = get_privacy_policy_url();
		$link    = '' !== $privacy ? ' <a href="' . esc_url( $privacy ) . '">' . esc_html__( 'privacy notice', 'whoiswho' ) . '</a>' : ' ' . esc_html__( 'privacy notice', 'whoiswho' );
		return '<div class="wpd-field-group wiw-comment-consent"><label><input type="checkbox" name="' . esc_attr( CommentPolicy::CONSENT_FIELD ) . '" value="1" required> '
			. esc_html__( 'I agree that you may keep my name and comment with this story, as set out in the', 'whoiswho' ) . $link . '.</label></div>';
	}
}
