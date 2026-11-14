<?php
/**
 * Which images a bundle may carry (contract 1.1 section 10, REQ-162, REQ-203, D-024): only open-licence images, JPEG, PNG or
 * WebP, at most 5 MB, each entry with exactly the contract's keys. An image that breaks a rule is refused on its own with
 * IMAGE_NOT_ALLOWED; the rest of the record still imports. Contract 1.0 allows no images at all. Plain PHP, no WordPress calls.
 *
 * The entry is checked here; the file itself (present, hash, size, real type) is checked by ImageImporter when it is applied.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Images;

/**
 * Image policy.
 */
final class ImagePolicy {

	public const CODE = 'IMAGE_NOT_ALLOWED';

	/**
	 * Allowed licence ids (contract 1.1 section 10).
	 */
	public const LICENCES = array( 'CC0-1.0', 'PDM-1.0', 'CC-BY-2.0', 'CC-BY-2.5', 'CC-BY-3.0', 'CC-BY-4.0', 'CC-BY-SA-2.0', 'CC-BY-SA-2.5', 'CC-BY-SA-3.0', 'CC-BY-SA-4.0' );

	/**
	 * The label and deed URL of each allowed licence (same ids as LICENCES), for the Add profile screen's select and credit link.
	 */
	private const DEEDS = array(
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
	 * Whether a licence id is on the allowlist.
	 *
	 * @param string $id Licence id.
	 */
	public static function licence_allowed( string $id ): bool {
		return in_array( $id, self::LICENCES, true );
	}

	/**
	 * Allowed licence ids => labels, for a select.
	 *
	 * @return array<string, string>
	 */
	public static function licence_choices(): array {
		$choices = array();
		foreach ( self::LICENCES as $id ) {
			$choices[ $id ] = self::DEEDS[ $id ][0] ?? $id;
		}
		return $choices;
	}

	/**
	 * The deed URL of an allowed licence, or an empty string.
	 *
	 * @param string $id Licence id.
	 */
	public static function licence_url( string $id ): string {
		return self::licence_allowed( $id ) ? ( self::DEEDS[ $id ][1] ?? '' ) : '';
	}

	/**
	 * Allowed types: mime type => file extensions.
	 */
	public const TYPES = array(
		'image/jpeg' => array( 'jpg', 'jpeg' ),
		'image/png'  => array( 'png' ),
		'image/webp' => array( 'webp' ),
	);

	public const MAX_BYTES = 5242880;

	public const KINDS = array( 'photo', 'logo' );

	/**
	 * Every key of an image entry.
	 */
	public const KEYS = array( 'image_id', 'file', 'sha256', 'mime_type', 'width', 'height', 'kind', 'source_url', 'retrieved_at', 'creator', 'licence', 'licence_url', 'attribution_text' );

	/**
	 * The path pattern of an image file inside a bundle: images/<sha256>.<ext>.
	 */
	public const FILE_PATTERN = '#^images/([0-9a-f]{64})\.(jpg|jpeg|png|webp)$#';

	/**
	 * Why an image entry is refused, or null when it may be imported.
	 *
	 * @param mixed $image One entry of the record's images array.
	 * @return string|null A short reason for staff (never a value), or null.
	 */
	public static function refusal( $image ): ?string {
		if ( ! is_array( $image ) ) {
			return 'shape';
		}
		$keys = array_keys( $image );
		sort( $keys );
		$want = self::KEYS;
		sort( $want );
		if ( $keys !== $want ) {
			return 'shape';
		}
		foreach ( array( 'image_id', 'file', 'sha256', 'mime_type', 'kind', 'source_url', 'retrieved_at', 'licence', 'licence_url', 'attribution_text' ) as $key ) {
			if ( ! is_string( $image[ $key ] ) || '' === trim( $image[ $key ] ) ) {
				return 'shape';
			}
		}
		if ( ! is_string( $image['creator'] ) || strlen( $image['creator'] ) > 1000 || strlen( $image['attribution_text'] ) > 2000 || strlen( $image['image_id'] ) > 64 ) {
			return 'shape';
		}
		if ( ! in_array( $image['licence'], self::LICENCES, true ) ) {
			return 'licence';
		}
		// The creator may be empty only for public-domain material (contract 1.1 section 10).
		if ( '' === trim( $image['creator'] ) && ! in_array( $image['licence'], array( 'CC0-1.0', 'PDM-1.0' ), true ) ) {
			return 'shape';
		}
		if ( ! isset( self::TYPES[ $image['mime_type'] ] ) ) {
			return 'type';
		}
		if ( 1 !== preg_match( self::FILE_PATTERN, $image['file'], $m ) || ! in_array( $m[2], self::TYPES[ $image['mime_type'] ], true ) ) {
			return 'file';
		}
		if ( self::hex( $image['sha256'] ) !== $m[1] ) {
			return 'hash';
		}
		if ( ! in_array( $image['kind'], self::KINDS, true ) ) {
			return 'kind';
		}
		if ( ! is_int( $image['width'] ) || ! is_int( $image['height'] ) || $image['width'] < 1 || $image['height'] < 1 ) {
			return 'shape';
		}
		foreach ( array( 'source_url', 'licence_url' ) as $key ) {
			if ( false === filter_var( $image[ $key ], FILTER_VALIDATE_URL ) || 1 !== preg_match( '#^https?://#i', $image[ $key ] ) ) {
				return 'shape';
			}
		}
		if ( false === strtotime( $image['retrieved_at'] ) ) {
			return 'shape';
		}
		return null;
	}

	/**
	 * The lowercase hex digest of a sha256 value given with or without the "sha256:" prefix.
	 *
	 * @param string $value Value.
	 */
	public static function hex( string $value ): string {
		return strtolower( (string) preg_replace( '/^sha256:/', '', $value ) );
	}

	/**
	 * Split a record's images into the ones that may be imported and the ids of the refused ones.
	 *
	 * @param string       $contract_version The record's contract version.
	 * @param array<mixed> $images           The record's images.
	 * @return array{accepted: array<int, array<string, mixed>>, refused: array<int, array{image_id: string, reason: string}>}
	 */
	public static function split( string $contract_version, array $images ): array {
		$out = array(
			'accepted' => array(),
			'refused'  => array(),
		);
		foreach ( $images as $i => $image ) {
			$reason = '1.0' === $contract_version ? 'version' : self::refusal( $image );
			$id     = is_array( $image ) && is_string( $image['image_id'] ?? null ) ? substr( $image['image_id'], 0, 64 ) : 'image:' . $i;
			if ( null === $reason ) {
				$out['accepted'][] = $image;
			} else {
				$out['refused'][] = array(
					'image_id' => $id,
					'reason'   => $reason,
				);
			}
		}
		return $out;
	}
}
