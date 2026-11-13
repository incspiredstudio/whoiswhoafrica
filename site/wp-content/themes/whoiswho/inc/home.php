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

if ( ! function_exists( 'wiw_is_home_request' ) ) {
	/**
	 * Whether this request is the home page itself. Routes without a post of their own (the form pages, the single-use link page,
	 * lists) also look like the front page to is_front_page(), so the request must carry no query variable of its own except paging.
	 */
	function wiw_is_home_request(): bool {
		if ( ! is_front_page() ) {
			return false;
		}
		global $wp;
		$vars = isset( $wp->query_vars ) && is_array( $wp->query_vars ) ? array_filter( array_diff_key( $wp->query_vars, array( 'paged' => 1 ) ), static fn ( $v ): bool => '' !== $v ) : array();
		return array() === $vars;
	}
}

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
	 * The four hero videos with their still posters (REQ-001, REQ-041). Files live in the child theme's assets/home folder (brand
	 * media; see the README there). Each clip has an MP4 and, when smaller, a WebM; each poster has 360 and 540 px widths in AVIF, WebP
	 * and JPEG (the 540 JPEG is the fallback). A missing video is skipped; a poster alone still gives a complete, static hero. A slot an
	 * editor filled on the Home page settings screen (issue 799) replaces the bundled clip for that slot only; the plugin hands it over
	 * through wiw_home_hero_overrides() already checked, so an invalid or removed choice is simply absent and the bundled clip shows.
	 *
	 * @return array<int, array{video: string, webm: string, poster: string, sets: array<string, string>, width: int, height: int}>
	 */
	function wiw_home_hero_videos(): array {
		$dir    = trailingslashit( get_stylesheet_directory() ) . 'assets/home/';
		$url    = trailingslashit( get_stylesheet_directory_uri() ) . 'assets/home/';
		$out    = array();
		$custom = function_exists( 'wiw_home_hero_overrides' ) ? wiw_home_hero_overrides() : array();
		for ( $i = 1; $i <= 4; $i++ ) {
			if ( isset( $custom[ $i ] ) ) {
				$out[] = array(
					'video'  => $custom[ $i ]['video'],
					'webm'   => $custom[ $i ]['webm'],
					'poster' => $custom[ $i ]['poster'],
					'sets'   => $custom[ $i ]['sets'],
					'width'  => $custom[ $i ]['width'],
					'height' => $custom[ $i ]['height'],
				);
				continue;
			}
			$base   = 'hero-' . $i;
			$poster = is_readable( $dir . $base . '-540.jpg' ) ? $url . $base . '-540.jpg' : '';
			$video  = is_readable( $dir . $base . '.mp4' ) ? $url . $base . '.mp4' : '';
			$webm   = is_readable( $dir . $base . '.webm' ) ? $url . $base . '.webm' : '';
			$sets   = array();
			foreach ( array( 'avif', 'webp', 'jpg' ) as $ext ) {
				$parts = array();
				foreach ( array( 360, 540 ) as $w ) {
					if ( is_readable( $dir . $base . '-' . $w . '.' . $ext ) ) {
						$parts[] = $url . $base . '-' . $w . '.' . $ext . ' ' . $w . 'w';
					}
				}
				if ( array() !== $parts ) {
					$sets[ $ext ] = implode( ', ', $parts );
				}
			}
			if ( '' !== $poster || '' !== $video ) {
				$out[] = array(
					'video'  => $video,
					'webm'   => $webm,
					'poster' => $poster,
					'sets'   => $sets,
					'width'  => 540,
					'height' => 960,
				);
			}
		}//end for
		return $out;
	}
}//end if

if ( ! function_exists( 'wiw_home_acts' ) ) {
	/**
	 * The three acts: the words of the brand tagline, each with a mark, a line and a link (people, awards, stories).
	 *
	 * @param string $tagline Brand tagline, for example "Influence. Achievement. Legacy.".
	 * @return array<int, array{tone: string, mark: string, word: string, text: string, link: string, url: string}>
	 */
	function wiw_home_acts( string $tagline ): array {
		$words = array_values( array_filter( array_map( 'trim', explode( '.', $tagline ) ), static fn ( string $w ): bool => '' !== $w ) );
		$words = 3 === count( $words ) ? $words : array( 'Influence', 'Achievement', 'Legacy' );
		return array(
			array(
				'tone' => 'blue',
				'mark' => 'star',
				'word' => $words[0] . '.',
				'text' => __( 'The people and organisations making a difference and shaping the world around them.', 'whoiswho' ),
				'link' => __( 'Discover the people', 'whoiswho' ),
				'url'  => home_url( '/people/' ),
			),
			array(
				'tone' => 'orange',
				'mark' => 'comb',
				'word' => $words[1] . '.',
				'text' => __( 'The milestones, contributions and innovations that deserve to be on the record.', 'whoiswho' ),
				'link' => __( 'See how recognition works', 'whoiswho' ),
				'url'  => home_url( '/awards/' ),
			),
			array(
				'tone' => 'purple',
				'mark' => 'spiral',
				'word' => $words[2] . '.',
				'text' => __( 'The lasting impact people and institutions leave behind, kept accurate over time.', 'whoiswho' ),
				'link' => __( 'Read the stories', 'whoiswho' ),
				'url'  => home_url( '/stories/' ),
			),
		);
	}
}//end if

if ( ! function_exists( 'wiw_home_doors' ) ) {
	/**
	 * The four doors to the directory lists.
	 *
	 * @return array<int, array{tone: string, mark: string, title: string, text: string, url: string}>
	 */
	function wiw_home_doors(): array {
		return array(
			array(
				'tone'  => 'blue',
				'mark'  => 'star',
				'title' => __( 'People', 'whoiswho' ),
				'text'  => __( 'Leaders and professionals on the public record', 'whoiswho' ),
				'url'   => home_url( '/people/' ),
			),
			array(
				'tone'  => 'orange',
				'mark'  => 'comb',
				'title' => __( 'Entrepreneurs', 'whoiswho' ),
				'text'  => __( 'Founders and the ventures they built', 'whoiswho' ),
				'url'   => home_url( '/entrepreneurs/' ),
			),
			array(
				'tone'  => 'green',
				'mark'  => 'link',
				'title' => __( 'Businesses', 'whoiswho' ),
				'text'  => __( 'Companies, from startups to listed groups', 'whoiswho' ),
				'url'   => home_url( '/businesses/' ),
			),
			array(
				'tone'  => 'purple',
				'mark'  => 'spiral',
				'title' => __( 'Institutions', 'whoiswho' ),
				'text'  => __( 'Universities, associations and public bodies', 'whoiswho' ),
				'url'   => home_url( '/institutions/' ),
			),
		);
	}
}//end if

if ( ! function_exists( 'wiw_home_record' ) ) {
	/**
	 * The profiles of the On the record block, picked by the curation engine (REQ-257) and re-read through PublicProfile, so only
	 * public, reviewed profiles come back. Empty when the plugin is not active.
	 *
	 * @return array<int, \WhoIsWho\Core\Directory\PublicProfile>
	 */
	function wiw_home_record(): array {
		if ( ! class_exists( '\WhoIsWho\Core\Content\Curation\ProfileCuration' ) ) {
			return array();
		}
		$curation = new \WhoIsWho\Core\Content\Curation\ProfileCuration(
			new \WhoIsWho\Core\Content\Curation\CurationSettings( \WhoIsWho\Core\Platform\Audit\AuditLogger::for_site() )
		);
		return $curation->items( \WhoIsWho\Core\Content\Curation\CurationSettings::ON_THE_RECORD );
	}
}

if ( ! function_exists( 'wiw_home_stories' ) ) {
	/**
	 * The six latest stories, read through PublicStory only. A story with a live placement for the Home surface leads as a labelled
	 * slot and the others keep their order (REQ-263). The curation engine for story blocks (M2-HOM-03) can replace this through the
	 * wiw_home_model filter; until then the newest stories are used.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function wiw_home_stories(): array {
		if ( ! class_exists( '\WhoIsWho\Core\Content\PublicStory' ) ) {
			return array();
		}
		$list  = \WhoIsWho\Core\Content\PublicStory::list(
			array(
				'per_page' => 6,
				'surface'  => 'home',
			)
		);
		$items = $list['items'];
		$slot  = $list['slot'];
		if ( is_array( $slot ) && ! empty( $slot['id'] ) ) {
			$items = array_merge( array( $slot ), array_values( array_filter( $items, static fn ( array $s ): bool => (int) ( $s['id'] ?? 0 ) !== (int) $slot['id'] ) ) );
		}
		return array_slice( $items, 0, 6 );
	}
}//end if

if ( ! function_exists( 'wiw_home_events' ) ) {
	/**
	 * The next three upcoming events, soonest first (REQ-264).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function wiw_home_events(): array {
		return class_exists( '\WhoIsWho\Core\Content\PublicEvent' ) ? \WhoIsWho\Core\Content\PublicEvent::next_upcoming( 3 ) : array();
	}
}

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
			// Only the closing section's button reads this (the hero has none, issue 798).
			'ctas'       => array(
				'primary' => $nominating
					? array(
						'label' => __( 'Nominate someone', 'whoiswho' ),
						'url'   => home_url( '/nominate/' ),
					)
					: array(
						'label' => __( 'Get listed', 'whoiswho' ),
						'url'   => home_url( '/get-listed/' ),
					),
			),
			'videos'     => wiw_home_hero_videos(),
			'acts'       => wiw_home_acts( $tagline ),
			'doors'      => wiw_home_doors(),
			'record'     => wiw_home_record(),
			'stories'    => wiw_home_stories(),
			'events'     => wiw_home_events(),
		);
		/**
		 * Filters the home model (the plugin's HomeData view model plugs in here).
		 *
		 * @param array<string, mixed> $model Model.
		 */
		return apply_filters( 'wiw_home_model', $model );
	}
}//end if
