<?php
/**
 * Site footer landmark. Minimal until the footer issue adds the links and the cookie preferences button.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<footer class="site-foot">
	<div class="wrap">
		<p class="small"><?php echo esc_html( function_exists( 'wiw_brand' ) ? wiw_brand( 'tagline' ) : '' ); ?></p>
	</div>
</footer>
