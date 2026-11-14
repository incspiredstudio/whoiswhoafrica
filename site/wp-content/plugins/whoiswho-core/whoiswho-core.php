<?php
/**
 * Plugin Name:       WHOISWHO Core
 * Description:       The WHOISWHO domain: directory, review, import, trust, commerce, content, awards, compliance and platform.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.3
 * Text Domain:       whoiswho
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WIW_CORE_VERSION', '0.1.0' );
define( 'WIW_CORE_FILE', __FILE__ );
define( 'WIW_CORE_DIR', plugin_dir_path( __FILE__ ) );

require_once WIW_CORE_DIR . 'src/Platform/Requirements.php';

if ( ! \WhoIsWho\Core\Platform\Requirements::met( PHP_VERSION, get_bloginfo( 'version' ) ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'WHOISWHO Core needs PHP 8.3 and WordPress 6.5 or newer, so it has not started. In cPanel, open "Select PHP Version" and choose 8.3 (or ask your host to do it), and update WordPress under Dashboard > Updates.', 'whoiswho' ) . '</p></div>';
		}
	);
	return;
}

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'WhoIsWho\\Core\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$path = WIW_CORE_DIR . 'src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

// Action Scheduler is installed by composer (as a library) and loaded from the plugins folder, or from this plugin's bundled folder in the install bundle (Platform\ThirdParty).
$wiw_action_scheduler = \WhoIsWho\Core\Platform\ThirdParty::dir_for( 'action-scheduler', WIW_CORE_DIR ) . '/action-scheduler/action-scheduler.php';
if ( is_readable( $wiw_action_scheduler ) ) {
	require_once $wiw_action_scheduler;
}

// The Two Factor plugin (pinned by composer) is loaded here too, so staff cannot deactivate it from wp-admin; its settings are applied by code.
\WhoIsWho\Core\Platform\TwoFactor\TwoFactor::load( \WhoIsWho\Core\Platform\ThirdParty::dir_for( 'two-factor', WIW_CORE_DIR ) );
// The User Role Editor plugin (pinned by composer) is loaded here, so it cannot be deactivated from wp-admin; its access is decided in Platform\RoleEditor.
\WhoIsWho\Core\Platform\RoleEditor\RoleEditor::load( \WhoIsWho\Core\Platform\ThirdParty::dir_for( 'user-role-editor', WIW_CORE_DIR ) );
// The page-cache plugin (pinned by composer) is loaded here only when the secrets file switches the cache on (WP_CACHE).
\WhoIsWho\Core\Platform\Cache\PageCache\PageCache::load( \WhoIsWho\Core\Platform\ThirdParty::dir_for( 'wp-super-cache', WIW_CORE_DIR ) );

register_activation_hook( __FILE__, array( \WhoIsWho\Core\Platform\Activation::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \WhoIsWho\Core\Platform\Activation::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \WhoIsWho\Core\Platform\Plugin::class, 'run' ) );
