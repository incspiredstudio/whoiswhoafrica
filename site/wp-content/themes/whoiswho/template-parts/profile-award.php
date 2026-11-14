<?php
/**
 * Award poster at the top of a profile (prototype profile view; REQ-033): the edition, "Winner" or "Nominated for" the category,
 * the public citation and a link to the nominee page, on the award group's colour with its mark and the approved pattern. Shown
 * only for a live nomination of a reviewed public profile (PublicNominee decides). Args: nominee (PublicNominee).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Awards\PublicNominee;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_nominee = isset( $args['nominee'] ) ? $args['nominee'] : null;
if ( ! $wiw_nominee instanceof PublicNominee ) {
	return;
}
$wiw_style = wiw_award_group_style( $wiw_nominee->group_key );
/* translators: %d: award edition year. */
$wiw_edition = sprintf( __( '%d edition', 'whoiswho' ), $wiw_nominee->year );
?>
<section id="award" class="prof-award poster poster--<?php echo esc_attr( $wiw_style['tone'] ); ?>" aria-labelledby="award-h">
	<span class="poster-pat" aria-hidden="true"></span>
	<span class="poster-mark poster-mark--<?php echo esc_attr( $wiw_style['mark'] ); ?>" aria-hidden="true"></span>
	<div class="prof-award-in">
		<p class="prof-award-k"><?php echo wiw_icon( 'star' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?> <?php echo esc_html( $wiw_edition ); ?></p>
		<h2 id="award-h">
			<?php
			/* translators: %s: award category. */
			echo esc_html( sprintf( $wiw_nominee->is_winner ? __( 'Winner, %s', 'whoiswho' ) : __( 'Nominated for %s', 'whoiswho' ), $wiw_nominee->category ) );
			?>
		</h2>
		<?php if ( '' !== $wiw_nominee->citation ) : ?>
			<p class="prof-award-cite"><?php echo esc_html( $wiw_nominee->citation ); ?></p>
		<?php endif; ?>
		<a class="btn prof-award-btn" href="<?php echo esc_url( $wiw_nominee->url ); ?>"><?php esc_html_e( 'See the nomination', 'whoiswho' ); ?> <?php echo wiw_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?></a>
	</div>
</section>
