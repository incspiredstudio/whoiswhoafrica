<?php
/**
 * The Setup screen (REQ-056, REQ-208): WHOISWHO > Setup. A live checklist, one-click fixes and one-click demo content, for someone
 * who does not write code. It opens once, straight after the plugin is activated, and a Dashboard widget keeps reminding until it
 * is dismissed. Administrators only (wiw_run_setup). Fixes are plain forms (they work without JavaScript); the demo import runs in
 * small steps over AJAX with a progress bar. Every request checks the capability and a nonce through ActionGuard.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Setup;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Permalinks;
use WhoIsWho\Core\Platform\TwoFactor\Enforcement;

/**
 * Setup screen.
 */
final class SetupScreen {

	public const SCREEN    = 'wiw-setup';
	public const CAP       = 'wiw_run_setup';
	public const DISMISSED = 'wiw_setup_dismissed';
	public const WIDGET    = 'wiw_setup_widget';

	public const ACTION_FIX    = 'wiw_setup_fix';
	public const ACTION_START  = 'wiw_demo_start';
	public const ACTION_STEP   = 'wiw_demo_step';
	public const ACTION_REMOVE = 'wiw_demo_remove';

	/**
	 * Attach hooks.
	 */
	public static function hooks(): void {
		add_action( 'init', array( self::class, 'register_admin' ), 1 );
		add_action( 'admin_init', array( self::class, 'maybe_redirect' ) );
		add_action( 'wp_dashboard_setup', array( self::class, 'add_widget' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		SetupNotice::register();
	}

	/**
	 * Register the screen and its actions in the admin only.
	 */
	public static function register_admin(): void {
		if ( ! is_admin() ) {
			return;
		}
		$registry = ScreenRegistry::shared();
		$registry->register(
			self::SCREEN,
			__( 'Setup', 'whoiswho' ),
			self::CAP,
			static function (): void {
				echo self::html( Request::from_globals() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
			}
		);
		$registry->hook();
		ActionGuard::register( self::ACTION_FIX, self::CAP, array( self::class, 'handle_fix' ) );
		ActionGuard::register( self::ACTION_START, self::CAP, array( self::class, 'handle_start' ), true );
		ActionGuard::register( self::ACTION_STEP, self::CAP, array( self::class, 'handle_step' ), true );
		ActionGuard::register( self::ACTION_REMOVE, self::CAP, array( self::class, 'handle_remove' ), true );
	}

	/**
	 * Send the administrator to the Setup screen once, right after activation. A staff member who has not set up two-factor sign-in
	 * yet is sent to their profile first (Platform\TwoFactor\Enforcement), so the flag waits until they can open the screen.
	 */
	public static function maybe_redirect(): void {
		global $pagenow;
		$target = self::redirect_target(
			get_current_user_id(),
			is_string( $pagenow ) ? $pagenow : '',
			Request::from_globals()->has( 'activate-multi' ),
			wp_doing_ajax()
		);
		if ( null === $target ) {
			return;
		}
		delete_option( FirstRun::REDIRECT );
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Where to send this request, or null for no redirect. Only a plain visit to the Dashboard or the Plugins screen is redirected, never a form post,
	 * an AJAX call or a user who may not open the screen or is being sent to enrol.
	 *
	 * @param int              $user_id User.
	 * @param string           $page    Admin page file, for example index.php.
	 * @param bool             $multi   Whether several plugins were activated at once.
	 * @param bool             $ajax        Whether this is an AJAX request.
	 * @param Enforcement|null $enforcement Two-factor rules (tests pass one).
	 */
	public static function redirect_target( int $user_id, string $page, bool $multi, bool $ajax, ?Enforcement $enforcement = null ): ?string {
		if ( false === get_option( FirstRun::REDIRECT ) || $ajax || $multi || ! in_array( $page, array( 'index.php', 'plugins.php' ), true ) ) {
			return null;
		}
		if ( $user_id < 1 || ! user_can( $user_id, self::CAP ) || Enforcement::ALLOW !== ( $enforcement ?? Enforcement::for_site() )->decide( $user_id, 'admin.php' ) ) {
			return null;
		}
		return self::url();
	}

	/**
	 * The screen's address.
	 */
	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::SCREEN );
	}

	/**
	 * A Dashboard widget with the progress, until it is dismissed.
	 */
	public static function add_widget(): void {
		if ( ! current_user_can( self::CAP ) || (bool) get_option( self::DISMISSED, false ) ) {
			return;
		}
		wp_add_dashboard_widget(
			self::WIDGET,
			__( 'Site setup', 'whoiswho' ),
			static function (): void {
				echo self::widget_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
			}
		);
	}

	/**
	 * The widget markup.
	 */
	public static function widget_html(): string {
		$items = Checklist::items();
		$ready = Checklist::ready_count( $items );
		$out   = '<p>' . esc_html(
			sprintf(
				/* translators: 1: steps ready, 2: all steps. */
				__( '%1$d of %2$d setup steps are ready.', 'whoiswho' ),
				$ready,
				count( $items )
			)
		) . '</p><ul>';
		foreach ( $items as $item ) {
			if ( Checklist::OK !== $item['status'] ) {
				$out .= '<li>' . esc_html( $item['title'] ) . '</li>';
			}
		}
		$out .= '</ul><p><a class="button button-primary" href="' . esc_url( self::url() ) . '">' . esc_html__( 'Open Setup', 'whoiswho' ) . '</a> ';
		$out .= self::fix_form( 'dismiss', __( 'Hide this reminder', 'whoiswho' ), 'button-link' ) . '</p>';
		return $out;
	}

	/**
	 * A small form that posts one fix to admin-post.php.
	 *
	 * @param string $what  The fix: permalinks, database, theme or dismiss.
	 * @param string $label Button text.
	 * @param string $css   Button class.
	 */
	private static function fix_form( string $what, string $label, string $css = 'button button-primary' ): string {
		return '<form method="post" class="wiw-inline-form" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_FIX ) . '" />'
			. '<input type="hidden" name="what" value="' . esc_attr( $what ) . '" />'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION_FIX ) ) . '" />'
			. '<button type="submit" class="' . esc_attr( $css ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Load the screen's script and style on the Setup screen only.
	 *
	 * @param string $hook Admin page hook (unused: the page query argument decides).
	 */
	public static function enqueue( string $hook ): void {
		unset( $hook );
		if ( self::SCREEN !== Request::from_globals()->string( 'page' ) || ! current_user_can( self::CAP ) ) {
			return;
		}
		wp_enqueue_style( 'wiw-setup', plugins_url( 'assets/admin/setup/setup.css', WIW_CORE_FILE ), array(), WIW_CORE_VERSION );
		wp_enqueue_script( 'wiw-setup', plugins_url( 'assets/admin/setup/setup.js', WIW_CORE_FILE ), array(), WIW_CORE_VERSION, true );
		$demo = DemoImport::for_site();
		wp_localize_script(
			'wiw-setup',
			'wiwSetup',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonces'  => array(
					'start'  => ActionGuard::nonce_for( self::ACTION_START ),
					'step'   => ActionGuard::nonce_for( self::ACTION_STEP ),
					'remove' => ActionGuard::nonce_for( self::ACTION_REMOVE ),
				),
				'actions' => array(
					'start'  => self::ACTION_START,
					'step'   => self::ACTION_STEP,
					'remove' => self::ACTION_REMOVE,
				),
				'resume'  => 'running' === $demo->state()['status'] || 'failed' === $demo->state()['status'],
				'strings' => array(
					'working'     => __( 'Working...', 'whoiswho' ),
					'finished'    => __( 'Done. The demo content is on your site.', 'whoiswho' ),
					'removed'     => __( 'The demo content has been removed.', 'whoiswho' ),
					'removing'    => __( 'Removing the demo content...', 'whoiswho' ),
					'failed'      => __( 'Something went wrong.', 'whoiswho' ),
					'network'     => __( 'The site did not answer (the connection may have dropped, or the request timed out). Nothing is lost: press Try again to continue from where it stopped.', 'whoiswho' ),
					'no_images'   => __( 'Pictures were skipped because this server has no image library (PHP GD), so pages show without them.', 'whoiswho' ),
					'resume'      => __( 'A demo import was started and did not finish. Press Try again to continue from where it stopped.', 'whoiswho' ),
					'type_needed' => __( 'Type the words in the box first.', 'whoiswho' ),
				),
			)
		);
	}

	/**
	 * The words for a message after a fix.
	 *
	 * @param string $what Fix.
	 * @param bool   $ok   Whether it worked.
	 */
	private static function result_text( string $what, bool $ok ): string {
		$text = array(
			'permalinks' => array( __( 'Pretty web addresses are on.', 'whoiswho' ), __( 'Pretty web addresses could not be switched on. Open Settings > Permalinks and choose Post name.', 'whoiswho' ) ),
			'database'   => array( __( 'The database is ready.', 'whoiswho' ), __( 'The database setup did not finish. The checklist below says why.', 'whoiswho' ) ),
			'theme'      => array( __( 'The child theme is now the active theme.', 'whoiswho' ), __( 'The theme could not be switched. Open Appearance > Themes and activate the child theme.', 'whoiswho' ) ),
			'dismiss'    => array( __( 'The Dashboard reminder is hidden. This screen stays in the admin menu.', 'whoiswho' ), '' ),
		);
		return isset( $text[ $what ] ) ? $text[ $what ][ $ok ? 0 : 1 ] : '';
	}

	/**
	 * Handle a one-click fix (admin-post): do it, then come back to the screen with the result.
	 *
	 * @param Request $request Request.
	 */
	public static function handle_fix( Request $request ): void {
		$what = $request->string( 'what' );
		$ok   = false;
		switch ( $what ) {
			case 'permalinks':
				global $wp_rewrite;
				if ( '' === (string) get_option( 'permalink_structure' ) && $wp_rewrite instanceof \WP_Rewrite ) {
					$wp_rewrite->set_permalink_structure( Permalinks::STRUCTURE );
					$wp_rewrite->flush_rules( false );
				}
				$ok = '' !== (string) get_option( 'permalink_structure' );
				break;
			case 'database':
				$ok = FirstRun::run_database_setup()['ok'];
				break;
			case 'theme':
				if ( current_user_can( 'switch_themes' ) && wp_get_theme( 'whoiswho-child' )->exists() && wp_get_theme( 'whoiswho' )->exists() ) {
					switch_theme( 'whoiswho-child' );
					$ok = 'whoiswho-child' === get_stylesheet();
				}
				break;
			case 'dismiss':
				update_option( self::DISMISSED, '1', false );
				$ok = true;
				break;
		}//end switch
		if ( $ok && 'dismiss' !== $what ) {
			self::audit( 'setup.' . $what );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'wiw_what' => $what,
					'wiw_ok'   => $ok ? '1' : '0',
				),
				self::url()
			)
		);
		exit;
	}

	/**
	 * Audit one setup action (the action name only).
	 *
	 * @param string $action Action.
	 */
	private static function audit( string $action ): void {
		try {
			AuditLogger::for_site()->log(
				$action,
				array(
					'type' => 'site',
					'id'   => 'setup',
				)
			); // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray -- plain subject array.
		} catch ( \Throwable $e ) {
			unset( $e );
			// The audit log being busy must not stop the setup.
		}
	}

	/**
	 * Start (or resume) the demo import (AJAX).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_start( Request $request ): void {
		wp_send_json_success( DemoImport::for_site()->start( get_current_user_id(), $request->string( 'typed' ) ) );
	}

	/**
	 * Run one batch of the demo import (AJAX).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_step( Request $request ): void {
		unset( $request );
		wp_send_json_success( DemoImport::for_site()->advance( get_current_user_id() ) );
	}

	/**
	 * Remove the demo content, a part at a time (AJAX).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_remove( Request $request ): void {
		unset( $request );
		wp_send_json_success( DemoImport::for_site()->remove( get_current_user_id() ) );
	}

	/**
	 * The screen markup.
	 *
	 * @param Request $request Request (carries the result of a fix).
	 * @throws \DomainException Without the capability.
	 */
	public static function html( Request $request ): string {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'Not allowed to use the setup screen.' );
		}
		$items = Checklist::items();
		$ready = Checklist::ready_count( $items );
		$out   = '<div class="wrap wiw-setup"><h1>' . esc_html__( 'Set up your site', 'whoiswho' ) . '</h1>';
		$what  = $request->string( 'wiw_what' );
		if ( '' !== $what && '' !== self::result_text( $what, '1' === $request->string( 'wiw_ok' ) ) ) {
			$good = '1' === $request->string( 'wiw_ok' );
			$out .= '<div class="notice notice-' . ( $good ? 'success' : 'error' ) . '"><p>' . esc_html( self::result_text( $what, $good ) ) . '</p></div>';
		}
		$out .= '<p class="wiw-lead">' . esc_html__( 'Everything you need is on this page. Work down the list: each line says whether it is ready and, when it is not, what to do. You do not need to edit any file or type any command.', 'whoiswho' ) . '</p>';
		$out .= '<p class="wiw-summary" role="status"><strong>' . esc_html(
			sprintf(
				/* translators: 1: steps ready, 2: all steps. */
				__( '%1$d of %2$d steps are ready.', 'whoiswho' ),
				$ready,
				count( $items )
			)
		) . '</strong></p><ol class="wiw-checklist">';
		foreach ( $items as $index => $item ) {
			$out .= self::item_html( $index + 1, $item );
		}
		$out .= '</ol>';
		$out .= '<div class="wiw-setup-foot"><p>' . esc_html__( 'Finished with this page? You can hide the reminder on the Dashboard. The Setup screen stays in the admin menu.', 'whoiswho' ) . '</p>' . self::fix_form( 'dismiss', __( 'Hide the Dashboard reminder', 'whoiswho' ), 'button' ) . '</div></div>';
		return $out;
	}

	/**
	 * One checklist line.
	 *
	 * @param int                                                                                                                 $number Position.
	 * @param array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string} $item   Item.
	 */
	private static function item_html( int $number, array $item ): string {
		$words = array(
			Checklist::OK    => __( 'Ready', 'whoiswho' ),
			Checklist::WARN  => __( 'Recommended', 'whoiswho' ),
			Checklist::ERROR => __( 'Needs attention', 'whoiswho' ),
		);
		$id    = 'wiw-item-' . $item['id'];
		$out   = '<li class="wiw-item wiw-item--' . esc_attr( $item['status'] ) . '" id="' . esc_attr( $id ) . '">';
		$out  .= '<div class="wiw-item-head"><span class="wiw-badge wiw-badge--' . esc_attr( $item['status'] ) . '"><span class="wiw-badge-mark" aria-hidden="true">' . ( Checklist::OK === $item['status'] ? '&#10003;' : ( Checklist::ERROR === $item['status'] ? '!' : '&#8226;' ) ) . '</span> ' . esc_html( $words[ $item['status'] ] ) . '</span>';
		$out  .= '<h2>' . esc_html( (string) $number ) . '. ' . esc_html( $item['title'] ) . '</h2></div>';
		$out  .= '<p>' . esc_html( $item['detail'] ) . '</p>';
		if ( Checklist::OK !== $item['status'] && '' !== $item['fix'] ) {
			$out .= '<p class="wiw-fix"><strong>' . esc_html__( 'What to do:', 'whoiswho' ) . '</strong> ' . esc_html( $item['fix'] ) . '</p>';
		}
		if ( '' !== $item['action'] ) {
			$out .= self::fix_form( $item['action'], $item['action_label'] );
		}
		if ( 'demo' === $item['id'] ) {
			$out .= self::demo_html( Checklist::OK === $item['status'] );
		}
		return $out . '</li>';
	}

	/**
	 * The demo content controls.
	 *
	 * @param bool $installed Whether the demo is on the site.
	 */
	private static function demo_html( bool $installed ): string {
		$demo  = DemoImport::for_site();
		$plan  = $demo->plan();
		$state = $demo->state();
		$real  = 'production' === wp_get_environment_type() && $demo->real_content_count() > 0;
		$out   = '<noscript><p class="wiw-fix">' . esc_html__( 'The demo import needs JavaScript in your browser. Switch it on for this page.', 'whoiswho' ) . '</p></noscript>';
		$out  .= '<div class="wiw-demo" data-installed="' . ( $installed ? '1' : '0' ) . '" data-needs-typed="' . ( $real ? '1' : '0' ) . '" data-state="' . esc_attr( $state['status'] ) . '">';
		$out  .= '<p class="wiw-demo-buttons"><button type="button" class="button button-primary" data-wiw="open-import"' . ( $installed ? ' hidden' : '' ) . '>' . esc_html__( 'Import demo content', 'whoiswho' ) . '</button> ';
		$out  .= '<button type="button" class="button" data-wiw="open-remove"' . ( $installed ? '' : ' hidden' ) . '>' . esc_html__( 'Remove demo content', 'whoiswho' ) . '</button></p>';
		$out  .= '<div class="wiw-confirm" data-wiw="confirm-import" hidden role="group" aria-labelledby="wiw-confirm-import-title"><h3 id="wiw-confirm-import-title">' . esc_html__( 'Import the demo content?', 'whoiswho' ) . '</h3><ul>';
		$out  .= '<li>' . esc_html(
			sprintf(
				/* translators: 1: profiles, 2: stories, 3: events, 4: award categories, 5: pins, 6: pages. */
				__( 'It adds invented content: %1$d profiles, %2$d stories, %3$d events, %4$d award categories, %5$d home page pins and %6$d pages.', 'whoiswho' ),
				$plan['profiles'],
				$plan['stories'],
				$plan['events'],
				$plan['awards'],
				$plan['pins'],
				$plan['pages']
			)
		) . '</li>';
		$out .= '<li>' . esc_html__( 'Everything is fictional and clearly marked as sample content, and the site\'s launch checks will remind you to remove it before going live.', 'whoiswho' ) . '</li>';
		$out .= '<li>' . esc_html__( 'It does not change or delete anything you already have. It does set the new demo Home page as your front page, and puts your old setting back when you remove the demo.', 'whoiswho' ) . '</li>';
		$out .= '<li>' . esc_html__( 'It takes about a minute. Keep this page open until the bar is full. If it stops, press Try again: it carries on where it left off.', 'whoiswho' ) . '</li></ul>';
		if ( $real ) {
			$out .= '<p class="wiw-fix"><strong>' . esc_html__( 'This site already has real profiles or stories.', 'whoiswho' ) . '</strong> <label for="wiw-typed">' . esc_html(
				sprintf(
					/* translators: %s: the words to type. */
					__( 'To add the demo next to them, type %s here:', 'whoiswho' ),
					DemoImport::CONFIRM
				)
			) . '</label> <input type="text" id="wiw-typed" class="regular-text" autocomplete="off" data-wiw="typed" /></p>';
		}
		$out .= '<p><button type="button" class="button button-primary" data-wiw="do-import">' . esc_html__( 'Yes, import the demo content', 'whoiswho' ) . '</button> <button type="button" class="button" data-wiw="cancel">' . esc_html__( 'Cancel', 'whoiswho' ) . '</button></p></div>';
		$out .= '<div class="wiw-confirm" data-wiw="confirm-remove" hidden role="group" aria-labelledby="wiw-confirm-remove-title"><h3 id="wiw-confirm-remove-title">' . esc_html__( 'Remove the demo content?', 'whoiswho' ) . '</h3><p>' . esc_html__( 'This deletes only the demo items (the ones marked as sample content) and puts back the settings the demo changed. Your own content is not touched.', 'whoiswho' ) . '</p>';
		$out .= '<p><button type="button" class="button button-primary" data-wiw="do-remove">' . esc_html__( 'Yes, remove the demo content', 'whoiswho' ) . '</button> <button type="button" class="button" data-wiw="cancel">' . esc_html__( 'Cancel', 'whoiswho' ) . '</button></p></div>';
		$out .= '<div class="wiw-progress-wrap" data-wiw="progress-wrap" hidden><div class="wiw-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-labelledby="wiw-progress-label" data-wiw="bar"><div class="wiw-progress-fill" data-wiw="fill"></div></div>';
		$out .= '<p id="wiw-progress-label" class="wiw-progress-label" data-wiw="label" aria-live="polite"></p>';
		$out .= '<p class="wiw-error" data-wiw="error" role="alert" hidden></p><p><button type="button" class="button button-primary" data-wiw="retry" hidden>' . esc_html__( 'Try again', 'whoiswho' ) . '</button> <a class="button" data-wiw="visit" href="' . esc_url( home_url( '/' ) ) . '" hidden>' . esc_html__( 'Visit the site', 'whoiswho' ) . '</a> <button type="button" class="button" data-wiw="reload" hidden>' . esc_html__( 'Update this checklist', 'whoiswho' ) . '</button></p></div></div>';
		return $out;
	}
}
