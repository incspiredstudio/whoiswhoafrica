<?php
/**
 * Profile page (REQ-003, REQ-004, REQ-005, REQ-247, REQ-082): header, a two-column body (sections left, rail slot right, stacked
 * on phones). Reads the profile only through PublicProfile. Only sections with content appear. No cover or banner image, no
 * email or phone (the read model has none), links are plain links. Actions and sources arrive in the rail and sections filters.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Content\PublicStory;

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
			<?php if ( '' !== $wiw_site ) : ?>
				<p style="margin-top:1rem">
					<?php esc_html_e( 'Website:', 'whoiswho' ); ?>
					<a href="<?php echo esc_url( $wiw_site ); ?>" rel="nofollow noopener"><?php echo esc_html( (string) preg_replace( '#^https?://#', '', $wiw_site ) ); ?></a>
				</p>
			<?php endif; ?>
			<?php
			get_template_part( 'template-parts/profile-facts', null, array( 'profile' => $wiw_profile ) );
			// The award panel is absent until a published nomination or win exists (the awards feature supplies it through this filter).
			$wiw_award = trim( (string) apply_filters( 'wiw_profile_award_panel', '', $wiw_profile ) );
			if ( '' !== $wiw_award ) :
				?>
				<section id="award" aria-labelledby="award-h">
					<h2 id="award-h"><?php esc_html_e( 'Award', 'whoiswho' ); ?></h2>
					<?php echo wp_kses_post( $wiw_award ); ?>
				</section>
			<?php endif; ?>
			<?php
			$wiw_stories = class_exists( PublicStory::class ) ? PublicStory::for_profile( $wiw_profile->id ) : array();
			if ( array() !== $wiw_stories ) :
				?>
				<section id="related-stories" aria-labelledby="related-stories-h">
					<h2 id="related-stories-h"><?php esc_html_e( 'Related stories', 'whoiswho' ); ?></h2>
					<ul class="ev-rel-list">
						<?php foreach ( $wiw_stories as $wiw_story ) : ?>
							<li><a href="<?php echo esc_url( (string) ( $wiw_story['url'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $wiw_story['title'] ?? '' ) ); ?></a><span class="muted small"> <?php echo esc_html( (string) ( $wiw_story['type'] ?? '' ) ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
		</div>
		<aside class="profile-rail" aria-label="<?php esc_attr_e( 'Actions', 'whoiswho' ); ?>">
			<?php
			get_template_part( 'template-parts/profile-rail', null, array( 'profile' => $wiw_profile ) );
			/**
			 * Further rail blocks added by other features.
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
