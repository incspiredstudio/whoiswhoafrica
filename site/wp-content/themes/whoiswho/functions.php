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

define( 'WIW_THEME_VERSION', '0.1.0' );

foreach ( array( 'setup', 'cleanup', 'parent-notice', 'assets', 'enqueue', 'images', 'layout', 'system-pages', 'legal', 'components', 'navigation', 'email', 'events', 'patterns', 'consent', 'link-page', 'profile', 'industries', 'directory', 'stories', 'settings', 'forms', 'home', 'comments', 'enquiry' ) as $wiw_inc ) {
	require_once get_template_directory() . '/inc/' . $wiw_inc . '.php';
}
