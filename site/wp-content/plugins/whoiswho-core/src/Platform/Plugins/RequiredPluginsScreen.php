<?php
/**
 * Site settings > Required plugins (REQ-056, REQ-240): the outside plugins the site needs, whether each is installed and active, and an
 * Install and an Activate button, so nobody has to search the plugin directory. Administrators only (wiw_run_setup), and WordPress's
 * own install_plugins or activate_plugins as well. Every button is a plain form through ActionGuard (capability and nonce), and every
 * install or activation is audited. A host that needs FTP details gets a link to WordPress's own install screen, which asks for them.
 * The list is a shared staff list table (RequiredPluginsTable, REQ-275): bulk Install selected and Activate selected repeat the
 * single actions through run(), and each row has Install, Install with WordPress or Activate as row actions.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Plugins;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Setup\SetupScreen;

/**
 * Required plugins screen.
 */
final class RequiredPluginsScreen {

	public const SCREEN          = 'wiw-required-plugins';
	public const CAP             = SetupScreen::CAP;
	public const ACTION_INSTALL  = 'wiw_plugin_install';
	public const ACTION_ACTIVATE = 'wiw_plugin_activate';

	/**
	 * Attach hooks.
	 */
	public static function hooks(): void {
		add_action( 'init', array( self::class, 'register_admin' ), 1 );
		MissingPluginsNotice::register();
	}

	/**
	 * Register the screen and its actions in the admin only.
	 */
	public static function register_admin(): void {
		if ( ! is_admin() ) {
			return;
		}
		self::register( ScreenRegistry::shared() );
		ScreenRegistry::shared()->hook();
		ActionGuard::register( self::ACTION_INSTALL, self::CAP, array( self::class, 'handle_install' ) );
		ActionGuard::register( self::ACTION_ACTIVATE, self::CAP, array( self::class, 'handle_activate' ) );
		RequiredPluginsTable::register_bulk_handler();
	}

	/**
	 * Register the screen in the Settings menu group.
	 *
	 * @param ScreenRegistry $registry Registry.
	 */
	public static function register( ScreenRegistry $registry ): void {
		$registry->register(
			self::SCREEN,
			__( 'Required plugins', 'whoiswho' ),
			self::CAP,
			static function (): void {
				echo self::html( Request::from_globals() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
			},
			false,
			'settings'
		);
	}

	/**
	 * The screen's address.
	 */
	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::SCREEN );
	}

	/**
	 * Install (admin-post; capability and nonce already checked).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_install( Request $request ): void {
		self::finish( 'install', $request->string( 'plugin' ), self::run( 'install', $request->string( 'plugin' ) ) );
	}

	/**
	 * Activate (admin-post; capability and nonce already checked).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_activate( Request $request ): void {
		self::finish( 'activate', $request->string( 'plugin' ), self::run( 'activate', $request->string( 'plugin' ) ) );
	}

	/**
	 * Install or activate one plugin and audit a success: the single action, shared by its button and the bulk actions.
	 *
	 * @param string               $what      install or activate.
	 * @param string               $slug      Plugin slug.
	 * @param PluginInstaller|null $installer Installer; the site's own by default.
	 * @return string The installer's result.
	 */
	public static function run( string $what, string $slug, ?PluginInstaller $installer = null ): string {
		$installer = $installer ?? PluginInstaller::for_site();
		$result    = 'install' === $what ? $installer->install( $slug ) : $installer->activate( $slug );
		if ( PluginInstaller::OK === $result && RequiredPlugins::known( $slug ) ) {
			self::audit( 'plugins.' . $what, $slug );
		}
		return $result;
	}

	/**
	 * Go back to the screen with the result.
	 *
	 * @param string $what   install or activate.
	 * @param string $slug   Plugin slug.
	 * @param string $result Installer result.
	 */
	private static function finish( string $what, string $slug, string $result ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'wiw_plugin' => RequiredPlugins::known( $slug ) ? $slug : '',
					'wiw_what'   => $what,
					'wiw_result' => $result,
				),
				self::url()
			)
		);
		exit;
	}

	/**
	 * Audit one action on a plugin (the slug only).
	 *
	 * @param string $action Action.
	 * @param string $slug   Slug.
	 */
	private static function audit( string $action, string $slug ): void {
		try {
			AuditLogger::for_site()->log(
				$action,
				array(
					'type' => 'plugin',
					'id'   => $slug,
				)
			); // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray -- plain subject array.
		} catch ( \Throwable $e ) {
			unset( $e );
			// The audit log being busy must not stop the install.
		}
	}

	/**
	 * The words after a button was pressed. Empty when there is nothing to say.
	 *
	 * @param string $what   install or activate.
	 * @param string $slug   Slug.
	 * @param string $result Installer result.
	 * @return array{0: string, 1: string} Notice kind and text.
	 */
	public static function result_text( string $what, string $slug, string $result ): array {
		if ( ! RequiredPlugins::known( $slug ) ) {
			return array( '', '' );
		}
		$name = RequiredPlugins::catalogue()[ $slug ]['name'];
		switch ( $result ) {
			case PluginInstaller::OK:
				return array(
					'success',
					'install' === $what
						/* translators: %s: plugin name. */
						? sprintf( __( '%s is installed. Press Activate to switch it on.', 'whoiswho' ), $name )
						/* translators: %s: plugin name. */
						: sprintf( __( '%s is active.', 'whoiswho' ), $name ),
				);
			case PluginInstaller::CREDENTIALS:
				return array(
					'warning',
					/* translators: %s: plugin name. */
					sprintf( __( 'Your host asks for its file-transfer (FTP) details before plugins can be added. Use the link "Install with WordPress" next to %s: WordPress will ask for those details (your host gives them to you), then install it.', 'whoiswho' ), $name ),
				);
			case PluginInstaller::NOT_ALLOWED:
				return array( 'error', __( 'Your account is not allowed to add or switch on plugins on this site. Ask the site\'s administrator.', 'whoiswho' ) );
			default:
				return array(
					'error',
					'install' === $what
						/* translators: %s: plugin name. */
						? sprintf( __( '%s could not be installed. The site may not be able to reach wordpress.org just now. Try again in a few minutes, or use Plugins > Add New and search for it.', 'whoiswho' ), $name )
						/* translators: %s: plugin name. */
						: sprintf( __( '%s could not be switched on. Open the Plugins screen and press Activate under it; WordPress will say what is wrong.', 'whoiswho' ), $name ),
				);
		}//end switch
	}

	/**
	 * The screen markup.
	 *
	 * @param Request              $request Request (carries the result of a button).
	 * @param RequiredPlugins|null $plugins Status reader (tests pass one).
	 * @throws \DomainException Without the capability.
	 */
	public static function html( Request $request, ?RequiredPlugins $plugins = null ): string {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'Not allowed to use the required plugins screen.' );
		}
		$plugins = $plugins ?? new RequiredPlugins();
		$out     = '<div class="wrap wiw-staff wiw-required-plugins"><h1>' . esc_html__( 'Required plugins', 'whoiswho' ) . '</h1>';
		$notice  = self::result_text( $request->string( 'wiw_what' ), $request->string( 'wiw_plugin' ), $request->string( 'wiw_result' ) );
		if ( '' !== $notice[1] ) {
			$out .= '<div class="notice notice-' . esc_attr( $notice[0] ) . '"><p>' . esc_html( $notice[1] ) . '</p></div>';
		}
		$out .= '<p class="wiw-lead">' . esc_html__( 'The site uses a few free plugins from the WordPress plugin directory for jobs WordPress does not do by itself. Press Install, then Activate, for each one that is not active yet. Nothing else is needed: the site sets them up for you where it can.', 'whoiswho' ) . '</p>';
		if ( ! current_user_can( 'install_plugins' ) ) {
			$out .= '<div class="notice notice-info inline"><p>' . esc_html__( 'This site is set up so that plugins cannot be added from the admin. Ask whoever looks after your hosting to add the plugins below, or to allow plugin installs.', 'whoiswho' ) . '</p></div>';
		}
		$out .= ( new RequiredPluginsTable( $plugins ) )->html( $request );
		return $out . self::button_forms( $plugins ) . '</div>';
	}

	/**
	 * The id of the small form behind one plugin's button.
	 *
	 * @param string $action Action.
	 * @param string $slug   Slug.
	 */
	public static function form_id( string $action, string $slug ): string {
		return ( self::ACTION_INSTALL === $action ? 'wiw-plugin-install-' : 'wiw-plugin-activate-' ) . $slug;
	}

	/**
	 * The small forms the per-plugin buttons post (outside the list form; each button names its form). One per plugin that has a
	 * button: Install for a plugin not installed, Activate for one installed and not active.
	 *
	 * @param RequiredPlugins $plugins Status reader.
	 */
	private static function button_forms( RequiredPlugins $plugins ): string {
		$out = '';
		foreach ( array_keys( RequiredPlugins::catalogue() ) as $slug ) {
			$status = $plugins->status( $slug );
			if ( RequiredPlugins::NOT_INSTALLED === $status && current_user_can( 'install_plugins' ) ) {
				$out .= self::button_form( self::ACTION_INSTALL, $slug );
			} elseif ( RequiredPlugins::INSTALLED === $status && current_user_can( 'activate_plugins' ) ) {
				$out .= self::button_form( self::ACTION_ACTIVATE, $slug );
			}
		}
		return $out;
	}

	/**
	 * A small form that posts one action for one plugin.
	 *
	 * @param string $action Action.
	 * @param string $slug   Slug.
	 */
	private static function button_form( string $action, string $slug ): string {
		return '<form method="post" id="' . esc_attr( self::form_id( $action, $slug ) ) . '" class="wiw-inline-form" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />'
			. '<input type="hidden" name="plugin" value="' . esc_attr( $slug ) . '" />'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( $action ) ) . '" /></form>';
	}
}
