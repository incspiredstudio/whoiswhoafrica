<?php
/**
 * The cookie card (REQ-248, REQ-062): a small region with Decline and Accept. It is hidden in the markup so it never shows without
 * script (then only essential cookies run); cookie.js reveals it after a little scroll, or at once on a short page, when no choice
 * was stored. The footer "Cookie preferences" button reopens it.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="cookie" role="region" aria-label="<?php esc_attr_e( 'Cookie consent', 'whoiswho' ); ?>" data-cookie hidden>
	<p>
		<?php esc_html_e( 'Essential cookies only, unless you accept analytics.', 'whoiswho' ); ?>
		<a href="<?php echo esc_url( home_url( '/cookies/' ) ); ?>"><?php esc_html_e( 'Policy', 'whoiswho' ); ?></a>
	</p>
	<div class="cookie-act">
		<button type="button" class="cookie-btn" data-consent="essential"><?php esc_html_e( 'Decline', 'whoiswho' ); ?></button>
		<button type="button" class="cookie-btn cookie-btn--yes" data-consent="all"><?php esc_html_e( 'Accept', 'whoiswho' ); ?></button>
	</div>
</div>
