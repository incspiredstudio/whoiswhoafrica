<?php
/**
 * Stories list at /stories (REQ-029, REQ-249, REQ-026, REQ-263, REQ-257): laid out like a magazine, no hero. A masthead with the
 * page title, plain type tabs and the quiet filter bar (Industry, count, Clear); a mosaic of one large and two small tiles; a
 * three-column grid of the next six; a dark sign-up block (filled by the newsletter issue through wiw_stories_signup); then the
 * rest. Reads stories only through PublicStory, so a draft, scheduled or removed story is never named. A story with a live
 * placement for Stories or Both sits in a labelled slot under the mosaic; it never moves the other stories. No Events filter or strip.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Content\PublicStory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_params = wiw_stories_params();
$wiw_result = class_exists( PublicStory::class ) ? PublicStory::list(
	array(
		'type'     => $wiw_params['type'],
		'industry' => $wiw_params['industry'],
		'page'     => $wiw_params['page'],
		'surface'  => 'stories',
	)
) : array(
	'items' => array(),
	'total' => 0,
	'pages' => 0,
	'page'  => 1,
	'slot'  => null,
);
$wiw_total  = (int) $wiw_result['total'];
$wiw_page   = (int) $wiw_result['page'];
$wiw_on     = '' !== $wiw_params['type'] || '' !== $wiw_params['industry'];
$wiw_base   = wiw_stories_url();
$wiw_items  = (array) $wiw_result['items'];
$wiw_slot   = ! $wiw_on && 1 === $wiw_page && is_array( $wiw_result['slot'] ) ? $wiw_result['slot'] : null;
if ( null !== $wiw_slot ) {
	// The slot story is shown once, in its labelled place.
	$wiw_items = array_values( array_filter( $wiw_items, static fn ( array $s ): bool => (int) $s['id'] !== (int) $wiw_slot['id'] ) );
}
$wiw_first  = 1 === $wiw_page;
$wiw_mosaic = $wiw_first ? array_slice( $wiw_items, 0, 3 ) : array();
$wiw_grid   = $wiw_first ? array_slice( $wiw_items, 3, 6 ) : $wiw_items;
$wiw_rest   = $wiw_first ? array_slice( $wiw_items, 9 ) : array();
$wiw_signup = has_action( 'wiw_stories_signup' );

$wiw_tabs = array(
	array(
		'label'   => __( 'All', 'whoiswho' ),
		'url'     => wiw_stories_url( array( 'industry' => $wiw_params['industry'] ) ),
		'current' => '' === $wiw_params['type'],
	),
);
foreach ( wiw_story_types() as $wiw_slug => $wiw_label ) {
	$wiw_tabs[] = array(
		'label'   => $wiw_label,
		'url'     => wiw_stories_url(
			array(
				'type'     => $wiw_slug,
				'industry' => $wiw_params['industry'],
			)
		),
		'current' => $wiw_slug === $wiw_params['type'],
	);
}

get_header();
?>
<section class="stories-mast">
	<div class="wrap">
		<h1 class="stories-title"><?php esc_html_e( 'Stories', 'whoiswho' ); ?></h1>
		<?php
		get_template_part(
			'template-parts/text-tabs',
			null,
			array(
				'label' => __( 'Story types', 'whoiswho' ),
				'items' => $wiw_tabs,
			)
		);
		get_template_part(
			'template-parts/filter-bar',
			null,
			array(
				'action'   => $wiw_base,
				'label'    => __( 'Filter stories', 'whoiswho' ),
				'hidden'   => '' !== $wiw_params['type'] ? array( 'type' => $wiw_params['type'] ) : array(),
				'controls' => array(
					array(
						'name'    => 'industry',
						'label'   => __( 'Industry', 'whoiswho' ),
						'blank'   => __( 'All industries', 'whoiswho' ),
						'options' => wiw_story_industry_options(),
						'value'   => $wiw_params['industry'],
					),
				),
				/* translators: %d: number of stories. */
				'count'    => sprintf( _n( '%d story', '%d stories', $wiw_total, 'whoiswho' ), $wiw_total ),
				'clear'    => $wiw_on,
			)
		);
		?>
	</div>
</section>
<?php if ( array() === $wiw_items && null === $wiw_slot ) : ?>
	<section class="section section--tight stories-body">
		<div class="wrap">
			<?php
			get_template_part(
				'template-parts/empty-state',
				null,
				array(
					'title'        => $wiw_on ? __( 'No stories match', 'whoiswho' ) : __( 'No stories yet', 'whoiswho' ),
					'text'         => $wiw_on ? __( 'Try another type or industry, or clear the filters to see every story.', 'whoiswho' ) : __( 'Stories appear here as they are published.', 'whoiswho' ),
					'action_label' => $wiw_on ? __( 'Clear filters', 'whoiswho' ) : '',
					'action_url'   => $wiw_on ? $wiw_base : '',
				)
			);
			?>
		</div>
	</section>
<?php else : ?>
	<section class="stories-body">
		<div class="wrap">
			<?php if ( array() !== $wiw_mosaic ) : ?>
				<div class="stories-mosaic">
					<?php foreach ( $wiw_mosaic as $wiw_i => $wiw_story ) : ?>
						<?php
						get_template_part(
							'template-parts/story-card',
							null,
							array(
								'story'   => $wiw_story,
								'variant' => 0 === $wiw_i ? 'lead' : 'tile',
								'eager'   => 0 === $wiw_i,
							)
						);
						?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( null !== $wiw_slot ) : ?>
				<div class="stories-slot">
					<?php
					get_template_part(
						'template-parts/story-card',
						null,
						array(
							'story'   => $wiw_slot,
							'variant' => 'lead',
						)
					);
					?>
				</div>
			<?php endif; ?>
			<?php if ( array() !== $wiw_grid ) : ?>
				<div class="stories-grid">
					<?php foreach ( $wiw_grid as $wiw_story ) : ?>
						<?php get_template_part( 'template-parts/story-card', null, array( 'story' => $wiw_story ) ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( $wiw_first && $wiw_signup ) : ?>
			<div class="stories-signup section--dark">
				<div class="wrap stories-signup-in">
					<h2><?php esc_html_e( 'Get new stories in your inbox.', 'whoiswho' ); ?></h2>
					<?php do_action( 'wiw_stories_signup' ); ?>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( array() !== $wiw_rest ) : ?>
			<div class="wrap">
				<div class="stories-grid">
					<?php foreach ( $wiw_rest as $wiw_story ) : ?>
						<?php get_template_part( 'template-parts/story-card', null, array( 'story' => $wiw_story ) ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( (int) $wiw_result['pages'] > 1 ) : ?>
			<div class="wrap">
				<nav class="pager" aria-label="<?php esc_attr_e( 'Pages', 'whoiswho' ); ?>">
					<?php if ( $wiw_page > 1 ) : ?>
						<a class="btn alt" rel="prev" href="<?php echo esc_url( wiw_stories_url( array_merge( $wiw_params, array( 'page' => $wiw_page - 1 ) ) ) ); ?>"><?php esc_html_e( 'Previous', 'whoiswho' ); ?></a>
					<?php endif; ?>
					<span class="pager-pos" aria-current="page">
						<?php
						/* translators: 1: current page, 2: number of pages. */
						echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'whoiswho' ), $wiw_page, (int) $wiw_result['pages'] ) );
						?>
					</span>
					<?php if ( $wiw_page < (int) $wiw_result['pages'] ) : ?>
						<a class="btn alt" rel="next" href="<?php echo esc_url( wiw_stories_url( array_merge( $wiw_params, array( 'page' => $wiw_page + 1 ) ) ) ); ?>"><?php esc_html_e( 'Next', 'whoiswho' ); ?></a>
					<?php endif; ?>
				</nav>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>
<?php
get_footer();
