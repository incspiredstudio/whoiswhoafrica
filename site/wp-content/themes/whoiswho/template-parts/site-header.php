<?php
/**
 * Site header landmark. Minimal until the header issue adds the menu, drawer and Get listed button.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<header class="site-head">
	<div class="wrap head-bar">
		<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( wiw_site_name() ); ?></a>
	</div>
</header>
