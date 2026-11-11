<?php
/**
 * Helpers shared by the theme's component parts: icons, initials, and the poster colour and mark picked from a slug (REQ-216, REQ-155).
 * Colours themselves come from the child theme tokens through CSS classes; nothing here holds a brand value.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! function_exists( 'wiw_icon_paths' ) ) {
	/**
	 * Icon paths (16 by 16 line icons, from the prototype).
	 *
	 * @return array<string, string>
	 */
	function wiw_icon_paths(): array {
		return array(
			'search'  => '<circle cx="7" cy="7" r="5"/><path d="m11 11 4 4"/>',
			'arrow'   => '<path d="M2 8h11M9 4l4 4-4 4"/>',
			'chevron' => '<path d="m3 6 5 5 5-5"/>',
			'check'   => '<path d="m3 8.5 3.2 3L13 4.5"/>',
			'star'    => '<path d="m8 2 1.8 3.9 4.2.5-3.1 2.9.8 4.2L8 11.5l-3.7 2 .8-4.2L2 6.4l4.2-.5z"/>',
			'clock'   => '<circle cx="8" cy="8" r="6"/><path d="M8 4.5V8l2.5 1.5"/>',
			'tag'     => '<path d="M2 2.5h5.5L14 9l-5 5-6.5-6.5z"/><circle cx="5.5" cy="5.5" r=".8"/>',
			'pin'     => '<path d="M8 14.5s5-4.6 5-8.5a5 5 0 0 0-10 0c0 3.9 5 8.5 5 8.5z"/><circle cx="8" cy="6" r="1.7"/>',
			'grid'    => '<path d="M2 2h5v5H2zM9 2h5v5H9zM2 9h5v5H2zM9 9h5v5H9z"/>',
			'list'    => '<path d="M2 3.5h12M2 8h12M2 12.5h12"/>',
			'menu'    => '<path d="M2 4.5h12M2 8h12M2 11.5h12"/>',
			'close'   => '<path d="m3 3 10 10M13 3 3 13"/>',
			'info'    => '<circle cx="8" cy="8" r="6"/><path d="M8 7.5v3.5M8 5v.2"/>',
			'filter'  => '<path d="M2 3.5h12M4.5 8h7M6.5 12.5h3"/>',
			'mail'    => '<path d="M2 3.5h12v9H2z"/><path d="m2 4 6 5 6-5"/>',
			'flag'    => '<path d="M3.5 14.5V2M3.5 2.5h9l-2 3 2 3h-9"/>',
			'edit'    => '<path d="M10.5 2.5 13.5 5.5 5.5 13.5H2.5V10.5z"/>',
			'trash'   => '<path d="M2.5 4h11M6 4V2.5h4V4M4 4l.7 9.5h6.6L12 4"/>',
		);
	}
}//end if

if ( ! function_exists( 'wiw_icon' ) ) {
	/**
	 * Markup of one decorative icon (hidden from assistive technology). Empty for an unknown name.
	 *
	 * @param string $name Icon name.
	 */
	function wiw_icon( string $name ): string {
		$paths = wiw_icon_paths();
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return '<svg class="ico" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}
}

if ( ! function_exists( 'wiw_initials' ) ) {
	/**
	 * One or two capital initials of a name, skipping titles such as Dr and Prof.
	 *
	 * @param string $name Name.
	 */
	function wiw_initials( string $name ): string {
		$name  = (string) preg_replace( '/^(Prof|Dr|Mr|Mrs|Ms|Chief|Engr|Hon)\.?\s+/u', '', trim( $name ) );
		$words = preg_split( '/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY );
		$out   = '';
		foreach ( is_array( $words ) ? $words : array() as $word ) {
			if ( 1 === preg_match( '/^\p{Lu}/u', $word ) ) {
				$out .= mb_substr( $word, 0, 1 );
			}
			if ( mb_strlen( $out ) >= 2 ) {
				break;
			}
		}
		return '' !== $out ? $out : mb_strtoupper( mb_substr( $name, 0, 1 ) );
	}
}//end if

if ( ! function_exists( 'wiw_poster_style' ) ) {
	/**
	 * Poster tone and accent mark for a profile: picked from its slug so the same profile always looks the same.
	 *
	 * @param string $key Slug or name.
	 * @return array{tone: string, mark: string}
	 */
	function wiw_poster_style( string $key ): array {
		$tones = array( 'blue', 'purple', 'green', 'red', 'orange' );
		$marks = array( 'star', 'comb', 'spiral', 'link' );
		$hash  = (int) sprintf( '%u', crc32( $key ) );
		return array(
			'tone' => $tones[ $hash % count( $tones ) ],
			'mark' => $marks[ ( $hash >> 3 ) % count( $marks ) ],
		);
	}
}
