<?php
/**
 * The two things on a profile that have their own stores rather than the field allowlist (REQ-261, REQ-253): the contact details
 * (email, phone, address, kept by ContactSwitches) and the photograph (an attachment id in the profile's image meta). An assigned
 * user can propose a change to either; it travels inside the pending revision under the keys below and is applied only when a
 * Reviewer or Administrator approves it (RevisionService). Whether a detail is shown publicly is never touched here: that switch
 * stays with staff. A proposed photograph must be a picture the same user uploaded.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Edit;

use WhoIsWho\Core\Directory\Contact\ContactSwitches;
use WhoIsWho\Core\Directory\ImageMeta;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Import\Images\ImagePolicy;

/**
 * Side fields.
 */
final class SideFields {

	public const IMAGE = 'image_id';

	/**
	 * The licence, creator and source of a proposed image, set by staff on the Edit profile screen (issue 806). Its value is
	 * {attachment_id, licence, creator, source_url, kind}; it is applied to the attachment's credit meta with the image. An assigned
	 * user cannot propose it through ProfileEditor (acceptable() refuses any non-empty value).
	 */
	public const IMAGE_CREDIT = 'image_credit';

	/**
	 * Revision key => contact kind.
	 */
	public const CONTACT = array(
		'contact_email'   => 'email',
		'contact_phone'   => 'phone',
		'contact_address' => 'address',
	);

	/**
	 * Every key, in the order the form shows them.
	 *
	 * @return string[]
	 */
	public static function keys(): array {
		return array_merge( array_keys( self::CONTACT ), array( self::IMAGE, self::IMAGE_CREDIT ) );
	}

	/**
	 * Whether a revision key is one of the side fields.
	 *
	 * @param string $key Key.
	 */
	public static function is_side( string $key ): bool {
		return in_array( $key, self::keys(), true );
	}

	/**
	 * The live values: the contact values as text and the photograph's attachment id (null when there is none).
	 *
	 * @param int $profile_id Profile.
	 * @return array<string, string|int|null>
	 */
	public static function current( int $profile_id ): array {
		$out = array();
		$all = ( new ContactSwitches() )->all( $profile_id );
		foreach ( self::CONTACT as $key => $kind ) {
			$out[ $key ] = $all[ $kind ]['value'];
		}
		$image                     = (int) get_post_meta( $profile_id, PublicProfile::IMAGE_META, true );
		$out[ self::IMAGE ]        = $image > 0 ? $image : null;
		$out[ self::IMAGE_CREDIT ] = null;
		return $out;
	}

	/**
	 * Whether a proposed value is acceptable. An empty value (clearing the field) always is.
	 *
	 * @param string $key      Key.
	 * @param mixed  $value    Proposed value.
	 * @param int    $actor_id User proposing it.
	 */
	public static function acceptable( string $key, $value, int $actor_id ): bool {
		if ( null === $value || '' === $value ) {
			return true;
		}
		if ( self::IMAGE === $key ) {
			$id = (int) $value;
			return $id > 0 && 'attachment' === get_post_type( $id ) && wp_attachment_is_image( $id ) && (int) get_post_field( 'post_author', $id ) === $actor_id;
		}
		if ( ! isset( self::CONTACT[ $key ] ) || ! is_string( $value ) ) {
			return false;
		}
		if ( strlen( $value ) > 300 || wp_strip_all_tags( $value ) !== $value ) {
			return false;
		}
		return 'contact_email' !== $key || is_email( $value ) !== false;
	}

	/**
	 * Apply an approved value to the live profile. The public switch of a contact detail is left as it is (it goes off when the
	 * value is cleared).
	 *
	 * @param int    $profile_id Profile.
	 * @param string $key        Key.
	 * @param mixed  $value      Approved value; empty clears.
	 */
	public static function apply( int $profile_id, string $key, $value ): void {
		if ( self::IMAGE_CREDIT === $key ) {
			$attachment = is_array( $value ) ? (int) ( $value['attachment_id'] ?? 0 ) : 0;
			$licence    = is_array( $value ) ? (string) ( $value['licence'] ?? '' ) : '';
			if ( $attachment > 0 && 'attachment' === get_post_type( $attachment ) && ImagePolicy::licence_allowed( $licence ) ) {
				$creator = (string) ( $value['creator'] ?? '' );
				ImageMeta::save(
					$attachment,
					array(
						'licence'          => $licence,
						'licence_url'      => ImagePolicy::licence_url( $licence ),
						'creator'          => $creator,
						'attribution_text' => $creator,
						'source_url'       => (string) ( $value['source_url'] ?? '' ),
						'kind'             => in_array( $value['kind'] ?? '', ImagePolicy::KINDS, true ) ? (string) $value['kind'] : 'photo',
					)
				);
			}
			return;
		}
		if ( self::IMAGE === $key ) {
			$id = (int) $value;
			if ( $id > 0 ) {
				update_post_meta( $profile_id, PublicProfile::IMAGE_META, $id );
			} else {
				delete_post_meta( $profile_id, PublicProfile::IMAGE_META );
			}
			return;
		}
		if ( isset( self::CONTACT[ $key ] ) ) {
			( new ContactSwitches() )->apply_approved( $profile_id, self::CONTACT[ $key ], null === $value ? '' : (string) $value );
		}
	}
}
