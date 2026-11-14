<?php
/**
 * The outside plugins the site needs (REQ-056, REQ-075, REQ-258): FluentSMTP sends the site's email and wpDiscuz is the comment form
 * on stories. Two Factor is recommended; whoiswho-core already carries a copy, so it shows as included when that copy is loaded.
 * This class only reads: which of them are not installed, installed or active. Installing and activating is PluginInstaller.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Plugins;

/**
 * Required plugins.
 */
final class RequiredPlugins {

	public const NOT_INSTALLED = 'not_installed';
	public const INSTALLED     = 'installed';
	public const ACTIVE        = 'active';
	public const INCLUDED      = 'included';

	/**
	 * Installed plugins: plugin file => header data (get_plugins()).
	 *
	 * @var callable(): array<string, mixed>
	 */
	private $installed;

	/**
	 * Whether a plugin file is active (is_plugin_active()).
	 *
	 * @var callable(string): bool
	 */
	private $active;

	/**
	 * Whether a slug is already loaded by whoiswho-core itself.
	 *
	 * @var callable(string): bool
	 */
	private $included;

	/**
	 * Constructor. Tests pass their own readers.
	 *
	 * @param callable|null $installed Returns get_plugins().
	 * @param callable|null $active    Takes a plugin file, returns whether it is active.
	 * @param callable|null $included  Takes a slug, returns whether whoiswho-core loads it already.
	 */
	public function __construct( ?callable $installed = null, ?callable $active = null, ?callable $included = null ) {
		$this->installed = $installed ?? static function (): array {
			self::load_admin_functions();
			return get_plugins();
		};
		$this->active    = $active ?? static function ( string $file ): bool {
			self::load_admin_functions();
			return is_plugin_active( $file );
		};
		$this->included  = $included ?? static fn ( string $slug ): bool => 'two-factor' === $slug && class_exists( '\Two_Factor_Core' );
	}

	/**
	 * The plugins, by wordpress.org slug. The file is where WordPress puts the plugin when it installs it.
	 *
	 * @return array<string, array{name: string, file: string, required: bool, why: string}>
	 */
	public static function catalogue(): array {
		return array(
			'fluent-smtp' => array(
				'name'     => 'FluentSMTP',
				'file'     => 'fluent-smtp/fluent-smtp.php',
				'required' => true,
				'why'      => __( 'Sends the site\'s email (confirmations, invitations and notices) through your email account, so messages arrive instead of landing in spam. After you activate it, open Settings > FluentSMTP and connect your email account.', 'whoiswho' ),
			),
			'wpdiscuz'    => array(
				'name'     => 'wpDiscuz',
				'file'     => 'wpdiscuz/class.WpdiscuzCore.php',
				'required' => true,
				'why'      => __( 'The comment form under stories. The site sets it up to its own comment rules for you: stories only, name, email and consent, every comment held for approval, no profile pictures.', 'whoiswho' ),
			),
			'two-factor'  => array(
				'name'     => 'Two Factor',
				'file'     => 'two-factor/two-factor.php',
				'required' => false,
				'why'      => __( 'A second sign-in step (an app code or an email code) for staff accounts. Recommended.', 'whoiswho' ),
			),
		);
	}

	/**
	 * Whether a slug is one of ours.
	 *
	 * @param string $slug Slug.
	 */
	public static function known( string $slug ): bool {
		return isset( self::catalogue()[ $slug ] );
	}

	/**
	 * The installed plugin file for a slug: the usual file, or any plugin in a folder of that name. Empty when not installed.
	 *
	 * @param string $slug Slug.
	 */
	public function file( string $slug ): string {
		if ( ! self::known( $slug ) ) {
			return '';
		}
		$installed = ( $this->installed )();
		$expected  = self::catalogue()[ $slug ]['file'];
		if ( isset( $installed[ $expected ] ) ) {
			return $expected;
		}
		foreach ( array_keys( $installed ) as $file ) {
			if ( dirname( (string) $file ) === $slug ) {
				return (string) $file;
			}
		}
		return '';
	}

	/**
	 * The status of one plugin: not installed, installed (not active), active, or included (loaded by whoiswho-core). Read fresh
	 * each time: an install or activation in between changes it.
	 *
	 * @phpstan-impure
	 * @param string $slug Slug.
	 */
	public function status( string $slug ): string {
		$file = $this->file( $slug );
		if ( '' !== $file && ( $this->active )( $file ) ) {
			return self::ACTIVE;
		}
		if ( ( $this->included )( $slug ) ) {
			return self::INCLUDED;
		}
		return '' === $file ? self::NOT_INSTALLED : self::INSTALLED;
	}

	/**
	 * The required plugins that are not active yet.
	 *
	 * @return string[] Slugs.
	 */
	public function missing_required(): array {
		$out = array();
		foreach ( self::catalogue() as $slug => $plugin ) {
			if ( $plugin['required'] && self::ACTIVE !== $this->status( $slug ) ) {
				$out[] = $slug;
			}
		}
		return $out;
	}

	/**
	 * WordPress's plugin functions live in an admin file that is not loaded on every request.
	 */
	public static function load_admin_functions(): void {
		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}
}
