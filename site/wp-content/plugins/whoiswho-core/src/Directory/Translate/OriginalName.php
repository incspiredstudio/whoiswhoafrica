<?php
/**
 * A profile's original-language name (REQ-276, REQ-053): the name as the source gave it before it was translated into English,
 * with its language code. Staff see it on Edit profile and search matches it, but no public template, fact, structured data or
 * import bundle reads it: it is private post meta, not an allowlisted field.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Translate;

/**
 * Original name store.
 */
final class OriginalName {

	public const META_NAME = '_wiw_original_name';
	public const META_LANG = '_wiw_original_lang';

	/**
	 * Longest name kept, in characters.
	 */
	public const MAX = 255;

	/**
	 * Plain names of the languages staff are most likely to meet; any other code is shown as it is.
	 */
	private const LANGUAGES = array(
		'ar'  => 'Arabic',
		'am'  => 'Amharic',
		'fr'  => 'French',
		'pt'  => 'Portuguese',
		'es'  => 'Spanish',
		'sw'  => 'Swahili',
		'so'  => 'Somali',
		'ti'  => 'Tigrinya',
		'zgh' => 'Tamazight',
		'ber' => 'Berber',
	);

	/**
	 * Whether a language code is acceptable: two or three lower-case letters, optionally with subtags (ar, ar-EG, zgh-Tfng).
	 *
	 * @param string $lang Code.
	 */
	public static function valid_lang( string $lang ): bool {
		return 1 === preg_match( '/^[a-z]{2,3}(-[A-Za-z0-9]{2,8}){0,2}$/', $lang );
	}

	/**
	 * The original name of a profile, or null when none is kept.
	 *
	 * @param int $profile_id Profile.
	 * @return array{name: string, lang: string}|null
	 */
	public static function get( int $profile_id ): ?array {
		$name = (string) get_post_meta( $profile_id, self::META_NAME, true );
		if ( '' === trim( $name ) ) {
			return null;
		}
		return array(
			'name' => $name,
			'lang' => (string) get_post_meta( $profile_id, self::META_LANG, true ),
		);
	}

	/**
	 * Keep an original name.
	 *
	 * @param int    $profile_id Profile.
	 * @param string $name       Name as the source gave it.
	 * @param string $lang       Language code.
	 * @throws \InvalidArgumentException For an empty or too long name, or a bad code.
	 */
	public static function set( int $profile_id, string $name, string $lang ): void {
		$name = trim( $name );
		if ( '' === $name || mb_strlen( $name ) > self::MAX || ! self::valid_lang( $lang ) ) {
			throw new \InvalidArgumentException( 'An original name needs a name and a language code.' );
		}
		update_post_meta( $profile_id, self::META_NAME, wp_slash( $name ) );
		update_post_meta( $profile_id, self::META_LANG, $lang );
	}

	/**
	 * Forget the original name.
	 *
	 * @param int $profile_id Profile.
	 */
	public static function clear( int $profile_id ): void {
		delete_post_meta( $profile_id, self::META_NAME );
		delete_post_meta( $profile_id, self::META_LANG );
	}

	/**
	 * The plain name of a language code (Arabic for ar); an unknown code comes back as it is.
	 *
	 * @param string $lang Code.
	 */
	public static function language_name( string $lang ): string {
		$base = strtolower( (string) strtok( $lang, '-' ) );
		return self::LANGUAGES[ $base ] ?? $lang;
	}
}
