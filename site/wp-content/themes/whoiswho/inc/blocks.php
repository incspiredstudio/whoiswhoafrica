<?php
/**
 * Dynamic blocks (REQ-260, REQ-052, REQ-259): blocks that show data the system knows, so an editor places them but cannot type
 * over them. Figures (calculated counts), the Awards stage button (follows the stage read model), the Awards categories (read from
 * the Award categories screen) and the edition label (from the brand configuration). Each renders on the server and ignores any
 * attribute or inner content an editor could have saved. Judging criteria stays page copy, not a block.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Awards\AwardsStage;
use WhoIsWho\Core\Awards\CategoryService;
use WhoIsWho\Core\Content\PublicStory;
use WhoIsWho\Core\Directory\ProfileList;
use WhoIsWho\Core\Directory\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wiw_figures_counts' ) ) {
	/**
	 * The four figures: public profiles, industries, countries with at least one public profile, and published stories. Read through
	 * the ProfileList and PublicStory read models and kept for ten minutes, so a page view does not repeat the count queries.
	 *
	 * @return array{profiles: int, industries: int, countries: int, stories: int}
	 */
	function wiw_figures_counts(): array {
		$cached = get_transient( 'wiw_figures_counts' );
		if ( is_array( $cached ) && isset( $cached['profiles'], $cached['industries'], $cached['countries'], $cached['stories'] ) ) {
			return array(
				'profiles'   => (int) $cached['profiles'],
				'industries' => (int) $cached['industries'],
				'countries'  => (int) $cached['countries'],
				'stories'    => (int) $cached['stories'],
			);
		}
		$list      = new ProfileList();
		$all       = $list->query( 'all' );
		$countries = 0;
		$terms     = get_terms(
			array(
				'taxonomy'   => Taxonomies::PLACE,
				'hide_empty' => false,
				'meta_key'   => Taxonomies::META_KIND, // phpcs:ignore WordPress.DB.SlowDBQuery -- a few dozen place terms.
				'meta_value' => Taxonomies::KIND_COUNTRY, // phpcs:ignore WordPress.DB.SlowDBQuery -- a few dozen place terms.
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( $list->query( 'all', array( 'country' => (string) $term->slug ) )['total'] > 0 ) {
				++$countries;
			}
		}
		$counts = array(
			'profiles'   => (int) $all['total'],
			'industries' => count( $list->industry_counts() ),
			'countries'  => $countries,
			'stories'    => (int) PublicStory::list( array( 'per_page' => 1 ) )['total'],
		);
		set_transient( 'wiw_figures_counts', $counts, 10 * MINUTE_IN_SECONDS );
		return $counts;
	}
}//end if

if ( ! function_exists( 'wiw_render_figures_block' ) ) {
	/**
	 * Figures block markup.
	 */
	function wiw_render_figures_block(): string {
		$counts = wiw_figures_counts();
		$rows   = array(
			__( 'Profiles', 'whoiswho' )   => $counts['profiles'],
			__( 'Industries', 'whoiswho' ) => $counts['industries'],
			__( 'Countries', 'whoiswho' )  => $counts['countries'],
			__( 'Stories', 'whoiswho' )    => $counts['stories'],
		);
		$html   = '<dl class="ab-figs wiw-figures" data-reveal>';
		foreach ( $rows as $label => $value ) {
			$html .= '<div><dt>' . esc_html( (string) $label ) . '</dt><dd>' . esc_html( number_format_i18n( $value ) ) . '</dd></div>';
		}
		return $html . '</dl>';
	}
}

if ( ! function_exists( 'wiw_render_stage_button_block' ) ) {
	/**
	 * Awards stage button block markup: one button whose label and link follow the stage.
	 */
	function wiw_render_stage_button_block(): string {
		$button = AwardsStage::for_site()->button();
		return '<p class="wiw-stage-button"><a class="btn" href="' . esc_url( $button['url'] ) . '">' . esc_html( $button['label'] ) . '</a></p>';
	}
}

if ( ! function_exists( 'wiw_render_award_categories_block' ) ) {
	/**
	 * Awards categories block markup: the groups and their categories, each linking to its page. A group with no category is left out.
	 */
	function wiw_render_award_categories_block(): string {
		$html = '<div class="wiw-award-categories">';
		foreach ( CategoryService::for_site()->grouped() as $key => $group ) {
			$html .= '<section class="award-group award-group--' . esc_attr( (string) $key ) . '"><h3>' . esc_html( (string) $group['label'] ) . '</h3>';
			if ( array() === $group['categories'] ) {
				$html .= '<p class="small muted">' . esc_html__( 'Categories will be listed here.', 'whoiswho' ) . '</p>';
			} else {
				$html .= '<ul class="link-stack">';
				foreach ( $group['categories'] as $category ) {
					$html .= '<li><a href="' . esc_url( home_url( '/awards/category/' . (int) $category['id'] . '/' ) ) . '">' . esc_html( (string) $category['title'] ) . '</a></li>';
				}
				$html .= '</ul>';
			}
			$html .= '</section>';
		}
		return $html . '</div>';
	}
}//end if

if ( ! function_exists( 'wiw_render_edition_block' ) ) {
	/**
	 * Edition label block markup, from the brand configuration.
	 */
	function wiw_render_edition_block(): string {
		return '<span class="wiw-edition">' . esc_html( wiw_brand( 'edition' ) ) . '</span>';
	}
}

add_action(
	'init',
	static function (): void {
		$blocks = array(
			'wiw/figures'             => array( 'Figures', 'wiw_render_figures_block' ),
			'wiw/awards-stage-button' => array( 'Awards stage button', 'wiw_render_stage_button_block' ),
			'wiw/awards-categories'   => array( 'Awards categories', 'wiw_render_award_categories_block' ),
			'wiw/edition'             => array( 'Edition label', 'wiw_render_edition_block' ),
		);
		foreach ( $blocks as $name => list( $title, $callback ) ) {
			register_block_type(
				$name,
				array(
					'api_version'     => 3,
					'title'           => $title,
					'category'        => 'widgets',
					'description'     => __( 'Shows data the system knows. It is calculated and cannot be typed over.', 'whoiswho' ),
					'supports'        => array(
						'html'     => false,
						'multiple' => true,
					),
					'editor_script'   => 'wiw-dynamic-blocks',
					'render_callback' => static fn (): string => (string) $callback(),
				)
			);
		}
	},
	20
);

add_action(
	'init',
	static function (): void {
		wp_register_script(
			'wiw-dynamic-blocks',
			get_template_directory_uri() . '/blocks/dynamic/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-server-side-render' ),
			(string) wp_get_theme( 'whoiswho' )->get( 'Version' ),
			true
		);
	},
	19
);
