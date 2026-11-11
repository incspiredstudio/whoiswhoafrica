<?php
/**
 * Reading time: words at 180 a minute, never less than two minutes (REQ-029).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Stories;

/**
 * Reading-time helper.
 */
final class ReadingTime {

	public const WORDS_PER_MINUTE = 180;
	public const MINIMUM          = 2;

	/**
	 * Minutes to read some HTML or text.
	 *
	 * @param string $content Post content.
	 */
	public static function minutes( string $content ): int {
		$text  = wp_strip_all_tags( strip_shortcodes( $content ) );
		$words = str_word_count( $text );
		if ( 0 === $words ) {
			$parts = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
			$words = is_array( $parts ) ? count( $parts ) : 0;
		}
		return max( self::MINIMUM, (int) ceil( $words / self::WORDS_PER_MINUTE ) );
	}

	/**
	 * Minutes for a post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function for_post( int $post_id ): int {
		return self::minutes( (string) get_post_field( 'post_content', $post_id ) );
	}
}
