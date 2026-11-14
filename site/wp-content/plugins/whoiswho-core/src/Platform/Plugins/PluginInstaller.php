<?php
/**
 * Installs and activates the required plugins from wordpress.org the way WordPress itself does (REQ-056): plugins_api() finds the
 * download, core's Plugin_Upgrader installs it, activate_plugin() switches it on. Only the plugins in RequiredPlugins can be
 * installed here, and only by a user WordPress lets install (install_plugins) or activate (activate_plugins) plugins. A host that
 * needs FTP details for file changes gets core's own install screen instead, which asks for them.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Plugins;

/**
 * Plugin installer.
 */
final class PluginInstaller {

	public const OK          = 'ok';
	public const CREDENTIALS = 'credentials';
	public const FAILED      = 'failed';
	public const NOT_ALLOWED = 'not_allowed';
	public const UNKNOWN     = 'unknown';

	/**
	 * Status reader.
	 *
	 * @var RequiredPlugins
	 */
	private RequiredPlugins $plugins;

	/**
	 * Takes a slug, returns the wordpress.org information (an object with download_link) or a WP_Error.
	 *
	 * @var callable(string): mixed
	 */
	private $api;

	/**
	 * Returns an upgrader: an object with install( string $package ) like core's Plugin_Upgrader.
	 *
	 * @var callable(): object
	 */
	private $upgrader;

	/**
	 * Whether files can be written without asking for FTP details.
	 *
	 * @var callable(): bool
	 */
	private $can_write;

	/**
	 * Takes a plugin file, activates it, returns null or a WP_Error (activate_plugin()).
	 *
	 * @var callable(string): mixed
	 */
	private $activator;

	/**
	 * Constructor. Tests pass fakes for the network, the upgrader and the file system.
	 *
	 * @param RequiredPlugins $plugins   Status reader.
	 * @param callable|null   $api       Slug => plugin information.
	 * @param callable|null   $upgrader  Builds the upgrader.
	 * @param callable|null   $can_write Whether WordPress can write files directly.
	 * @param callable|null   $activator Activates a plugin file.
	 */
	public function __construct( RequiredPlugins $plugins, ?callable $api = null, ?callable $upgrader = null, ?callable $can_write = null, ?callable $activator = null ) {
		$this->plugins   = $plugins;
		$this->api       = $api ?? static function ( string $slug ) {
			self::load_upgrader();
			return plugins_api(
				'plugin_information',
				array(
					'slug'   => $slug,
					'fields' => array( 'sections' => false ),
				)
			);
		};
		$this->upgrader  = $upgrader ?? static function (): object {
			self::load_upgrader();
			return new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );
		};
		$this->can_write = $can_write ?? static function (): bool {
			self::load_upgrader();
			if ( 'direct' === get_filesystem_method() ) {
				return true;
			}
			return defined( 'FTP_HOST' ) && defined( 'FTP_USER' ) && defined( 'FTP_PASS' );
		};
		$this->activator = $activator ?? static function ( string $file ) {
			RequiredPlugins::load_admin_functions();
			return activate_plugin( $file );
		};
	}

	/**
	 * The installer for this site.
	 */
	public static function for_site(): self {
		return new self( new RequiredPlugins() );
	}

	/**
	 * Install one of the required plugins.
	 *
	 * @param string $slug Slug.
	 * @return string One of the result constants.
	 */
	public function install( string $slug ): string {
		if ( ! RequiredPlugins::known( $slug ) ) {
			return self::UNKNOWN;
		}
		if ( ! current_user_can( 'install_plugins' ) ) {
			return self::NOT_ALLOWED;
		}
		if ( RequiredPlugins::NOT_INSTALLED !== $this->plugins->status( $slug ) ) {
			return self::OK;
		}
		if ( ! ( $this->can_write )() ) {
			return self::CREDENTIALS;
		}
		$info = ( $this->api )( $slug );
		$link = is_object( $info ) && isset( $info->download_link ) ? (string) $info->download_link : '';
		if ( is_wp_error( $info ) || ! $this->from_wordpress_org( $link ) ) {
			return self::FAILED;
		}
		$upgrader = ( $this->upgrader )();
		if ( ! method_exists( $upgrader, 'install' ) ) {
			return self::FAILED;
		}
		$result = $upgrader->install( $link );
		if ( true !== $result ) {
			return self::FAILED;
		}
		return RequiredPlugins::NOT_INSTALLED === $this->plugins->status( $slug ) ? self::FAILED : self::OK;
	}

	/**
	 * Activate one of the required plugins.
	 *
	 * @param string $slug Slug.
	 * @return string One of the result constants.
	 */
	public function activate( string $slug ): string {
		if ( ! RequiredPlugins::known( $slug ) ) {
			return self::UNKNOWN;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return self::NOT_ALLOWED;
		}
		$status = $this->plugins->status( $slug );
		if ( RequiredPlugins::ACTIVE === $status || RequiredPlugins::INCLUDED === $status ) {
			return self::OK;
		}
		$file = $this->plugins->file( $slug );
		if ( '' === $file ) {
			return self::FAILED;
		}
		$result = ( $this->activator )( $file );
		return null === $result || true === $result ? self::OK : self::FAILED;
	}

	/**
	 * WordPress's own install screen for a slug, which asks for FTP details when the host needs them.
	 *
	 * @param string $slug Slug.
	 */
	public static function core_install_url( string $slug ): string {
		return wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . rawurlencode( $slug ) ), 'install-plugin_' . $slug );
	}

	/**
	 * Only a download from wordpress.org is installed.
	 *
	 * @param string $link Download link.
	 */
	private function from_wordpress_org( string $link ): bool {
		$host = (string) wp_parse_url( $link, PHP_URL_HOST );
		return 'https' === wp_parse_url( $link, PHP_URL_SCHEME ) && ( 'downloads.wordpress.org' === $host || 'wordpress.org' === $host );
	}

	/**
	 * Core's upgrader and plugin-install files.
	 */
	private static function load_upgrader(): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
	}
}
