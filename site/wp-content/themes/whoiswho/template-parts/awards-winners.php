<?php
/**
 * The winners page body (REQ-246, REQ-033, REQ-034): a quiet filter bar (search, year, group, category, country, winner is, sort,
 * count and a Clear link), twelve winners per page grouped by year, and a link to the profile where the profile is public. Every
 * filter lives in the address. Winners come only through the plugin's winners query over PublicNominee, so no nominator, evidence
 * or contact exists here to print. A one-time confetti (assets/js/winners.js) runs on load when winners are on the page and the
 * visitor has not asked for reduced motion; the script reads its colours from the theme tokens.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_data   = function_exists( 'wiw_awards_winners' ) ? wiw_awards_winners() : array();
$wiw_params = isset( $wiw_data['params'] ) && is_array( $wiw_data['params'] ) ? $wiw_data['params'] : array();
$wiw_total  = (int) ( $wiw_data['total'] ?? 0 );
$wiw_pages  = (int) ( $wiw_data['pages'] ?? 1 );
$wiw_page   = (int) ( $wiw_data['page'] ?? 1 );
$wiw_years  = isset( $wiw_data['years'] ) && is_array( $wiw_data['years'] ) ? $wiw_data['years'] : array();
$wiw_sort   = (string) ( $wiw_params['sort'] ?? 'newest' );
$wiw_on     = false;
foreach ( array( 'year', 'group', 'category', 'type', 'country', 'q' ) as $wiw_key ) {
	if ( ! empty( $wiw_params[ $wiw_key ] ) ) {
		$wiw_on = true;
	}
}
$wiw_action = home_url( '/awards/winners/' );

$wiw_year_options = array();
foreach ( $wiw_years as $wiw_year => $wiw_count ) {
	$wiw_year_options[ (string) $wiw_year ] = sprintf( '%d (%d)', (int) $wiw_year, (int) $wiw_count );
}
$wiw_group_options = isset( $wiw_data['groups'] ) && is_array( $wiw_data['groups'] ) ? $wiw_data['groups'] : array();
$wiw_cat_options   = isset( $wiw_data['categories'] ) && is_array( $wiw_data['categories'] ) ? $wiw_data['categories'] : array();
$wiw_country_opts  = isset( $wiw_data['countries'] ) && is_array( $wiw_data['countries'] ) ? $wiw_data['countries'] : array();

?>
<header class="np-hero win-hero">
	<div class="wrap">
		<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'whoiswho' ); ?>">
			<a href="<?php echo esc_url( home_url( '/awards/' ) ); ?>"><?php esc_html_e( 'Awards', 'whoiswho' ); ?></a><span aria-hidden="true">/</span><span><?php esc_html_e( 'Winners', 'whoiswho' ); ?></span>
		</nav>
		<p class="np-kicker"><?php echo esc_html( wiw_brand( 'edition' ) ); ?></p>
		<h1 class="np-title"><span><?php esc_html_e( 'The', 'whoiswho' ); ?></span> <span><?php esc_html_e( 'winners', 'whoiswho' ); ?></span></h1>
		<div class="np-intro">
			<p><?php esc_html_e( 'Chosen by independent judges against the published criteria. Filter by year, group, category or country.', 'whoiswho' ); ?></p>
		</div>
	</div>
</header>
<section class="section win-body" data-confetti="<?php echo $wiw_total > 0 ? 'ready' : 'none'; ?>">
	<div class="wrap">
		<?php
		get_template_part(
			'template-parts/filter-bar',
			null,
			array(
				'action'   => $wiw_action,
				'label'    => __( 'Filter winners', 'whoiswho' ),
				'search'   => array(
					'name'        => 'q',
					'value'       => (string) ( $wiw_params['q'] ?? '' ),
					'placeholder' => __( 'Search winners', 'whoiswho' ),
					'label'       => __( 'Search winners', 'whoiswho' ),
				),
				'controls' => array(
					array(
						'name'    => 'year',
						'label'   => __( 'Year', 'whoiswho' ),
						'blank'   => __( 'All years', 'whoiswho' ),
						'value'   => ! empty( $wiw_params['year'] ) ? (string) $wiw_params['year'] : '',
						'options' => $wiw_year_options,
					),
					array(
						'name'    => 'group',
						'label'   => __( 'Group', 'whoiswho' ),
						'blank'   => __( 'All groups', 'whoiswho' ),
						'value'   => (string) ( $wiw_params['group'] ?? '' ),
						'options' => $wiw_group_options,
					),
					array(
						'name'    => 'category',
						'label'   => __( 'Category', 'whoiswho' ),
						'blank'   => __( 'All categories', 'whoiswho' ),
						'value'   => ! empty( $wiw_params['category'] ) ? (string) $wiw_params['category'] : '',
						'options' => $wiw_cat_options,
					),
					array(
						'name'    => 'type',
						'label'   => __( 'Winner is', 'whoiswho' ),
						'blank'   => __( 'People and organisations', 'whoiswho' ),
						'value'   => (string) ( $wiw_params['type'] ?? '' ),
						'options' => array(
							'person'       => __( 'A person', 'whoiswho' ),
							'organisation' => __( 'An organisation', 'whoiswho' ),
						),
					),
					array(
						'name'    => 'country',
						'label'   => __( 'Country', 'whoiswho' ),
						'blank'   => __( 'All countries', 'whoiswho' ),
						'value'   => (string) ( $wiw_params['country'] ?? '' ),
						'options' => $wiw_country_opts,
					),
				),
				'sort'     => array(
					'name'    => 'sort',
					'value'   => $wiw_sort,
					'options' => array(
						'newest' => __( 'Newest first', 'whoiswho' ),
						'name'   => __( 'Name, A to Z', 'whoiswho' ),
					),
				),
				/* translators: %d: number of winners. */
				'count'    => sprintf( _n( '%d winner', '%d winners', $wiw_total, 'whoiswho' ), $wiw_total ),
				'clear'    => $wiw_on,
			)
		);

		/**
		 * Print the cards of a list of winners.
		 *
		 * @param array<int, \WhoIsWho\Core\Awards\PublicNominee> $winners Winners.
		 */
		$wiw_cards = static function ( array $winners ): void {
			echo '<div class="nom-grid">';
			foreach ( $winners as $winner ) {
				get_template_part(
					'template-parts/nominee-card',
					null,
					array(
						'nominee' => $winner,
						'won'     => true,
					)
				);
			}
			echo '</div>';
		};

		if ( 0 === $wiw_total ) {
			get_template_part(
				'template-parts/empty-state',
				null,
				array(
					'title'        => $wiw_on ? __( 'No winners match', 'whoiswho' ) : __( 'Winners are not published yet', 'whoiswho' ),
					'text'         => $wiw_on ? __( 'Try removing a filter or searching for a different word.', 'whoiswho' ) : __( 'They appear here once the team makes them live.', 'whoiswho' ),
					'action_label' => $wiw_on ? __( 'Clear filters', 'whoiswho' ) : __( 'Awards overview', 'whoiswho' ),
					'action_url'   => $wiw_on ? $wiw_action : home_url( '/awards/' ),
				)
			);
		} elseif ( 'newest' === $wiw_sort && ! empty( $wiw_data['by_year'] ) ) {
			foreach ( (array) $wiw_data['by_year'] as $wiw_year => $wiw_group ) {
				?>
				<section class="win-year" aria-labelledby="wy<?php echo esc_attr( (string) $wiw_year ); ?>">
					<h2 class="win-year-h" id="wy<?php echo esc_attr( (string) $wiw_year ); ?>"><span><?php echo esc_html( (string) $wiw_year ); ?></span></h2>
					<?php $wiw_cards( (array) $wiw_group ); ?>
				</section>
				<?php
			}
		} else {
			?>
			<section class="win-year"><?php $wiw_cards( (array) ( $wiw_data['items'] ?? array() ) ); ?></section>
			<?php
		}//end if

		if ( $wiw_pages > 1 ) :
			?>
			<nav class="pager" aria-label="<?php esc_attr_e( 'Pages', 'whoiswho' ); ?>">
				<?php if ( $wiw_page > 1 ) : ?>
					<a class="btn alt" rel="prev" href="<?php echo esc_url( wiw_awards_winners_url( array_merge( $wiw_params, array( 'page' => $wiw_page - 1 ) ) ) ); ?>"><?php esc_html_e( 'Previous', 'whoiswho' ); ?></a>
				<?php endif; ?>
				<span class="pager-pos" aria-current="page">
					<?php
					/* translators: 1: current page, 2: number of pages. */
					echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'whoiswho' ), $wiw_page, $wiw_pages ) );
					?>
				</span>
				<?php if ( $wiw_page < $wiw_pages ) : ?>
					<a class="btn alt" rel="next" href="<?php echo esc_url( wiw_awards_winners_url( array_merge( $wiw_params, array( 'page' => $wiw_page + 1 ) ) ) ); ?>"><?php esc_html_e( 'Next', 'whoiswho' ); ?></a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</div>
</section>
