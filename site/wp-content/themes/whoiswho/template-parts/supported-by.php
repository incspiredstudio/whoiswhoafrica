<?php
/**
 * The Supported by block (REQ-186, REQ-199): the sponsors attached to a page or an award category, each as a logo or a name with its
 * link, and the note that sponsors do not choose or influence nominees or winners. It reads sponsors only through PublicSponsor, so
 * nothing commercial (no amount, deal or placement) can be printed, and it prints nothing at all when there are no sponsors.
 * Args: kind (page|award_category), id (page or category id).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Content\PublicSponsor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_kind = isset( $args['kind'] ) && 'award_category' === $args['kind'] ? 'award_category' : 'page';
$wiw_id   = isset( $args['id'] ) ? (int) $args['id'] : 0;
if ( $wiw_id < 1 || ! class_exists( PublicSponsor::class ) ) {
	return;
}
$wiw_sponsors = PublicSponsor::for_subject( $wiw_kind, $wiw_id );
if ( array() === $wiw_sponsors ) {
	return;
}
?>
<section class="aw-sponsors" aria-labelledby="supported-by-h">
	<div class="wrap">
		<h2 id="supported-by-h"><?php esc_html_e( 'Supported by', 'whoiswho' ); ?></h2>
		<ul class="sponsors">
			<?php foreach ( $wiw_sponsors as $wiw_sponsor ) : ?>
				<li class="sponsor">
					<?php
					$wiw_inner = '' !== $wiw_sponsor['logo_url']
						? '<img src="' . esc_url( $wiw_sponsor['logo_url'] ) . '" alt="' . esc_attr( $wiw_sponsor['name'] ) . '" loading="lazy" decoding="async">'
						: '<span>' . esc_html( $wiw_sponsor['name'] ) . '</span>';
					if ( '' !== $wiw_sponsor['link'] ) {
						echo '<a href="' . esc_url( $wiw_sponsor['link'] ) . '" rel="sponsored noopener">' . $wiw_inner . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above.
					} else {
						echo $wiw_inner; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above.
					}
					?>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="small muted"><?php echo esc_html( PublicSponsor::disclosure() ); ?></p>
	</div>
</section>
