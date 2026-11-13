<?php
/**
 * Profile page (REQ-003, REQ-004, REQ-005, REQ-247, REQ-082): header, a two-column body (sections left, rail slot right, stacked
 * on phones). Reads the profile only through PublicProfile. Only sections with content appear. No cover or banner image, no
 * email or phone (the read model has none), links are plain links. Actions and sources arrive in the rail and sections filters.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_profile = wiw_current_profile();
get_header();
if ( null === $wiw_profile ) {
	get_template_part(
		'template-parts/notice',
		null,
		array(
			'kind' => 'info',
			'text' => __( 'This profile is not available.', 'whoiswho' ),
		)
	);
	get_footer();
	return;
}
$wiw_sections = wiw_profile_sections( $wiw_profile );
$wiw_site     = wiw_profile_fact( $wiw_profile, 'official_website' );
$wiw_site     = '' !== $wiw_site ? $wiw_site : wiw_profile_fact( $wiw_profile, 'website' );
?>
<article class="profile">
	<?php get_template_part( 'template-parts/profile-preview-banner', null, array( 'profile' => $wiw_profile ) ); ?>
	<?php get_template_part( 'template-parts/profile-head', null, array( 'profile' => $wiw_profile ) ); ?>
	<div class="wrap split split--rail profile-body">
		<div class="profile-main">
			<?php
			if ( null !== $wiw_profile->label ) {
				get_template_part(
					'template-parts/pending-label',
					null,
					array(
						'label'       => $wiw_profile->label,
						'variant'     => 'notice',
						'correct_url' => home_url( '/correct/' . $wiw_profile->slug . '/' ),
						'remove_url'  => home_url( '/remove/' . $wiw_profile->slug . '/' ),
					)
				);
			}
			?>
			<?php foreach ( $wiw_sections as $wiw_section ) : ?>
				<section id="<?php echo esc_attr( (string) $wiw_section['id'] ); ?>" aria-labelledby="<?php echo esc_attr( (string) $wiw_section['id'] ); ?>-h">
					<h2 id="<?php echo esc_attr( (string) $wiw_section['id'] ); ?>-h"><?php echo esc_html( (string) $wiw_section['title'] ); ?></h2>
					<?php if ( isset( $wiw_section['text'] ) ) : ?>
						<p><?php echo esc_html( (string) $wiw_section['text'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $wiw_section['items'] ) ) : ?>
						<ul class="timeline-simple">
							<?php foreach ( (array) $wiw_section['items'] as $wiw_item ) : ?>
								<li style="grid-template-columns:1fr"><span><?php echo esc_html( (string) $wiw_item ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
			<?php get_template_part( 'template-parts/profile-related-stories', null, array( 'profile' => $wiw_profile ) ); ?>
			<?php if ( '' !== $wiw_site ) : ?>
				<p style="margin-top:1rem">
					<?php esc_html_e( 'Website:', 'whoiswho' ); ?>
					<a href="<?php echo esc_url( $wiw_site ); ?>" rel="nofollow noopener"><?php echo esc_html( (string) preg_replace( '#^https?://#', '', $wiw_site ) ); ?></a>
				</p>
			<?php endif; ?>
		</div>
		<aside class="profile-rail" aria-label="<?php esc_attr_e( 'Actions', 'whoiswho' ); ?>">
			<?php
			/**
			 * The right rail of a profile: enquiry, claim, correction, removal and report actions are added by their own issues.
			 *
			 * @param \WhoIsWho\Core\Directory\PublicProfile $profile Profile.
			 */
			do_action( 'wiw_profile_rail', $wiw_profile );
			?>
		</aside>
	</div>
</article>
<?php
get_footer();
