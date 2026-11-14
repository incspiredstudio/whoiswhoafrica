<?php
/**
 * WHOISWHO parent theme bootstrap. Loads the files in inc/. Contains no brand values.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WIW_THEME_VERSION', '0.2.0' );

// The theme's files call the core plugin. Without it, load only the required-plugin notice and the holding page (REQ-046, REQ-056).
require_once get_template_directory() . '/inc/dependencies.php';
if ( ! wiw_core_ready() ) {
	require_once get_template_directory() . '/inc/parent-notice.php';
	require_once get_template_directory() . '/inc/safe-mode.php';
	return;
}

foreach ( array( 'setup', 'cleanup', 'parent-notice', 'assets', 'enqueue', 'images', 'layout', 'system-pages', 'legal', 'components', 'navigation', 'email', 'events', 'patterns', 'consent', 'link-page', 'profile', 'industries', 'directory', 'stories', 'settings', 'forms', 'home', 'comments', 'enquiry', 'pages', 'claim', 'requests', 'blocks', 'awards', 'faq', 'newsletter', 'invitation' ) as $wiw_inc ) {
	require_once get_template_directory() . '/inc/' . $wiw_inc . '.php';
}
