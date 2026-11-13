<?php
/**
 * A nominee card (REQ-198, REQ-033): portrait, category, name and citation, as one link to the nominee page. Built only from the
 * PublicNominee read model, which has no nominator, evidence or contact. Args: nominee (PublicNominee), won (bool, show as winner).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Awards\PublicNominee;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_nominee = isset( $args['nominee'] ) && $args['nominee'] instanceof PublicNominee ? $args['nominee'] : null;
if ( null === $wiw_nominee ) {
	return;
}
$wiw_won   = ! empty( $args['won'] ) || $wiw_nominee->is_winner;
$wiw_style = wiw_award_group_style( $wiw_nominee->group_key );
$wiw_photo = null !== $wiw_nominee->profile->image_id ? wp_get_attachment_image(
	$wiw_nominee->profile->image_id,
	'wiw-poster',
	false,
	array_merge( array( 'class' => 'poster-photo' ), wiw_poster_image_attrs( '', 'card' ) )
) : '';
?>
<a class="citation nom-card nom-card--<?php echo esc_attr( $wiw_style['tone'] ); ?><?php echo $wiw_won ? ' nom-card--won' : ' citation--nominee'; ?>" href="<?php echo esc_url( $wiw_nominee->url ); ?>" data-group="<?php echo esc_attr( $wiw_nominee->group_key ); ?>">
	<span class="nom-media">
		<span class="poster poster--<?php echo esc_attr( $wiw_style['tone'] ); ?><?php echo '' === $wiw_photo ? ' poster--org' : ''; ?>" aria-hidden="true">
			<span class="poster-pat"></span>
			<span class="poster-mark poster-mark--<?php echo esc_attr( $wiw_style['mark'] ); ?>"></span>
			<?php if ( '' !== $wiw_photo ) : ?>
				<?php echo $wiw_photo; // phpcs:ignore WordPress.Security.EscapeOutput -- built by wp_get_attachment_image. ?>
			<?php else : ?>
				<span class="poster-ini"><?php echo esc_html( wiw_initials( $wiw_nominee->name ) ); ?></span>
			<?php endif; ?>
		</span>
	</span>
	<span class="nom-body">
		<span class="tag"><?php echo esc_html( ( $wiw_won ? __( 'Winner', 'whoiswho' ) : __( 'Nominee', 'whoiswho' ) ) . ' · ' . $wiw_nominee->category ); ?></span>
		<h3><?php echo esc_html( $wiw_nominee->name ); ?></h3>
		<?php if ( '' !== $wiw_nominee->citation ) : ?>
			<p class="nom-cite"><?php echo esc_html( $wiw_nominee->citation ); ?></p>
		<?php endif; ?>
	</span>
</a>
