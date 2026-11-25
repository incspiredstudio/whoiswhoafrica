<?php
/**
 * Industries (REQ-007, REQ-009): the index at /industries lists the sectors, each with its industries grouped under it and a
 * public count on every row; the industry page at /industries/{slug} (a sector or an industry) shows its reviewed profiles and, only when there are any, its stories. Counts and profiles come from
 * ProfileList, which never includes a profile that is pending review, hidden or removed; stories come from PublicStory.
 * An industry with no public profiles shows an empty state.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Content\PublicStory;
use WhoIsWho\Core\Directory\ProfileList;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_term = wiw_industry_term();
$wiw_list = class_exists( ProfileList::class ) ? new ProfileList() : null;

get_header();

if ( null === $wiw_term ) :
	$wiw_rows = null !== $wiw_list ? $wiw_list->industry_counts() : array();
	wiw_page_band(
		array(
			'eyebrow' => __( 'Directory', 'whoiswho' ),
			'title'   => __( 'Industries', 'whoiswho' ),
			'tone'    => 'dark',
			'lede'    => __( 'Browse the record by sector. Each count is the number of reviewed profiles.', 'whoiswho' ),
			'wide'    => true,
		)
	);
	?>
<section class="section section--tight ind-index">
	<div class="wrap">
		<?php if ( array() === $wiw_rows ) : ?>
			<?php
			get_template_part(
				'template-parts/empty-state',
				null,
				array(
					'title' => __( 'No industries yet', 'whoiswho' ),
					'text'  => __( 'Industries appear here once profiles have been reviewed.', 'whoiswho' ),
				)
			);
			?>
		<?php else : ?>
			<?php
			// Sectors in name order, each with its industries under it as the prototype's index list: the name, the count and
			// an arrow. The sector heading links to the whole sector.
			?>
			<div class="ind-sectors">
				<?php foreach ( wiw_industry_sectors( $wiw_rows ) as $wiw_sector ) : ?>
					<section class="ind-sector" aria-labelledby="ind-<?php echo esc_attr( $wiw_sector['slug'] ); ?>">
						<h2 class="ind-sector-h" id="ind-<?php echo esc_attr( $wiw_sector['slug'] ); ?>">
							<a href="<?php echo esc_url( home_url( '/industries/' . $wiw_sector['slug'] . '/' ) ); ?>">
								<span class="ind-name"><?php echo esc_html( $wiw_sector['name'] ); ?></span>
								<span class="ind-count">
									<?php
									/* translators: %d: number of profiles. */
									echo esc_html( sprintf( _n( '%d profile', '%d profiles', $wiw_sector['count'], 'whoiswho' ), $wiw_sector['count'] ) );
									?>
								</span>
							</a>
						</h2>
						<?php if ( array() !== $wiw_sector['industries'] ) : ?>
							<ul class="index-list ind-list">
								<?php foreach ( $wiw_sector['industries'] as $wiw_row ) : ?>
									<li>
										<a class="ind-tile" href="<?php echo esc_url( home_url( '/industries/' . $wiw_row['slug'] . '/' ) ); ?>">
											<span class="ind-name"><?php echo esc_html( $wiw_row['name'] ); ?></span>
											<span class="ind-count">
												<?php
												/* translators: %d: number of profiles. */
												echo esc_html( sprintf( _n( '%d profile', '%d profiles', $wiw_row['count'], 'whoiswho' ), $wiw_row['count'] ) );
												?>
												<?php echo wiw_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?>
											</span>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</section>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
	<?php
else :
	$wiw_page    = max( 1, (int) get_query_var( 'paged', 1 ) );
	$wiw_result  = null !== $wiw_list ? $wiw_list->query(
		'all',
		array(
			'industry' => $wiw_term->slug,
			'page'     => $wiw_page,
		)
	) : array(
		'profiles' => array(),
		'total'    => 0,
		'page'     => 1,
		'pages'    => 1,
	);
	$wiw_stories = class_exists( PublicStory::class ) ? PublicStory::for_industry( $wiw_term->slug ) : array();
	$wiw_base    = home_url( '/industries/' . $wiw_term->slug . '/' );
	wiw_page_band(
		array(
			'eyebrow' => __( 'Industry', 'whoiswho' ),
			'title'   => $wiw_term->name,
			'tone'    => 'dark',
			/* translators: %d: number of profiles. */
			'lede'    => sprintf( _n( '%d reviewed profile in this industry.', '%d reviewed profiles in this industry.', (int) $wiw_result['total'], 'whoiswho' ), (int) $wiw_result['total'] ),
			'wide'    => true,
		)
	);
	?>
<section class="section section--tight ind-page">
	<div class="wrap">
		<p class="ind-back"><a href="<?php echo esc_url( home_url( '/industries/' ) ); ?>"><?php esc_html_e( 'All industries', 'whoiswho' ); ?></a></p>
		<?php
		// A sector page lists its industries as chips, each a link to that industry's page (REQ-009).
		$wiw_children = 0 === (int) $wiw_term->parent && null !== $wiw_list ? wiw_sector_industries( $wiw_list->industry_counts(), (int) $wiw_term->term_id ) : array();
		?>
		<?php if ( array() !== $wiw_children ) : ?>
			<nav class="ind-chips" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: sector name. */ __( 'Industries in %s', 'whoiswho' ), $wiw_term->name ) ); ?>">
				<ul>
					<?php foreach ( $wiw_children as $wiw_child ) : ?>
						<li>
							<a class="ind-chip" href="<?php echo esc_url( $wiw_child['url'] ); ?>">
								<span><?php echo esc_html( $wiw_child['name'] ); ?></span>
								<span class="ind-chip-n" aria-hidden="true"><?php echo esc_html( number_format_i18n( $wiw_child['count'] ) ); ?></span>
								<span class="sr"><?php echo esc_html( sprintf( /* translators: %d: number of profiles. */ _n( ', %d profile', ', %d profiles', $wiw_child['count'], 'whoiswho' ), $wiw_child['count'] ) ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
		<?php if ( array() === $wiw_result['profiles'] ) : ?>
			<?php
			get_template_part(
				'template-parts/empty-state',
				null,
				array(
					'title'        => __( 'No public profiles in this industry yet', 'whoiswho' ),
					'text'         => __( 'Reviewed profiles appear here as soon as they are published.', 'whoiswho' ),
					'action_label' => __( 'All industries', 'whoiswho' ),
					'action_url'   => home_url( '/industries/' ),
				)
			);
			?>
		<?php else : ?>
			<div class="grid grid--4 results">
				<?php foreach ( $wiw_result['profiles'] as $wiw_profile ) : ?>
					<div><?php get_template_part( 'template-parts/profile-card', null, array( 'profile' => $wiw_profile ) ); ?></div>
				<?php endforeach; ?>
			</div>
			<?php if ( $wiw_result['pages'] > 1 ) : ?>
				<?php echo wp_kses( wiw_pager( (int) $wiw_result['page'], (int) $wiw_result['pages'], static fn ( int $p ): string => $p > 1 ? $wiw_base . 'page/' . $p . '/' : $wiw_base ), wiw_pager_kses() ); ?>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( array() !== $wiw_stories ) : ?>
			<h2 class="ev-h2"><?php esc_html_e( 'Stories', 'whoiswho' ); ?></h2>
			<ul class="ev-rel-list ind-stories">
				<?php foreach ( $wiw_stories as $wiw_story ) : ?>
					<li><a href="<?php echo esc_url( (string) ( $wiw_story['url'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $wiw_story['title'] ?? '' ) ); ?></a><span class="muted small"> <?php echo esc_html( (string) ( $wiw_story['type'] ?? '' ) ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>
<?php
get_footer();
