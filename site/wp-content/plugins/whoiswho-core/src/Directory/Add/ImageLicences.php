<?php
/**
 * The open image licences a profile photo or logo may carry (import contract v1.1 section 10, REQ-209), and the attachment meta
 * keys that hold an image's licence and credit. Any other licence is refused. Plain PHP, no WordPress calls.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Add;

/**
 * Image licences.
 */
final class ImageLicences {

	// The same keys as the import's image meta (Directory\ImageMeta on the profile-sections branch), so the read model's credit
	// line reads a hand-added image exactly like an imported one.
	public const META_LICENCE     = '_wiw_image_licence';
	public const META_LICENCE_URL = '_wiw_image_licence_url';
	public const META_ATTRIBUTION = '_wiw_image_attribution';
	public const META_CREATOR     = '_wiw_image_creator';
	public const META_SOURCE_URL  = '_wiw_image_source_url';
	public const META_KIND        = '_wiw_image_kind';

	/**
	 * Allowed licence id => label and deed URL.
	 */
	private const LICENCES = array(
		'CC0-1.0'      => array( 'CC0 1.0 (public domain dedication)', 'https://creativecommons.org/publicdomain/zero/1.0/' ),
		'PDM-1.0'      => array( 'Public domain (Public Domain Mark 1.0)', 'https://creativecommons.org/publicdomain/mark/1.0/' ),
		'CC-BY-2.0'    => array( 'CC BY 2.0', 'https://creativecommons.org/licenses/by/2.0/' ),
		'CC-BY-2.5'    => array( 'CC BY 2.5', 'https://creativecommons.org/licenses/by/2.5/' ),
		'CC-BY-3.0'    => array( 'CC BY 3.0', 'https://creativecommons.org/licenses/by/3.0/' ),
		'CC-BY-4.0'    => array( 'CC BY 4.0', 'https://creativecommons.org/licenses/by/4.0/' ),
		'CC-BY-SA-2.0' => array( 'CC BY-SA 2.0', 'https://creativecommons.org/licenses/by-sa/2.0/' ),
		'CC-BY-SA-2.5' => array( 'CC BY-SA 2.5', 'https://creativecommons.org/licenses/by-sa/2.5/' ),
		'CC-BY-SA-3.0' => array( 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0/' ),
		'CC-BY-SA-4.0' => array( 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0/' ),
	);

	/**
	 * Allowed image types.
	 */
	public const MIME_TYPES = array( 'image/jpeg', 'image/png', 'image/webp' );

	/**
	 * Allowed licence ids => labels, for a select.
	 *
	 * @return array<string, string>
	 */
	public static function choices(): array {
		return array_map( static fn ( array $licence ): string => $licence[0], self::LICENCES );
	}

	/**
	 * Whether a licence id is allowed.
	 *
	 * @param string $id Licence id.
	 */
	public static function is_allowed( string $id ): bool {
		return isset( self::LICENCES[ $id ] );
	}

	/**
	 * The deed URL of an allowed licence, or an empty string.
	 *
	 * @param string $id Licence id.
	 */
	public static function url( string $id ): string {
		return self::LICENCES[ $id ][1] ?? '';
	}
}
