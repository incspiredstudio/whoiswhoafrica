<?php
/**
 * Directory list (REQ-001, REQ-007, REQ-218, REQ-208): the five lists share this template, told apart by the wiw_list query
 * variable. A header band with a slot for the search box (filled in M2), tabs for the five lists, the shared filter bar,
 * sixteen profile cards a page and pagination. Reads profiles only through ProfileList, which never returns a profile that is
 * pending review, hidden or removed. An empty result offers Clear filters.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\ProfileList;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_key    = wiw_directory_current_list();
$wiw_key    = '' !== $wiw_key ? $wiw_key : 'all';
$wiw_lists  = wiw_directory_lists();
$wiw_info   = $wiw_lists[ $wiw_key ];
$wiw_params = wiw_directory_params( $wiw_key );
$wiw_result = class_exists( ProfileList::class ) ? ( new ProfileList() )->query( $wiw_key, $wiw_params ) : array(
	'profiles' => array(),
	'total'    => 0,
	'page'     => 1,
	'pages'    => 1,
	'per_page' => 16,
	'applied'  => array(),
);
$wiw_on     = array() !== $wiw_result['applied'];
$wiw_base   = wiw_directory_url( $wiw_key );
$wiw_total  = (int) $wiw_result['total'];
$wiw_labels = wiw_directory_filter_labels();

$wiw_tabs = array();
foreach ( $wiw_lists as $wiw_tab_key => $wiw_tab ) {
	$wiw_tabs[] = array(
		'label'   => $wiw_tab['tab'],
		'url'     => wiw_directory_url( $wiw_tab_key ),
		'current' => $wiw_tab_key === $wiw_key,
	);
}
$wiw_controls = array();
foreach ( ProfileList::filters_for( $wiw_key ) as $wiw_filter ) {
	$wiw_controls[] = array(
		'name'    => $wiw_filter,
		'label'   => $wiw_labels[ $wiw_filter ][0],
		'blank'   => $wiw_labels[ $wiw_filter ][1],
		'options' => wiw_directory_filter_options( $wiw_filter ),
		'value'   => (string) $wiw_params[ $wiw_filter ],
	);
}

get_header();
wiw_page_band(
	array(
		'eyebrow' => __( 'Directory', 'whoiswho' ),
		'title'   => $wiw_info['title'],
		'lede'    => $wiw_info['lede'],
	)
);
?>
<section class="section section--tight dir-list" data-list="<?php echo esc_attr( $wiw_key ); ?>">
	<div class="wrap">
		<?php
		// Slot for the search box (REQ-007, live in M2).
		do_action( 'wiw_directory_search', $wiw_key, $wiw_params );
		get_template_part(
			'template-parts/text-tabs',
			null,
			array(
				'label' => __( 'Directory lists', 'whoiswho' ),
				'items' => $wiw_tabs,
			)
		);
		get_template_part(
			'template-parts/filter-bar',
			null,
			array(
				'action'   => $wiw_base,
				'label'    => __( 'Filter profiles', 'whoiswho' ),
				'controls' => $wiw_controls,
				'sort'     => array(
					'name'    => 'sort',
					'value'   => (string) $wiw_params['sort'],
					'options' => array(
						''                        => __( 'Name A to Z', 'whoiswho' ),
						ProfileList::SORT_UPDATED => __( 'Recently updated', 'whoiswho' ),
					),
				),
				/* translators: %d: number of profiles. */
				'count'    => sprintf( _n( '%d profile', '%d profiles', $wiw_total, 'whoiswho' ), $wiw_total ),
				'clear'    => $wiw_on,
			)
		);
		?>
		<?php if ( array() === $wiw_result['profiles'] ) : ?>
			<?php
			get_template_part(
				'template-parts/empty-state',
				null,
				array(
					'title'        => $wiw_on ? __( 'No profiles match', 'whoiswho' ) : __( 'No profiles here yet', 'whoiswho' ),
					'text'         => $wiw_on ? __( 'Try other filters, or clear them to see the whole list.', 'whoiswho' ) : __( 'Profiles appear here once they have been reviewed.', 'whoiswho' ),
					'action_label' => $wiw_on ? __( 'Clear filters', 'whoiswho' ) : '',
					'action_url'   => $wiw_on ? $wiw_base : '',
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
						<a class="btn alt" rel="prev" href="<?php echo esc_url( wiw_directory_url( $wiw_key, array_merge( $wiw_params, array( 'page' => $wiw_result['page'] - 1 ) ) ) ); ?>"><?php esc_html_e( 'Previous', 'whoiswho' ); ?></a>
					<?php endif; ?>
					<span class="pager-pos" aria-current="page">
						<?php
						/* translators: 1: current page, 2: number of pages. */
						echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'whoiswho' ), $wiw_result['page'], $wiw_result['pages'] ) );
						?>
					</span>
					<?php if ( $wiw_result['page'] < $wiw_result['pages'] ) : ?>
						<a class="btn alt" rel="next" href="<?php echo esc_url( wiw_directory_url( $wiw_key, array_merge( $wiw_params, array( 'page' => $wiw_result['page'] + 1 ) ) ) ); ?>"><?php esc_html_e( 'Next', 'whoiswho' ); ?></a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
