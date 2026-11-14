<?php
/**
 * "When shared" rail panel (prototype profile view; REQ-006, REQ-216): how a link to this profile looks when it is shared, built
 * from the read model's share data, so it shows exactly what the share card carries, including the pending-review label. Absent
 * when the state allows no share card. Args: profile (PublicProfile).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\PublicProfile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_profile = isset( $args['profile'] ) ? $args['profile'] : null;
if ( ! $wiw_profile instanceof PublicProfile || empty( $wiw_profile->share['enabled'] ) ) {
	return;
}
$wiw_share = $wiw_profile->share;
$wiw_line  = array_filter( array( (string) apply_filters( 'wiw_brand_name', get_bloginfo( 'name' ) ), (string) ( $wiw_share['title'] ?? '' ) ) );
$wiw_pic   = ! empty( $wiw_share['image_id'] ) ? wp_get_attachment_image( (int) $wiw_share['image_id'], 'wiw-card', false, array( 'alt' => '' ) ) : '';
?>
<section class="rail-card rail-share" aria-labelledby="rail-share-h">
	<h2 id="rail-share-h"><?php esc_html_e( 'When shared', 'whoiswho' ); ?></h2>
	<div class="share">
		<figure class="share-media<?php echo '' === $wiw_pic ? ' share-media--empty' : ''; ?>" aria-hidden="true">
			<?php if ( '' !== $wiw_pic ) : ?>
				<?php echo $wiw_pic; // phpcs:ignore WordPress.Security.EscapeOutput -- built by wp_get_attachment_image. ?>
			<?php else : ?>
				<span class="share-ini"><?php echo esc_html( wiw_initials( $wiw_profile->name ) ); ?></span>
			<?php endif; ?>
		</figure>
		<div>
			<strong><?php echo esc_html( (string) $wiw_share['name'] ); ?></strong>
			<span class="muted"><?php echo esc_html( implode( ' · ', $wiw_line ) ); ?></span>
			<?php if ( ! empty( $wiw_share['label'] ) ) : ?>
				<p class="pending-note"><?php echo esc_html( (string) $wiw_share['label'] ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</section>
