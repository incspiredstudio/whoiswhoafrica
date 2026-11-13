<?php
/**
 * Awards public pages (REQ-033, REQ-198, REQ-064, REQ-200, REQ-266): the template choice for the nominees list, a category, a
 * nominee and the winners list, the overview and rules pages, and the group colours. The plugin decides whether a nominee or a
 * category is public (404, preview or serve); the theme only draws what the read models return. Nominees are read only through
 * PublicNominee, never through the nominations table, and no nominator is ever available to print.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Awards\AwardsRoutes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wiw_awards_view' ) ) {
	/**
	 * The Awards view the request asks for: nominees, winners, category or nominee. Empty on any other request or on a 404.
	 */
	function wiw_awards_view(): string {
		if ( ! class_exists( AwardsRoutes::class ) || is_404() ) {
			return '';
		}
		$view = get_query_var( AwardsRoutes::VIEW_VAR, '' );
		return is_string( $view ) && in_array( $view, array( 'nominees', 'winners', 'category', 'nominee' ), true ) ? $view : '';
	}
}

if ( ! function_exists( 'wiw_awards_page_kind' ) ) {
	/**
	 * Which editor page is the Awards overview or the rules: "overview" for /awards, "rules" for /awards/rules, else empty.
	 */
	function wiw_awards_page_kind(): string {
		if ( is_page( 'awards' ) ) {
			return 'overview';
		}
		$post = get_queried_object();
		if ( is_page( 'rules' ) && $post instanceof WP_Post && $post->post_parent > 0 && 'awards' === get_post_field( 'post_name', $post->post_parent ) ) {
			return 'rules';
		}
		return '';
	}
}

if ( ! function_exists( 'wiw_award_group_style' ) ) {
	/**
	 * The brand colour class and mark for an award group. Class names only: the colours live in the tokens file.
	 *
	 * @param string $group_key Group key from the Award categories screen.
	 * @return array{tone: string, mark: string}
	 */
	function wiw_award_group_style( string $group_key ): array {
		$map = array(
			'leadership-public-impact'     => array( 'blue', 'star' ),
			'entrepreneurship-business'    => array( 'orange', 'comb' ),
			'technology-innovation'        => array( 'purple', 'spiral' ),
			'professional-excellence'      => array( 'green', 'link' ),
			'industry-institutional-recog' => array( 'red', 'comb' ),
		);
		$row = $map[ $group_key ] ?? array( 'blue', 'star' );
		return array(
			'tone' => $row[0],
			'mark' => $row[1],
		);
	}
}//end if

if ( ! function_exists( 'wiw_award_group_text' ) ) {
	/**
	 * The one-line description of an award group.
	 *
	 * @param string $group_key Group key.
	 */
	function wiw_award_group_text( string $group_key ): string {
		$text = array(
			'leadership-public-impact'     => __( 'People whose leadership has changed how an organisation, a sector or a community works.', 'whoiswho' ),
			'entrepreneurship-business'    => __( 'Founders and companies that have built something durable.', 'whoiswho' ),
			'technology-innovation'        => __( 'New products and approaches, judged on what they changed rather than on how new they are.', 'whoiswho' ),
			'professional-excellence'      => __( 'Practitioners whose record stands out within their profession.', 'whoiswho' ),
			'industry-institutional-recog' => __( 'Institutions that serve their field or the public well, over time.', 'whoiswho' ),
		);
		return $text[ $group_key ] ?? '';
	}
}

add_filter(
	'template_include',
	static function ( string $template ): string {
		$name = '';
		if ( '' !== wiw_awards_view() ) {
			$name = 'templates/awards.php';
		} elseif ( '' !== wiw_awards_page_kind() ) {
			$name = 'templates/awards-page.php';
		}
		if ( '' === $name ) {
			return $template;
		}
		$found = locate_template( $name );
		return '' !== $found ? $found : $template;
	},
	12
);

add_filter(
	'pre_get_document_title',
	static function ( $title ) {
		$view = wiw_awards_view();
		if ( '' === $view ) {
			return $title;
		}
		$labels = array(
			'nominees' => __( 'Nominees', 'whoiswho' ),
			'winners'  => __( 'Winners', 'whoiswho' ),
			'category' => __( 'Award category', 'whoiswho' ),
			'nominee'  => __( 'Nominee', 'whoiswho' ),
		);
		return $labels[ $view ] . ' | ' . wiw_site_name();
	}
);
