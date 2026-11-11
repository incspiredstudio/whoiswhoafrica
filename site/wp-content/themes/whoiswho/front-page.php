<?php
/**
 * Front page stub. The real home (hero, blocks, rails) is built in M2; this keeps the page valid, with one h1.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
wiw_page_band(
	array(
		'title' => wiw_site_name(),
		'lede'  => function_exists( 'wiw_brand' ) ? wiw_brand( 'tagline' ) : '',
	)
);
get_footer();
