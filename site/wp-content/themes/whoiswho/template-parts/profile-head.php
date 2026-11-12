<?php
/**
 * Profile header (REQ-003, REQ-247): one portrait or logo (or the monogram), name as the page's h1, title and organisation, the
 * pending-review label, and the place line. There is no cover or banner image. Args: profile (PublicProfile).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\PublicProfile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_profile = isset( $args['profile'] ) ? $args['profile'] : null;
if ( ! $wiw_profile instanceof PublicProfile ) {
	return;
}
$wiw_kind  = wiw_profile_kind( $wiw_profile->entity_type );
$wiw_role  = wiw_profile_fact( $wiw_profile, 'current_title' );
$wiw_role  = '' !== $wiw_role ? $wiw_role : wiw_profile_fact( $wiw_profile, 'org_type' );
$wiw_org   = wiw_profile_fact( $wiw_profile, 'current_organisation' );
$wiw_where = array_filter(
	array(
		wiw_profile_fact( $wiw_profile, 'hq_city' ),
		wiw_profile_fact( $wiw_profile, 'industry' ),
		$wiw_kind['label'],
	)
);
$wiw_tone  = (int) sprintf( '%u', crc32( $wiw_profile->name ) ) % 6 + 1;
$wiw_photo = null !== $wiw_profile->image_id ? wp_get_attachment_image(
	$wiw_profile->image_id,
	'wiw-poster',
	false,
	array(
		'alt'      => sprintf(
			/* translators: %s: name. */
			'individual' === $wiw_profile->entity_type || 'entrepreneur' === $wiw_profile->entity_type ? __( 'Portrait of %s', 'whoiswho' ) : __( 'Logo of %s', 'whoiswho' ),
			$wiw_profile->name
		),
		'decoding' => 'async',
	)
) : '';
?>
<header class="profile-head">
	<div class="wrap" style="padding-block:.75rem">
		<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'whoiswho' ); ?>">
			<a href="<?php echo esc_url( $wiw_kind['list'] ); ?>"><?php echo esc_html( $wiw_kind['list_title'] ); ?></a>
			<span aria-hidden="true"> / </span>
			<span aria-current="page"><?php echo esc_html( $wiw_profile->name ); ?></span>
		</nav>
	</div>
	<div class="wrap">
		<div class="profile-id">
			<?php if ( '' !== $wiw_photo ) : ?>
				<div class="monogram monogram--<?php echo 'organisation' === $wiw_profile->entity_type || 'institution' === $wiw_profile->entity_type ? 'org' : 'person'; ?> monogram--photo"><?php echo $wiw_photo; // phpcs:ignore WordPress.Security.EscapeOutput -- built by wp_get_attachment_image. ?></div>
			<?php else : ?>
				<div class="monogram monogram--person mono-<?php echo (int) $wiw_tone; ?>" aria-hidden="true"><span><?php echo esc_html( wiw_initials( $wiw_profile->name ) ); ?></span></div>
			<?php endif; ?>
			<div>
				<h1><?php echo esc_html( $wiw_profile->name ); ?></h1>
				<?php if ( '' !== $wiw_role || '' !== $wiw_org ) : ?>
					<p class="profile-role"><?php echo esc_html( implode( ' · ', array_filter( array( $wiw_role, $wiw_org ) ) ) ); ?></p>
				<?php endif; ?>
				<?php if ( null !== $wiw_profile->label ) : ?>
					<div class="badges"><?php get_template_part( 'template-parts/pending-label', null, array( 'label' => $wiw_profile->label ) ); ?></div>
				<?php endif; ?>
				<?php if ( null !== $wiw_profile->reviewed_at ) : ?>
					<?php $wiw_reviewed = strtotime( $wiw_profile->reviewed_at . ' UTC' ); ?>
					<?php if ( false !== $wiw_reviewed ) : ?>
						<p class="profile-reviewed meta">
							<?php esc_html_e( 'Reviewed on', 'whoiswho' ); ?>
							<time datetime="<?php echo esc_attr( gmdate( 'Y-m-d', $wiw_reviewed ) ); ?>"><?php echo esc_html( wp_date( (string) get_option( 'date_format' ), $wiw_reviewed ) ); ?></time>
						</p>
					<?php endif; ?>
				<?php endif; ?>
				<p class="profile-loc meta"><?php echo wiw_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?> <?php echo esc_html( implode( ' · ', $wiw_where ) ); ?></p>
			</div>
		</div>
	</div>
</header>
