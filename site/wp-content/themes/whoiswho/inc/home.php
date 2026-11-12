<?php
/**
 * Home page view model for the front page (REQ-001, REQ-002, REQ-052, REQ-190, REQ-259). The templates read this one array and
 * nothing else: the brand name and tagline come from the child theme through wiw_brand(), the Nominate button follows the
 * Nominations switch through wiw_nominations_are_open(), and profiles and stories are read by the section parts only through
 * the PublicProfile and PublicStory read models. The plugin's HomeData view model (M2-HOM-07) can replace the hero and door
 * arrays through the wiw_home_model filter without a template change.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! function_exists( 'wiw_home_title_lines' ) ) {
	/**
	 * The hero title as two lines: the brand's long name in sentence case, split after the third word ("Who is who" / "in Africa.").
	 *
	 * @param string $long_name Long brand name.
	 * @return array{0: string, 1: string}
	 */
	function wiw_home_title_lines( string $long_name ): array {
		$words = preg_split( '/\s+/', trim( $long_name ) );
		$words = is_array( $words ) ? array_values( array_filter( $words, static fn ( string $w ): bool => '' !== $w ) ) : array();
		if ( count( $words ) < 2 ) {
			return array( implode( ' ', $words ), '' );
		}
		$lower    = array_map(
			static fn ( string $w ): string => ctype_upper( $w ) && strlen( $w ) > 1 ? $w : mb_strtolower( $w ),
			$words
		);
		$lower[0] = mb_strtoupper( mb_substr( $lower[0], 0, 1 ) ) . mb_substr( $lower[0], 1 );
		// The last word is a place or a name and keeps its capital.
		$last           = count( $words ) - 1;
		$lower[ $last ] = $words[ $last ];
		$cut            = min( 3, $last );
		return array( implode( ' ', array_slice( $lower, 0, $cut ) ), implode( ' ', array_slice( $lower, $cut ) ) . '.' );
	}
}//end if

if ( ! function_exists( 'wiw_home_hero_videos' ) ) {
	/**
	 * The four hero videos with their still posters. Files live in the child theme's assets/home folder (brand media). A missing
	 * video is skipped; a poster alone still gives a complete, static hero.
	 *
	 * @return array<int, array{video: string, poster: string}>
	 */
	function wiw_home_hero_videos(): array {
		$dir = trailingslashit( get_stylesheet_directory() ) . 'assets/home/';
		$url = trailingslashit( get_stylesheet_directory_uri() ) . 'assets/home/';
		$out = array();
		for ( $i = 1; $i <= 4; $i++ ) {
			$poster = is_readable( $dir . 'hero-' . $i . '.jpg' ) ? $url . 'hero-' . $i . '.jpg' : '';
			$video  = is_readable( $dir . 'hero-' . $i . '.mp4' ) ? $url . 'hero-' . $i . '.mp4' : '';
			if ( '' !== $poster || '' !== $video ) {
				$out[] = array(
					'video'  => $video,
					'poster' => $poster,
				);
			}
		}
		return $out;
	}
}//end if

if ( ! function_exists( 'wiw_home_model' ) ) {
	/**
	 * Everything the front page needs that is not a profile, story or event.
	 *
	 * @return array<string, mixed>
	 */
	function wiw_home_model(): array {
		$nominating = wiw_nominations_are_open();
		$name       = wiw_site_name();
		$long       = function_exists( 'wiw_brand' ) ? wiw_brand( 'long_name' ) : $name;
		$tagline    = function_exists( 'wiw_brand' ) ? wiw_brand( 'tagline' ) : '';
		$model      = array(
			'name'       => $name,
			'tagline'    => $tagline,
			'title'      => wiw_home_title_lines( '' !== $long ? $long : $name ),
			'lede'       => __( 'Documenting the influence, achievements and legacy of the people and institutions shaping Africa.', 'whoiswho' ),
			'nominating' => $nominating,
			'ctas'       => array(
				'primary'   => $nominating
					? array(
						'label' => __( 'Nominate someone', 'whoiswho' ),
						'url'   => home_url( '/nominate/' ),
					)
					: array(
						'label' => __( 'Get listed', 'whoiswho' ),
						'url'   => home_url( '/get-listed/' ),
					),
				'secondary' => array(
					'label' => __( 'Browse the directory', 'whoiswho' ),
					'url'   => home_url( '/people/' ),
				),
			),
			'videos'     => wiw_home_hero_videos(),
		);
		/**
		 * Filters the home model (the plugin's HomeData view model plugs in here).
		 *
		 * @param array<string, mixed> $model Model.
		 */
		return apply_filters( 'wiw_home_model', $model );
	}
}//end if
