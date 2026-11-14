<?php
/**
 * Industries (REQ-007, REQ-009): the index at /industries with a public count per industry, and the industry page at
 * /industries/{slug} with its reviewed profiles and, only when there are any, its stories. Counts and profiles come from
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
			<ul class="ind-grid">
				<?php foreach ( $wiw_rows as $wiw_row ) : ?>
					<li>
						<a class="ind-tile" href="<?php echo esc_url( home_url( '/industries/' . $wiw_row['slug'] . '/' ) ); ?>">
							<span class="ind-name"><?php echo esc_html( $wiw_row['name'] ); ?></span>
							<span class="ind-count">
								<?php
								/* translators: %d: number of profiles. */
								echo esc_html( sprintf( _n( '%d profile', '%d profiles', $wiw_row['count'], 'whoiswho' ), $wiw_row['count'] ) );
								?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
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
			/* translators: %d: number of profiles. */
			'lede'    => sprintf( _n( '%d reviewed profile in this industry.', '%d reviewed profiles in this industry.', (int) $wiw_result['total'], 'whoiswho' ), (int) $wiw_result['total'] ),
			'wide'    => true,
		)
	);
	?>
<section class="section section--tight ind-page">
	<div class="wrap">
		<p class="ind-back"><a href="<?php echo esc_url( home_url( '/industries/' ) ); ?>"><?php esc_html_e( 'All industries', 'whoiswho' ); ?></a></p>
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
				<nav class="pager" aria-label="<?php esc_attr_e( 'Pages', 'whoiswho' ); ?>">
					<?php if ( $wiw_result['page'] > 1 ) : ?>
						<a class="btn alt" rel="prev" href="<?php echo esc_url( $wiw_result['page'] > 2 ? $wiw_base . 'page/' . ( $wiw_result['page'] - 1 ) . '/' : $wiw_base ); ?>"><?php esc_html_e( 'Previous', 'whoiswho' ); ?></a>
					<?php endif; ?>
					<span class="pager-pos">
						<?php
						/* translators: 1: current page, 2: number of pages. */
						echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'whoiswho' ), $wiw_result['page'], $wiw_result['pages'] ) );
						?>
					</span>
					<?php if ( $wiw_result['page'] < $wiw_result['pages'] ) : ?>
						<a class="btn alt" rel="next" href="<?php echo esc_url( $wiw_base . 'page/' . ( $wiw_result['page'] + 1 ) . '/' ); ?>"><?php esc_html_e( 'Next', 'whoiswho' ); ?></a>
					<?php endif; ?>
				</nav>
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
