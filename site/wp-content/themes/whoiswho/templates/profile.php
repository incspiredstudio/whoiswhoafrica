<?php
/**
 * Profile page (REQ-003, REQ-004, REQ-005, REQ-247, REQ-082, REQ-033, REQ-162): header, a two-column body (sections left, rail
 * right, stacked on phones), matching the prototype profile view. The award poster leads the body when the profile has a live
 * nomination or win. The narrative sections Bio, Achievement, Influence and Legacy (contract 1.1) follow, each with a heading
 * and its paragraphs, then the other sections, key facts and one related-stories block. The rail holds contact, the "Something
 * wrong?" actions and the "When shared" preview. Reads the profile only through PublicProfile (and the nominee through
 * PublicNominee). No cover or banner image, no email or phone.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Awards\PublicNominee;

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
$wiw_sections  = wiw_profile_sections( $wiw_profile );
$wiw_narrative = $wiw_profile->sections();
$wiw_site      = wiw_profile_fact( $wiw_profile, 'official_website' );
$wiw_site      = '' !== $wiw_site ? $wiw_site : wiw_profile_fact( $wiw_profile, 'website' );
$wiw_nominee   = class_exists( PublicNominee::class ) ? PublicNominee::for_profile( $wiw_profile->id ) : null;
?>
<article class="profile">
	<?php get_template_part( 'template-parts/profile-preview-banner', null, array( 'profile' => $wiw_profile ) ); ?>
	<?php get_template_part( 'template-parts/profile-head', null, array( 'profile' => $wiw_profile ) ); ?>
	<div class="wrap split split--rail profile-body">
		<div class="profile-main">
			<?php
			if ( null !== $wiw_nominee ) {
				get_template_part( 'template-parts/profile-award', null, array( 'nominee' => $wiw_nominee ) );
			} else {
				// Another feature may still supply an award panel.
				$wiw_award = trim( (string) apply_filters( 'wiw_profile_award_panel', '', $wiw_profile ) );
				if ( '' !== $wiw_award ) {
					echo '<section id="award" aria-labelledby="award-h"><h2 id="award-h">' . esc_html__( 'Award', 'whoiswho' ) . '</h2>' . wp_kses_post( $wiw_award ) . '</section>';
				}
			}
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
			<?php foreach ( $wiw_narrative as $wiw_part ) : ?>
				<section id="<?php echo esc_attr( $wiw_part['key'] ); ?>" class="profile-section profile-section--<?php echo esc_attr( $wiw_part['key'] ); ?>" aria-labelledby="<?php echo esc_attr( $wiw_part['key'] ); ?>-h">
					<h2 id="<?php echo esc_attr( $wiw_part['key'] ); ?>-h"><?php echo esc_html( wiw_profile_section_title( $wiw_part['key'], $wiw_part['title'] ) ); ?></h2>
					<div class="profile-prose">
						<?php foreach ( wiw_profile_paragraphs( $wiw_part['text'] ) as $wiw_paragraph ) : ?>
							<p><?php echo nl2br( esc_html( $wiw_paragraph ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped first; nl2br only adds line breaks. ?></p>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
			<?php foreach ( $wiw_sections as $wiw_section ) : ?>
				<section id="<?php echo esc_attr( (string) $wiw_section['id'] ); ?>" class="profile-section" aria-labelledby="<?php echo esc_attr( (string) $wiw_section['id'] ); ?>-h">
					<h2 id="<?php echo esc_attr( (string) $wiw_section['id'] ); ?>-h"><?php echo esc_html( (string) $wiw_section['title'] ); ?></h2>
					<?php if ( isset( $wiw_section['text'] ) ) : ?>
						<div class="profile-prose">
							<?php foreach ( wiw_profile_paragraphs( (string) $wiw_section['text'] ) as $wiw_paragraph ) : ?>
								<p><?php echo esc_html( $wiw_paragraph ); ?></p>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $wiw_section['items'] ) ) : ?>
						<ul class="timeline-simple">
							<?php foreach ( (array) $wiw_section['items'] as $wiw_item ) : ?>
								<li class="timeline-simple-one"><span><?php echo esc_html( (string) $wiw_item ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
			<?php get_template_part( 'template-parts/profile-facts', null, array( 'profile' => $wiw_profile ) ); ?>
			<?php if ( '' !== $wiw_site ) : ?>
				<p class="profile-website">
					<?php esc_html_e( 'Website:', 'whoiswho' ); ?>
					<a href="<?php echo esc_url( $wiw_site ); ?>" rel="nofollow noopener"><?php echo esc_html( (string) preg_replace( '#^https?://#', '', $wiw_site ) ); ?></a>
				</p>
			<?php endif; ?>
			<?php get_template_part( 'template-parts/profile-related-stories', null, array( 'profile' => $wiw_profile ) ); ?>
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
			get_template_part( 'template-parts/profile-share', null, array( 'profile' => $wiw_profile ) );
			?>
		</aside>
	</div>
</article>
<?php
get_footer();
