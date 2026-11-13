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
			echo '<div class="notice notice-error"><p>' . esc_html__( 'WHOISWHO Core needs PHP 8.3 and WordPress 6.5 or newer, so it has not started.', 'whoiswho' ) . '</p></div>';
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

// Action Scheduler is installed by composer (as a library) and loaded from the plugins folder.
$wiw_action_scheduler = dirname( WIW_CORE_DIR ) . '/action-scheduler/action-scheduler.php';
if ( is_readable( $wiw_action_scheduler ) ) {
	require_once $wiw_action_scheduler;
}

// The User Role Editor plugin (pinned by composer) is loaded here, so it cannot be deactivated from wp-admin; its access is decided in Platform\RoleEditor.
\WhoIsWho\Core\Platform\RoleEditor\RoleEditor::load( dirname( WIW_CORE_DIR ) );

register_activation_hook( __FILE__, array( \WhoIsWho\Core\Platform\Activation::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \WhoIsWho\Core\Platform\Activation::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \WhoIsWho\Core\Platform\Plugin::class, 'run' ) );
