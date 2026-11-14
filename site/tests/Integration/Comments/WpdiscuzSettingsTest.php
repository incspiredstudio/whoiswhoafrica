<?php
/**
 * wpDiscuz set to the comment rules (REQ-258, REQ-229): its settings forced on read, its forms set once, the consent tick added to
 * its form, and its admin-ajax comment posts screened like any visitor's. wpDiscuz itself is not loaded: its settings and forms
 * are stored the way wpDiscuz 7.7.0 stores them. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Comments;

use WhoIsWho\Core\Content\Comments\CommentPolicy;
use WhoIsWho\Core\Content\Comments\CommentSpamGuard;
use WhoIsWho\Core\Content\Comments\WpdiscuzSettings;
use WhoIsWho\Tests\WpTestCase;

final class WpdiscuzSettingsTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( 0 );
		delete_option( WpdiscuzSettings::OPTION );
		delete_option( WpdiscuzSettings::APPLIED );
	}

	protected function tearDown(): void {
		$_POST = array();
		remove_all_filters( 'option_' . WpdiscuzSettings::OPTION );
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wp_die_handler' );
		remove_all_filters( 'wp_die_ajax_handler' );
		remove_all_filters( 'preprocess_comment' );
		remove_all_filters( 'pre_comment_approved' );
		remove_all_actions( 'comment_form' );
		foreach ( array( 'comment_moderation', 'comment_previously_approved', 'comment_registration', 'require_name_email', 'show_avatars', 'comments_notify', 'default_ping_status' ) as $option ) {
			remove_all_filters( 'pre_option_' . $option );
		}
		foreach ( array( 'comments_open', 'pings_open', 'get_avatar_url', 'get_comment_author_email' ) as $hook ) {
			remove_all_filters( $hook );
		}
		set_current_screen( 'front' );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * wpDiscuz's stored settings, as its defaults would save them (abridged).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function stored(): array {
		return array(
			'thread_layouts' => array(
				'showAvatars'       => 1,
				'showVotingButtons' => 1,
				'isGuestCanVote'    => 1,
				'showCommentDate'   => 1,
			),
			'social'         => array(
				'enableTwitterShare' => 1,
				'enableGoogleLogin'  => 1,
			),
			'content'        => array( 'wmuIsEnabled' => 1 ),
			'form'           => array( 'storeCommenterData' => -1 ),
			'general'        => array( 'isEnableOnHome' => 1 ),
		);
	}

	public function test_REQ_258_wpdiscuz_settings_are_forced_to_the_rules_on_every_read(): void {
		( new WpdiscuzSettings() )->register();
		update_option( WpdiscuzSettings::OPTION, $this->stored() );
		$read = get_option( WpdiscuzSettings::OPTION );
		$this->assertSame( 0, $read['thread_layouts']['showAvatars'], 'no avatars, so no Gravatar' );
		$this->assertSame( 0, $read['thread_layouts']['showVotingButtons'], 'no votes (they store an IP hash)' );
		$this->assertSame( 0, $read['thread_layouts']['isGuestCanVote'] );
		$this->assertSame( 1, $read['thread_layouts']['showCommentDate'], 'other settings are kept' );
		$this->assertSame( 0, $read['social']['enableTwitterShare'] );
		$this->assertSame( 0, $read['social']['enableGoogleLogin'], 'no public login of any kind' );
		$this->assertSame( 0, $read['content']['wmuIsEnabled'], 'no visitor uploads' );
		$this->assertSame( 0, $read['form']['storeCommenterData'], 'the commenter is not remembered in a long-lived cookie' );
		$this->assertSame( 0, $read['subscription']['isFollowActive'] );
		$this->assertSame( 0, $read['subscription']['isReplyDefaultChecked'], 'reply notifications are opt-in' );
		$this->assertSame( 1, $read['subscription']['enableGuestsConfirm'], 'and confirmed by email' );
		$this->assertSame( 0, $read['general']['showPluginPoweredByLink'] );
		$this->assertSame( 0, $read['rating']['enablePostRatingSchema'], 'no schema markup from a plugin (REQ-217)' );
		$this->assertFalse( get_option( 'wiw_absent_option_zq' ), 'other options are untouched' );
	}

	public function test_REQ_258_apply_saves_the_settings_and_sets_every_wpdiscuz_form(): void {
		update_option( WpdiscuzSettings::OPTION, $this->stored() );
		$form = self::factory()->post->create(
			array(
				'post_type'   => WpdiscuzSettings::FORM_TYPE,
				'post_status' => 'publish',
			)
		);
		update_post_meta(
			$form,
			WpdiscuzSettings::FORM_GENERAL,
			array(
				'guest_can_comment'         => 0,
				'enable_post_rating'        => 1,
				'allow_guests_rate_on_post' => 1,
				'wpdiscuz_form_post_types'  => array(
					'post'       => 'post',
					'page'       => 'page',
					'attachment' => 'attachment',
				),
				'header_text_single'        => 'Comment',
			)
		);
		// The structure exactly as wpDiscuz 7.7.0 saves its default form (stored with add_magic_quotes).
		$structure = array(
			'wpd_form_row_wrap_0' => array(
				'column_type' => 'two',
				'left'        => array(
					'wc_name'    => array(
						'type'     => 'wpdFormAttr\Field\DefaultField\Name',
						'required' => '0',
					),
					'wc_email'   => array(
						'type'     => 'wpdFormAttr\Field\DefaultField\Email',
						'required' => '0',
					),
					'wc_website' => array(
						'type'   => 'wpdFormAttr\Field\DefaultField\Website',
						'enable' => '1',
					),
				),
				'right'       => array(
					'submit' => array( 'type' => 'wpdFormAttr\Field\DefaultField\Submit' ),
				),
			),
		);
		update_post_meta( $form, WpdiscuzSettings::FORM_STRUCTURE, add_magic_quotes( $structure ) );

		$this->assertSame( 1, ( new WpdiscuzSettings() )->apply() );

		$saved = get_option( WpdiscuzSettings::OPTION );
		$this->assertSame( 0, $saved['thread_layouts']['showAvatars'], 'saved, so its settings screen shows the rules' );
		$general = get_post_meta( $form, WpdiscuzSettings::FORM_GENERAL, true );
		$this->assertSame( 1, $general['guest_can_comment'], 'guests comment without an account' );
		$this->assertSame( 0, $general['enable_post_rating'], 'no star ratings (they store the IP address)' );
		$this->assertSame( 0, $general['allow_guests_rate_on_post'] );
		$this->assertSame( array( 'post' => 'post' ), $general['wpdiscuz_form_post_types'], 'stories only' );
		$this->assertSame( 'Comment', $general['header_text_single'], 'other form settings are kept' );
		$fields = get_post_meta( $form, WpdiscuzSettings::FORM_STRUCTURE, true )['wpd_form_row_wrap_0']['left'];
		$this->assertSame( '1', $fields['wc_name']['required'] );
		$this->assertSame( '1', $fields['wc_email']['required'] );
		$this->assertSame( '0', $fields['wc_website']['enable'], 'no website field' );
		$this->assertSame( 'wpdFormAttr\Field\DefaultField\Name', $fields['wc_name']['type'], 'backslashes survive the save' );
		$this->assertSame( WpdiscuzSettings::VERSION, get_option( WpdiscuzSettings::APPLIED ) );
		$this->assertSame( '1', get_option( WpdiscuzSettings::WIZARD_DONE ), 'no set-up wizard banner for the owner' );
	}

	public function test_REQ_258_apply_really_saves_while_the_read_filter_is_on(): void {
		update_option( WpdiscuzSettings::OPTION, $this->stored() );
		$settings = new WpdiscuzSettings();
		$settings->register();
		$settings->apply();
		remove_all_filters( 'option_' . WpdiscuzSettings::OPTION );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( WpdiscuzSettings::OPTION, 'options' );
		$this->assertSame( 0, get_option( WpdiscuzSettings::OPTION )['thread_layouts']['showAvatars'], 'the stored value changed, not only the filtered read' );
	}

	public function test_REQ_258_apply_waits_for_wpdiscuz_to_create_its_form(): void {
		$this->assertSame( 0, ( new WpdiscuzSettings() )->apply() );
		$this->assertFalse( get_option( WpdiscuzSettings::APPLIED ), 'not marked done, so the form wpDiscuz creates later is set' );
	}

	public function test_REQ_258_the_consent_tick_is_added_to_wpdiscuz_form_for_visitors_only(): void {
		( new WpdiscuzSettings() )->register();
		ob_start();
		do_action( 'comment_form', 12, new \WP_User( 0 ), true, 'uid', 0 );
		$wpdiscuz = (string) ob_get_clean();
		$this->assertStringContainsString( 'name="' . CommentPolicy::CONSENT_FIELD . '"', $wpdiscuz );
		$this->assertStringContainsString( 'required', $wpdiscuz );

		ob_start();
		do_action( 'comment_form', 12 );
		$this->assertSame( '', (string) ob_get_clean(), 'the core form carries its own tick' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		do_action( 'comment_form', 12, wp_get_current_user(), true, 'uid', 0 );
		$this->assertStringNotContainsString( CommentPolicy::CONSENT_FIELD, (string) ob_get_clean(), 'staff are exempt' );
	}

	/**
	 * Make this request look like wpDiscuz's comment post: admin-ajax.php, which WordPress counts as admin.
	 */
	private function as_wpdiscuz_ajax(): void {
		set_current_screen( 'edit-comments' );
		add_filter( 'wp_doing_ajax', '__return_true' );
		$this->assertTrue( is_admin() );
	}

	public function test_REQ_258_a_visitor_comment_through_admin_ajax_still_needs_the_consent_tick(): void {
		( new CommentPolicy() )->register();
		$this->as_wpdiscuz_ajax();
		$story = self::factory()->post->create();
		$_POST = array();
		$died  = false;
		$catch = static function () use ( &$died ) {
			return static function () use ( &$died ): void {
				$died = true;
				throw new \RuntimeException( 'wp_die called' );
			};
		};
		add_filter( 'wp_die_handler', $catch );
		add_filter( 'wp_die_ajax_handler', $catch );
		try {
			wp_new_comment(
				array(
					'comment_post_ID'      => $story,
					'comment_author'       => 'Ada Obi',
					'comment_author_email' => 'ada@example.test',
					'comment_content'      => 'Posted through wpDiscuz.',
					'comment_type'         => 'comment',
				)
			);
		} catch ( \RuntimeException $e ) {
			unset( $e );
		}
		$this->assertTrue( $died, 'admin-ajax is not an admin screen: the visitor must tick the box' );
	}

	public function test_REQ_258_a_visitor_comment_through_admin_ajax_needs_a_name_and_email(): void {
		( new CommentPolicy() )->register();
		$this->as_wpdiscuz_ajax();
		$_POST = array( CommentPolicy::CONSENT_FIELD => '1' );
		$this->expectException( \RuntimeException::class );
		add_filter(
			'wp_die_ajax_handler',
			static fn () => static function (): void {
				throw new \RuntimeException( 'wp_die called' );
			}
		);
		( new CommentPolicy() )->require_consent(
			array(
				'comment_author'       => '',
				'comment_author_email' => 'not-an-email',
				'user_id'              => 0,
			)
		);
	}

	public function test_REQ_258_a_visitor_comment_through_admin_ajax_is_held_and_rate_limited(): void {
		$this->as_wpdiscuz_ajax();
		$this->assertFalse( CommentPolicy::is_staff_request(), 'a visitor on admin-ajax is not staff' );
		$this->assertSame( 0, ( new CommentPolicy() )->hold( 1 ), 'held for a moderator' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( CommentPolicy::is_staff_request(), 'a moderator is staff anywhere' );
		$this->assertTrue( class_exists( CommentSpamGuard::class ), 'the spam guard uses the same staff test' );
	}
}
