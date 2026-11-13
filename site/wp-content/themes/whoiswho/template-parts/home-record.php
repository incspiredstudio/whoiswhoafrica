<?php
/**
 * Home "On the record" block (REQ-257, REQ-218, REQ-001): the profiles picked by the curation engine, shown as a large ruled name index
 * on desktop (hovering a name shows a preview card, see home.js) and as a swipeable rail of poster cards on phones and tablets. Profiles are
 * read only through PublicProfile (via ProfileCuration), so a profile that is not public and reviewed never appears. No trust or paid mark
 * is shown here and no label picks the profiles (D-055). The block is absent when there is nothing to show.
 * Args: home (the home model).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\PublicProfile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_home     = isset( $args['home'] ) && is_array( $args['home'] ) ? $args['home'] : array();
$wiw_profiles = isset( $wiw_home['record'] ) && is_array( $wiw_home['record'] ) ? $wiw_home['record'] : array();
if ( array() === $wiw_profiles ) {
	return;
}
$wiw_fact = static function ( PublicProfile $profile, string $key ): string {
	$value = $profile->facts[ $key ]['value'] ?? '';
	return is_string( $value ) ? $value : '';
};
?>
<section class="h-record" aria-labelledby="home-record-title">
	<div class="wrap">
		<div class="h-sec-head">
			<h2 class="h-kicker" id="home-record-title"><?php esc_html_e( 'On the record', 'whoiswho' ); ?></h2>
			<a class="h-link" href="<?php echo esc_url( home_url( '/people/' ) ); ?>"><?php esc_html_e( 'Open the directory', 'whoiswho' ); ?><?php echo wiw_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?></a>
		</div>
		<ul class="h-index" id="home-index">
			<?php
			foreach ( $wiw_profiles as $wiw_i => $wiw_p ) :
				if ( ! $wiw_p instanceof PublicProfile ) {
					continue;
				}
				$wiw_role = $wiw_fact( $wiw_p, 'current_title' );
				$wiw_org  = $wiw_fact( $wiw_p, 'current_organisation' );
				$wiw_sub  = $wiw_role . ( '' !== $wiw_org && $wiw_org !== $wiw_p->name ? ( '' !== $wiw_role ? ', ' : '' ) . $wiw_org : '' );
				$wiw_pic  = null !== $wiw_p->image_id ? (string) wp_get_attachment_image_url( $wiw_p->image_id, 'wiw-poster' ) : '';
				?>
				<li data-reveal style="--d:<?php echo (int) min( $wiw_i, 5 ); ?>">
					<a href="<?php echo esc_url( $wiw_p->url ); ?>" data-peek-name="<?php echo esc_attr( $wiw_p->name ); ?>"<?php echo '' !== $wiw_pic ? ' data-peek-photo="' . esc_url( $wiw_pic ) . '"' : ''; ?> data-peek-tone="<?php echo esc_attr( wiw_poster_style( $wiw_p->slug )['tone'] ); ?>">
						<span class="h-index-name"><?php echo esc_html( $wiw_p->name ); ?></span>
						<span class="h-index-meta"><span><?php echo esc_html( $wiw_sub ); ?></span><span class="meta"><?php echo esc_html( $wiw_fact( $wiw_p, 'industry' ) ); ?></span></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<ul class="h-rail" aria-label="<?php esc_attr_e( 'Profiles on the record', 'whoiswho' ); ?>">
			<?php foreach ( $wiw_profiles as $wiw_p ) : ?>
				<?php if ( $wiw_p instanceof PublicProfile ) : ?>
					<li class="h-rail-item"><?php get_template_part( 'template-parts/profile-card', null, array( 'profile' => $wiw_p ) ); ?></li>
				<?php endif; ?>
			<?php endforeach; ?>
			<li class="h-rail-item h-rail-end"><a class="h-rail-all" href="<?php echo esc_url( home_url( '/people/' ) ); ?>"><?php esc_html_e( 'Open the directory', 'whoiswho' ); ?><?php echo wiw_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?></a></li>
		</ul>
	</div>
</section>
<div class="h-peek" id="home-peek" aria-hidden="true"></div>
