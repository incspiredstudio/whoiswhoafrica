<?php
/**
 * Safe mode (REQ-046, REQ-056): while the core plugin is missing, inactive or not started, every public page is one neutral holding
 * page. The theme's templates call plugin code, so none of them runs; nothing from the directory can reach a visitor. This is the
 * single guard layer: parent functions.php loads this file instead of the theme's other files.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'template_include',
	static function ( $template ) {
		if ( wiw_core_ready() ) {
			return $template;
		}
		return get_template_directory() . '/safe-mode.php';
	},
	PHP_INT_MAX
);

add_filter(
	'wp_robots',
	static function ( $robots ) {
		return is_array( $robots ) ? array_merge( $robots, array( 'noindex' => true ) ) : $robots;
	}
);
