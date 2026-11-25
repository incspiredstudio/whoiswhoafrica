<?php
/**
 * Profile header (REQ-003, REQ-247, REQ-009, REQ-203): the breadcrumb Country › Sector › Industry › Name, one portrait or logo
 * (or the monogram) with its credit as the image credit setting asks, name as the page's h1, title and organisation, the
 * pending-review label, and the place line. Classification, organisation type and place come from the read model's methods.
 * There is no cover or banner image. The photo links to its large version; with scripts it opens in a dialog that grows out of
 * the photo (photo-zoom.js), with the credit underneath. Args: profile (PublicProfile).
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
$wiw_role  = wiw_profile_role( $wiw_profile );
$wiw_org   = wiw_profile_fact( $wiw_profile, 'current_organisation' );
$wiw_life  = wiw_profile_lifespan( $wiw_profile );
$wiw_where = wiw_profile_where( $wiw_profile, $wiw_role );
$wiw_crumb = wiw_profile_crumbs( $wiw_profile->name, wiw_profile_classification( $wiw_profile ) );
$wiw_image = $wiw_profile->image();
$wiw_mode  = wiw_profile_credit_mode( $wiw_image );
$wiw_cred  = null !== $wiw_image && 'hidden' !== $wiw_mode ? wiw_profile_credit_html( $wiw_image ) : '';
$wiw_tone  = (int) sprintf( '%u', crc32( $wiw_profile->name ) ) % 6 + 1;
$wiw_logo  = null !== $wiw_image && 'logo' === $wiw_image['kind'] && null !== $wiw_profile->image_id;
$wiw_photo = $wiw_logo ? wiw_logo_img(
	(int) $wiw_profile->image_id,
	array(
		/* translators: %s: name. */
		'alt'           => sprintf( __( 'Logo of %s', 'whoiswho' ), $wiw_profile->name ),
		'loading'       => 'eager',
		'decoding'      => 'async',
		'fetchpriority' => 'high',
	)
) : ( null !== $wiw_profile->image_id ? wp_get_attachment_image(
	$wiw_profile->image_id,
	'wiw-poster',
	false,
	wiw_poster_image_attrs(
		sprintf(
			/* translators: %s: name. */
			'individual' === $wiw_profile->entity_type || 'entrepreneur' === $wiw_profile->entity_type ? __( 'Portrait of %s', 'whoiswho' ) : __( 'Logo of %s', 'whoiswho' ),
			$wiw_profile->name
		),
		'head'
	)
) : '' );
$wiw_large = '' !== $wiw_photo ? wp_get_attachment_image_src( (int) $wiw_profile->image_id, 'large' ) : false;
if ( $wiw_logo && '' !== $wiw_photo ) {
	// A logo opens trimmed, like the header shows it.
	$wiw_trim  = wiw_logo_trimmed( (int) $wiw_profile->image_id );
	$wiw_large = null === $wiw_trim ? false : array( $wiw_trim['url'], $wiw_trim['width'], $wiw_trim['height'] );
}
$wiw_alt   = sprintf(
	/* translators: %s: name. */
	null !== $wiw_image && 'logo' === $wiw_image['kind'] ? __( 'Logo of %s', 'whoiswho' ) : __( 'Photo of %s', 'whoiswho' ),
	$wiw_profile->name
);
?>
<header class="profile-head">
	<div class="wrap" style="padding-block:.75rem">
		<?php echo wp_kses( wiw_profile_crumbs_html( $wiw_crumb ), wiw_crumbs_kses() ); ?>
	</div>
	<div class="wrap">
		<div class="profile-id">
			<?php if ( '' !== $wiw_photo ) : ?>
				<div class="profile-photo">
					<div class="monogram monogram--<?php echo 'organisation' === $wiw_profile->entity_type || 'institution' === $wiw_profile->entity_type ? 'org' : 'person'; ?> monogram--photo<?php echo $wiw_logo ? ' monogram--logo' : ''; ?>">
						<?php if ( is_array( $wiw_large ) ) : ?>
							<a class="photo-zoom" href="<?php echo esc_url( (string) $wiw_large[0] ); ?>" data-photo-zoom="profile-photo-dialog" aria-haspopup="dialog">
								<?php echo $wiw_photo; // phpcs:ignore WordPress.Security.EscapeOutput -- built by wp_get_attachment_image. ?>
								<span class="sr"><?php echo esc_html( null !== $wiw_image && 'logo' === $wiw_image['kind'] ? __( 'Enlarge the logo', 'whoiswho' ) : __( 'Enlarge the photo', 'whoiswho' ) ); ?></span>
							</a>
						<?php else : ?>
							<?php echo $wiw_photo; // phpcs:ignore WordPress.Security.EscapeOutput -- built by wp_get_attachment_image. ?>
						<?php endif; ?>
					</div>
					<?php if ( 'compact' === $wiw_mode && '' !== $wiw_cred ) : ?>
						<button type="button" class="credit-toggle" aria-expanded="false" aria-controls="profile-credit-pop" data-credit-toggle hidden>
							<?php echo wiw_icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?>
							<span class="sr"><?php echo esc_html( 'logo' === $wiw_image['kind'] ? __( 'Logo credit', 'whoiswho' ) : __( 'Photo credit', 'whoiswho' ) ); ?></span>
						</button>
						<p class="credit-pop" id="profile-credit-pop" hidden><?php echo wp_kses( $wiw_cred, wiw_credit_kses() ); ?></p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="monogram monogram--person mono-<?php echo (int) $wiw_tone; ?>" aria-hidden="true"><span><?php echo esc_html( wiw_initials( $wiw_profile->name ) ); ?></span></div>
			<?php endif; ?>
			<div>
				<h1><?php echo esc_html( $wiw_profile->name ); ?></h1>
				<?php if ( '' !== $wiw_life ) : ?>
					<p class="profile-lifespan">
						<span class="sr"><?php esc_html_e( 'Lived', 'whoiswho' ); ?></span>
						<?php echo esc_html( $wiw_life ); ?>
					</p>
				<?php endif; ?>
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
				<?php if ( array() !== $wiw_where ) : ?>
					<p class="profile-loc meta"><?php echo wiw_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?> <?php echo esc_html( implode( ' · ', $wiw_where ) ); ?></p>
				<?php endif; ?>
				<?php if ( 'full' === $wiw_mode && '' !== $wiw_photo && '' !== $wiw_cred ) : ?>
					<p class="profile-credit small"><?php echo wp_kses( $wiw_cred, wiw_credit_kses() ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php if ( is_array( $wiw_large ) ) : ?>
		<dialog class="photo-dialog<?php echo null !== $wiw_image && 'logo' === $wiw_image['kind'] ? ' photo-dialog--logo' : ''; ?>" id="profile-photo-dialog" aria-label="<?php echo esc_attr( $wiw_alt ); ?>">
			<button type="button" class="photo-dialog-close" data-photo-close>
				<?php echo wiw_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?>
				<span class="sr"><?php esc_html_e( 'Close', 'whoiswho' ); ?></span>
			</button>
			<figure>
				<img data-src="<?php echo esc_url( (string) $wiw_large[0] ); ?>" alt="<?php echo esc_attr( $wiw_alt ); ?>" width="<?php echo (int) $wiw_large[1]; ?>" height="<?php echo (int) $wiw_large[2]; ?>" style="--w:<?php echo (int) $wiw_large[1]; ?>;--ar:<?php echo esc_attr( (string) round( (int) $wiw_large[1] / max( 1, (int) $wiw_large[2] ), 4 ) ); ?>" decoding="async" />
				<?php if ( '' !== $wiw_cred ) : ?>
					<figcaption><?php echo wp_kses( $wiw_cred, wiw_credit_kses() ); ?></figcaption>
				<?php endif; ?>
			</figure>
		</dialog>
	<?php endif; ?>
</header>
