<?php
/**
 * WHOISWHO child theme: the active theme. Loads the parent first, then the child's own files.
 * Brand values live here (assets/tokens), never in the parent.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WIW_CHILD_VERSION', '0.2.0' );

require_once get_stylesheet_directory() . '/inc/status.php';
require_once get_stylesheet_directory() . '/inc/assets.php';
require_once get_stylesheet_directory() . '/inc/brand.php';
