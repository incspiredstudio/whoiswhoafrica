<?php
/**
 * Where the third-party plugin code this plugin loads lives (REQ-046, REQ-056). A composer or managed install puts it beside this
 * plugin (wp-content/plugins/two-factor); the install bundle ships it inside this plugin (whoiswho-core/bundled/two-factor), so
 * uploading the one zip to a plain WordPress or WordPress Studio is enough. A copy beside the plugin wins.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform;

/**
 * Third-party plugin locations.
 */
final class ThirdParty {

	/**
	 * The folder that holds the plugin folder for a slug.
	 *
	 * @param string $slug     Plugin folder name, for example "two-factor".
	 * @param string $core_dir This plugin's folder (WIW_CORE_DIR).
	 */
	public static function dir_for( string $slug, string $core_dir ): string {
		$core    = rtrim( $core_dir, '/\\' );
		$beside  = dirname( $core );
		$bundled = $core . '/bundled';
		if ( is_dir( $beside . '/' . $slug ) ) {
			return $beside;
		}
		return is_dir( $bundled . '/' . $slug ) ? $bundled : $beside;
	}
}
