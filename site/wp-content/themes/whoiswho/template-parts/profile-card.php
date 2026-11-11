<?php
/**
 * Profile card for lists, home and search (REQ-216, REQ-155, REQ-247): poster, industry, name, role. No Verified, Featured,
 * Sponsored or Claimed mark and no cover image: the only label is the pending-review one, read from the profile.
 * Reads the profile only through PublicProfile. Args: profile (PublicProfile).
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

$wiw_fact  = static function ( string $key ) use ( $wiw_profile ): string {
	$value = $wiw_profile->facts[ $key ]['value'] ?? '';
	return is_string( $value ) ? $value : '';
};
$wiw_style = wiw_poster_style( $wiw_profile->slug );
$wiw_ind   = $wiw_fact( 'industry' );
$wiw_role  = '' !== $wiw_fact( 'current_title' ) ? $wiw_fact( 'current_title' ) : $wiw_fact( 'org_type' );
$wiw_org   = '' !== $wiw_fact( 'current_organisation' ) ? $wiw_fact( 'current_organisation' ) : '';
$wiw_sub   = $wiw_role;
if ( '' !== $wiw_org && $wiw_org !== $wiw_profile->name ) {
	$wiw_sub .= ( '' !== $wiw_sub ? ', ' : '' ) . $wiw_org;
}
$wiw_pend  = null !== $wiw_profile->label;
$wiw_photo = null !== $wiw_profile->image_id ? wp_get_attachment_image(
	$wiw_profile->image_id,
	'wiw-poster',
	false,
	array(
		'class'    => 'poster-photo',
		'alt'      => '',
		'loading'  => 'lazy',
		'decoding' => 'async',
	)
) : '';
?>
<article class="pcard<?php echo $wiw_pend ? ' pcard--pending' : ''; ?>" data-profile="<?php echo esc_attr( $wiw_profile->slug ); ?>">
	<span class="poster poster--<?php echo esc_attr( $wiw_style['tone'] ); ?><?php echo '' === $wiw_photo ? ' poster--org' : ''; ?> pcard-media" aria-hidden="true">
		<span class="poster-pat"></span>
		<span class="poster-mark poster-mark--<?php echo esc_attr( $wiw_style['mark'] ); ?>"></span>
		<?php if ( '' !== $wiw_photo ) : ?>
			<?php echo $wiw_photo; // phpcs:ignore WordPress.Security.EscapeOutput -- built by wp_get_attachment_image. ?>
		<?php else : ?>
			<span class="poster-ini"><?php echo esc_html( wiw_initials( $wiw_profile->name ) ); ?></span>
		<?php endif; ?>
	</span>
	<div class="pcard-body">
		<?php if ( '' !== $wiw_ind ) : ?>
			<p class="pcard-kick"><?php echo esc_html( $wiw_ind ); ?></p>
		<?php endif; ?>
		<h3 class="pcard-name"><a class="stretched" href="<?php echo esc_url( $wiw_profile->url ); ?>"><?php echo esc_html( $wiw_profile->name ); ?></a></h3>
		<?php if ( '' !== $wiw_sub ) : ?>
			<p class="pcard-sub"><?php echo esc_html( $wiw_sub ); ?></p>
		<?php endif; ?>
		<?php if ( $wiw_pend ) : ?>
			<div class="badges">
				<?php get_template_part( 'template-parts/pending-label', null, array( 'label' => $wiw_profile->label ) ); ?>
			</div>
		<?php endif; ?>
		<span class="pcard-go" aria-hidden="true"><?php echo wiw_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?></span>
	</div>
</article>
