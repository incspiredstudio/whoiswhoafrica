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
use WhoIsWho\Core\Compliance\Forms\TurnstileSettings;

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
	public const ACTION_PART   = 'wiw_demo_remove_part';

	public const ACTION_TURNSTILE = 'wiw_setup_turnstile';
	public const TURNSTILE_ERROR  = 'wiw_setup_turnstile_error';
	public const DB_RESULT        = 'wiw_setup_db_result';
	public const BACKUP_FIELD     = 'backup_confirmed';

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
		ActionGuard::register( self::ACTION_TURNSTILE, self::CAP, array( self::class, 'handle_turnstile' ) );
		ActionGuard::register( self::ACTION_PART, self::CAP, array( self::class, 'handle_remove_part' ), true );
	}

	/**
	 * Save the Turnstile keys (admin-post). The secret is never sent back to the browser; the screen says only that it is saved.
	 * The change is audited without either key.
	 *
	 * @param Request $request Request.
	 */
	public static function handle_turnstile( Request $request ): void {
		$result = self::save_turnstile( $request );
		wp_safe_redirect(
			add_query_arg(
				array(
					'wiw_what' => 'turnstile',
					'wiw_ok'   => '' === $result ? '1' : '0',
				),
				self::url()
			) . '#wiw-item-turnstile'
		);
		exit;
	}

	/**
	 * Check and save the Turnstile keys (no output, so it can be tested).
	 *
	 * @param Request $request Request.
	 * @return string An error message, or an empty string when saved.
	 */
	public static function save_turnstile( Request $request ): string {
		if ( TurnstileSettings::from_constants() ) {
			return __( 'Your host set these keys for the site, so they cannot be changed here.', 'whoiswho' );
		}
		$values = TurnstileSettings::validate( $request->string( 'turnstile_site_key' ), $request->string( 'turnstile_secret' ), '1' === $request->string( 'turnstile_remove' ) );
		if ( ! $values['ok'] ) {
			update_option( self::TURNSTILE_ERROR, $values['error'], false );
			return $values['error'];
		}
		delete_option( self::TURNSTILE_ERROR );
		TurnstileSettings::save( $values );
		self::audit( $values['remove'] ? 'setup.turnstile_removed' : 'setup.turnstile_saved' );
		return '';
	}

	/**
	 * The Turnstile key form.
	 */
	private static function turnstile_form(): string {
		if ( TurnstileSettings::from_constants() ) {
			return '';
		}
		$site_key = TurnstileSettings::site_key();
		$saved    = TurnstileSettings::has_secret();
		$out      = '<form method="post" class="wiw-turnstile-form" data-personal-data="none" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out     .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_TURNSTILE ) . '" />';
		$out     .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION_TURNSTILE ) ) . '" />';
		$out     .= '<p><label for="wiw-turnstile-site-key">' . esc_html__( 'Site key', 'whoiswho' ) . '</label><br />';
		$out     .= '<input type="text" id="wiw-turnstile-site-key" name="turnstile_site_key" class="regular-text" autocomplete="off" spellcheck="false" value="' . esc_attr( $site_key ) . '" /></p>';
		$out     .= '<p><label for="wiw-turnstile-secret">' . esc_html__( 'Secret key', 'whoiswho' ) . '</label><br />';
		$out     .= '<input type="password" id="wiw-turnstile-secret" name="turnstile_secret" class="regular-text" autocomplete="new-password" spellcheck="false" value="" aria-describedby="wiw-turnstile-secret-note" />';
		$out     .= '<br /><span class="description" id="wiw-turnstile-secret-note">' . esc_html( $saved ? __( 'Saved. It is not shown again. Leave this empty to keep it, or paste a new one to replace it.', 'whoiswho' ) : __( 'Not saved yet.', 'whoiswho' ) ) . '</span></p>';
		if ( '' !== $site_key || $saved ) {
			$out .= '<p><label><input type="checkbox" name="turnstile_remove" value="1" /> ' . esc_html__( 'Remove both keys and go back to the built-in spam check', 'whoiswho' ) . '</label></p>';
		}
		return $out . '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save Turnstile keys', 'whoiswho' ) . '</button></p></form>';
	}

	/**
	 * Send the administrator to the Setup screen once, right after activation.
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
	 * an AJAX call or a user who may not open the screen.
	 *
	 * @param int    $user_id User.
	 * @param string $page    Admin page file, for example index.php.
	 * @param bool   $multi   Whether several plugins were activated at once.
	 * @param bool   $ajax    Whether this is an AJAX request.
	 */
	public static function redirect_target( int $user_id, string $page, bool $multi, bool $ajax ): ?string {
		if ( false === get_option( FirstRun::REDIRECT ) || $ajax || $multi || ! in_array( $page, array( 'index.php', 'plugins.php' ), true ) ) {
			return null;
		}
		if ( $user_id < 1 || ! user_can( $user_id, self::CAP ) ) {
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
	 * The "Update the database" form for a site that already has its database: a plain backup warning and an "I have a backup"
	 * box that must be ticked. A first install needs no backup and keeps the plain button.
	 *
	 * @param string $label Button text.
	 */
	private static function database_form( string $label ): string {
		if ( SchemaState::may_self_initialise() ) {
			return self::fix_form( 'database', $label );
		}
		return '<form method="post" class="wiw-db-form" data-personal-data="none" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_FIX ) . '" />'
			. '<input type="hidden" name="what" value="database" />'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION_FIX ) ) . '" />'
			. '<p class="wiw-fix"><strong>' . esc_html__( 'Take a backup in cPanel first.', 'whoiswho' ) . '</strong> ' . esc_html__( 'In cPanel, open Backup (or your host\'s backup tool) and download a full backup, or at least the database. The update only adds to the database and cannot be undone from here.', 'whoiswho' ) . '</p>'
			. '<p><label><input type="checkbox" name="' . esc_attr( self::BACKUP_FIELD ) . '" value="1" required /> ' . esc_html__( 'I have a backup', 'whoiswho' ) . '</label></p>'
			. '<p><button type="submit" class="button button-primary">' . esc_html( $label ) . '</button></p></form>';
	}

	/**
	 * Apply the waiting database changes (the Setup screen's button; the caller has checked wiw_run_setup and the nonce through
	 * ActionGuard). On a site that already has its database the "I have a backup" box must be ticked. The changes run through the
	 * locked migration runner, and the result is audited and kept in plain words for the screen.
	 *
	 * @param Request     $request Request.
	 * @param string|null $dir     Migrations folder (tests pass one).
	 * @return array{ok: bool, applied: string[], error: string}
	 */
	public static function update_database( Request $request, ?string $dir = null ): array {
		$fresh = SchemaState::may_self_initialise();
		if ( ! $fresh && '1' !== $request->string( self::BACKUP_FIELD ) ) {
			$message = __( 'Nothing was changed. Take a backup first, then tick "I have a backup" and press the button again.', 'whoiswho' );
			update_option( self::DB_RESULT, $message, false );
			return array(
				'ok'      => false,
				'applied' => array(),
				'error'   => $message,
			);
		}
		$result = FirstRun::run_database_setup( $dir );
		if ( $result['ok'] ) {
			$count   = count( $result['applied'] );
			$message = 0 === $count
				? __( 'The database was already up to date. Nothing needed changing.', 'whoiswho' )
				/* translators: %d: number of database changes applied. */
				: sprintf( _n( 'The database is up to date: %d change was applied.', 'The database is up to date: %d changes were applied.', $count, 'whoiswho' ), $count );
		} else {
			$message = __( 'The database update did not finish, and the site is as it was before the step that stopped. The checklist below says why. You can press the button again; it carries on from where it stopped.', 'whoiswho' );
		}
		update_option( self::DB_RESULT, $message, false );
		try {
			AuditLogger::for_site()->log(
				$result['ok'] ? 'setup.database_updated' : 'setup.database_update_failed',
				array(
					'type' => 'site',
					'id'   => 'setup',
				),
				null,
				array(
					'applied' => $result['applied'],
					'fresh'   => $fresh,
				)
			); // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray -- plain subject array.
		} catch ( \Throwable $e ) {
			unset( $e );
			// The audit log being busy must not stop the update.
		}
		return $result;
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
					'part'   => ActionGuard::nonce_for( self::ACTION_PART ),
				),
				'actions' => array(
					'start'  => self::ACTION_START,
					'step'   => self::ACTION_STEP,
					'remove' => self::ACTION_REMOVE,
					'part'   => self::ACTION_PART,
				),
				'resume'  => 'running' === $demo->state()['status'] || 'failed' === $demo->state()['status'],
				'parts'   => self::parts_config(),
				'strings' => array(
					'none_chosen'  => __( 'Tick at least one part to import.', 'whoiswho' ),
					/* translators: 1: the part that was ticked for you, 2: why. */
					'auto_ticked'  => __( '%1$s was ticked too. %2$s', 'whoiswho' ),
					/* translators: 1: the parts that were unticked, 2: the part they need. */
					'auto_cleared' => __( '%1$s cannot be imported without %2$s, so it was unticked too.', 'whoiswho' ),
					/* translators: %s: part name. */
					'remove_title' => __( 'Remove the demo %s?', 'whoiswho' ),
					'remove_text'  => __( 'This deletes only the demo items of this part. Anything a person changed after the import is kept and listed, and from then on counts as your own content.', 'whoiswho' ),
					/* translators: %s: list of parts. */
					'remove_also'  => __( 'This also removes: %s, because they need it.', 'whoiswho' ),
					'part_removed' => __( 'That part of the demo content has been removed.', 'whoiswho' ),
					'kept'         => __( 'Kept because a person changed them after the import (they now count as your own content):', 'whoiswho' ),
					'working'      => __( 'Working...', 'whoiswho' ),
					'finished'     => __( 'Done. The demo content is on your site.', 'whoiswho' ),
					'removed'      => __( 'The demo content has been removed.', 'whoiswho' ),
					'removing'     => __( 'Removing the demo content...', 'whoiswho' ),
					'failed'       => __( 'Something went wrong.', 'whoiswho' ),
					'network'      => __( 'The site did not answer (the connection may have dropped, or the request timed out). Nothing is lost: press Try again to continue from where it stopped.', 'whoiswho' ),
					'no_images'    => __( 'Pictures were skipped because this server has no image library (PHP GD), so pages show without them.', 'whoiswho' ),
					'resume'       => __( 'A demo import was started and did not finish. Press Try again to continue from where it stopped.', 'whoiswho' ),
					'type_needed'  => __( 'Type the words in the box first.', 'whoiswho' ),
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
			'database'   => array(
				(string) get_option( self::DB_RESULT, __( 'The database is ready.', 'whoiswho' ) ),
				(string) get_option( self::DB_RESULT, __( 'The database setup did not finish. The checklist below says why.', 'whoiswho' ) ),
			),
			'theme'      => array( __( 'The child theme is now the active theme.', 'whoiswho' ), __( 'The theme could not be switched. Open Appearance > Themes and activate the child theme.', 'whoiswho' ) ),
			'dismiss'    => array( __( 'The Dashboard reminder is hidden. This screen stays in the admin menu.', 'whoiswho' ), '' ),
			'turnstile'  => array(
				__( 'The Turnstile settings are saved.', 'whoiswho' ),
				(string) get_option( self::TURNSTILE_ERROR, __( 'The Turnstile keys were not saved.', 'whoiswho' ) ),
			),
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
				$ok = self::update_database( $request )['ok'];
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
		if ( $ok && ! in_array( $what, array( 'dismiss', 'database' ), true ) ) {
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
		wp_send_json_success( DemoImport::for_site()->start( get_current_user_id(), $request->string( 'typed' ), self::parts_from( $request ) ) );
	}

	/**
	 * The parts a start request asks for: the comma-separated `parts` field, known names only. A request without the field (an older
	 * page) asks for every part.
	 *
	 * @param Request $request Request.
	 * @return string[]
	 */
	public static function parts_from( Request $request ): array {
		return $request->has( 'parts' ) ? DemoParts::parse( $request->string( 'parts' ) ) : DemoParts::PARTS;
	}

	/**
	 * Remove one part of the demo content, a few items at a time (AJAX).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_remove_part( Request $request ): void {
		wp_send_json_success( DemoImport::for_site()->remove_part( $request->string( 'part' ), get_current_user_id() ) );
	}

	/**
	 * What the chooser script needs to know about the parts: names, what each needs and why, and what needs each.
	 *
	 * @return array{labels: array<string, string>, needs: array<string, string[]>, reasons: array<string, string>, dependents: array<string, string[]>}
	 */
	public static function parts_config(): array {
		$dependents = array();
		foreach ( DemoParts::PARTS as $part ) {
			$dependents[ $part ] = DemoParts::dependents( $part );
		}
		return array(
			'labels'     => DemoParts::labels(),
			'needs'      => DemoParts::NEEDS,
			'reasons'    => DemoParts::reasons(),
			'dependents' => $dependents,
		);
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
	 * @param int                                                                                                                                                   $number Position.
	 * @param array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string, link: string, link_label: string} $item   Item.
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
		if ( 'database' === $item['action'] ) {
			$out .= self::database_form( $item['action_label'] );
		} elseif ( '' !== $item['action'] ) {
			$out .= self::fix_form( $item['action'], $item['action_label'] );
		}
		if ( '' !== $item['link'] ) {
			$out .= '<p><a class="button" href="' . esc_url( $item['link'] ) . '">' . esc_html( $item['link_label'] ) . '</a></p>';
		}
		if ( 'turnstile' === $item['id'] ) {
			$out .= self::turnstile_form();
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
		$out  .= self::chooser_html( $demo );
		$out  .= '<p class="wiw-demo-buttons"><button type="button" class="button button-primary" data-wiw="open-import">' . esc_html__( 'Import demo content', 'whoiswho' ) . '</button> ';
		$out  .= '<button type="button" class="button" data-wiw="open-remove"' . ( $installed ? '' : ' hidden' ) . '>' . esc_html__( 'Remove demo content', 'whoiswho' ) . '</button></p>';
		$out  .= '<div class="wiw-confirm" data-wiw="confirm-import" hidden role="group" aria-labelledby="wiw-confirm-import-title"><h3 id="wiw-confirm-import-title">' . esc_html__( 'Import the chosen parts?', 'whoiswho' ) . '</h3>';
		$out  .= '<p data-wiw="chosen"></p><ul>';
		if ( $demo->ships_profiles() ) {
			$out .= '<li>' . esc_html(
				sprintf(
					/* translators: %d: number of profiles. */
					__( 'Profiles: %d sample profiles that come with the plugin, about real public figures and organisations, with their sources. They are imported like any import and published as reviewed by you.', 'whoiswho' ),
					$plan['profiles']
				)
			) . '</li>';
			$out .= '<li>' . esc_html__( 'Everything else is invented content: stories, comments, events and awards are fictional and clearly marked as sample content.', 'whoiswho' ) . '</li>';
		} else {
			$out .= '<li>' . esc_html(
				sprintf(
					/* translators: 1: profiles, 2: stories, 3: comments, 4: events, 5: award categories, 6: pins, 7: pages. */
					__( 'It adds invented content: up to %1$d profiles, %2$d stories, %3$d comments, %4$d events, %5$d award categories, %6$d home page pins and %7$d pages.', 'whoiswho' ),
					$plan['profiles'],
					$plan['stories'],
					$plan['comments'],
					$plan['events'],
					$plan['awards'],
					$plan['pins'],
					$plan['pages']
				)
			) . '</li>';
			$out .= '<li>' . esc_html__( 'Everything is fictional and clearly marked as sample content.', 'whoiswho' ) . '</li>';
		}//end if
		$out .= '<li>' . esc_html__( 'The site\'s launch checks will remind you to remove the demo before going live. Each part has its own Remove button, and anything you change after the import is never deleted.', 'whoiswho' ) . '</li>';
		$out .= '<li>' . esc_html__( 'It does not change or delete anything you already have. The Pages part sets the demo Home page as your front page, and puts your old setting back when you remove it.', 'whoiswho' ) . '</li>';
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
		$out .= '<div class="wiw-confirm" data-wiw="confirm-remove" hidden role="group" aria-labelledby="wiw-confirm-remove-title"><h3 id="wiw-confirm-remove-title">' . esc_html__( 'Remove the demo content?', 'whoiswho' ) . '</h3><p>' . esc_html__( 'This deletes every part of the demo (only the items marked as sample content) and puts back the settings the demo changed. Your own content is not touched, and a demo item a person changed after the import is kept and listed.', 'whoiswho' ) . '</p>';
		$out .= '<p><button type="button" class="button button-primary" data-wiw="do-remove">' . esc_html__( 'Yes, remove the demo content', 'whoiswho' ) . '</button> <button type="button" class="button" data-wiw="cancel">' . esc_html__( 'Cancel', 'whoiswho' ) . '</button></p></div>';
		$out .= self::remove_part_html();
		$out .= '<div class="wiw-progress-wrap" data-wiw="progress-wrap" hidden><div class="wiw-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-labelledby="wiw-progress-label" data-wiw="bar"><div class="wiw-progress-fill" data-wiw="fill"></div></div>';
		$out .= '<p id="wiw-progress-label" class="wiw-progress-label" data-wiw="label" aria-live="polite"></p>';
		$out .= '<p class="wiw-error" data-wiw="error" role="alert" hidden></p><p><button type="button" class="button button-primary" data-wiw="retry" hidden>' . esc_html__( 'Try again', 'whoiswho' ) . '</button> <a class="button" data-wiw="visit" href="' . esc_url( home_url( '/' ) ) . '" hidden>' . esc_html__( 'Visit the site', 'whoiswho' ) . '</a> <button type="button" class="button" data-wiw="reload" hidden>' . esc_html__( 'Update this checklist', 'whoiswho' ) . '</button></p>';
		$out .= '<div class="wiw-kept" data-wiw="kept" hidden><p>' . esc_html__( 'Kept because a person changed them after the import (they now count as your own content):', 'whoiswho' ) . '</p><ul data-wiw="kept-list"></ul></div></div></div>';
		return $out;
	}

	/**
	 * The checkboxes: one per part, with what it adds, what it needs, and for a part on the site when it came and a Remove button.
	 *
	 * @param DemoImport $demo The demo.
	 */
	private static function chooser_html( DemoImport $demo ): string {
		$labels  = DemoParts::labels();
		$texts   = DemoParts::descriptions();
		$status  = $demo->parts_status();
		$nothing = array() === $status;
		$out     = '<fieldset class="wiw-parts-box"><legend class="wiw-parts-legend">' . esc_html__( 'Choose the parts to import', 'whoiswho' ) . '</legend>';
		$out    .= '<p class="description">' . esc_html(
			$demo->ships_profiles()
				? __( 'The profiles are the sample profiles that come with the plugin. The other parts are invented.', 'whoiswho' )
				: __( 'This copy of the plugin has no sample profiles, so the Profiles part adds invented ones.', 'whoiswho' )
		) . '</p><ul class="wiw-parts">';
		foreach ( DemoParts::PARTS as $part ) {
			$id    = 'wiw-part-' . $part;
			$here  = isset( $status[ $part ] );
			$needs = array_map( static fn ( string $need ): string => $labels[ $need ], DemoParts::NEEDS[ $part ] ?? array() );
			$out  .= '<li class="wiw-part" data-part="' . esc_attr( $part ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" value="' . esc_attr( $part ) . '" data-wiw-part="' . esc_attr( $part ) . '"' . ( ! $here && $nothing ? ' checked' : '' ) . ' /> ';
			$out  .= '<label for="' . esc_attr( $id ) . '"><strong>' . esc_html( $labels[ $part ] ) . '</strong></label>';
			$out  .= '<span class="wiw-part-text">' . esc_html( $texts[ $part ] );
			if ( array() !== $needs ) {
				$out .= ' <em>' . esc_html(
					sprintf(
						/* translators: %s: parts it needs. */
						__( 'Needs: %s.', 'whoiswho' ),
						implode( ', ', $needs )
					)
				) . '</em>';
			}
			$out .= '</span>';
			if ( $here ) {
				$out .= '<span class="wiw-part-status" data-wiw="part-status">' . esc_html(
					sprintf(
						/* translators: 1: number of items, 2: date. */
						__( 'Imported %1$d on %2$s.', 'whoiswho' ),
						$status[ $part ]['count'],
						(string) wp_date( (string) get_option( 'date_format', 'j F Y' ), $status[ $part ]['at'] )
					)
				) . '</span> <button type="button" class="button button-small" data-wiw="remove-part" data-part="' . esc_attr( $part ) . '">' . esc_html(
					sprintf(
						/* translators: %s: part name. */
						__( 'Remove %s', 'whoiswho' ),
						$labels[ $part ]
					)
				) . '</button>';
			}
			$out .= '</li>';
		}//end foreach
		$out .= '</ul><p class="wiw-auto-note" data-wiw="auto-note" role="status" aria-live="polite"></p></fieldset>';
		return $out;
	}

	/**
	 * The confirmation for removing one part (the script fills in which).
	 */
	private static function remove_part_html(): string {
		return '<div class="wiw-confirm" data-wiw="confirm-part" hidden role="group" aria-labelledby="wiw-confirm-part-title"><h3 id="wiw-confirm-part-title" data-wiw="part-title"></h3><p data-wiw="part-text"></p>'
			. '<p><button type="button" class="button button-primary" data-wiw="do-remove-part">' . esc_html__( 'Yes, remove it', 'whoiswho' ) . '</button> <button type="button" class="button" data-wiw="cancel">' . esc_html__( 'Cancel', 'whoiswho' ) . '</button></p></div>';
	}
}
