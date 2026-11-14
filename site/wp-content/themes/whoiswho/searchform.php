<?php
/**
 * The theme's search form, used wherever WordPress asks for one (the 404 page): it submits to the site's search page (REQ-008), which
 * searches public profiles and stories, never posts and pages, and it takes the theme's field and button look.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_sf_id = wp_unique_id( 'site-search-' );
?>
<form class="dir-search search-form site-search-form" role="search" method="get" action="<?php echo esc_url( home_url( '/search/' ) ); ?>">
	<label class="sr" for="<?php echo esc_attr( $wiw_sf_id ); ?>"><?php esc_html_e( 'Search people, businesses, institutions and stories', 'whoiswho' ); ?></label>
	<input id="<?php echo esc_attr( $wiw_sf_id ); ?>" type="search" name="q" maxlength="80" placeholder="<?php esc_attr_e( 'Name, organisation or keyword', 'whoiswho' ); ?>">
	<button class="btn" type="submit"><?php esc_html_e( 'Search', 'whoiswho' ); ?></button>
</form>
