<?php
/**
 * Profile field storage (REQ-139, REQ-140): registers post meta only for allowlisted fields, refuses writes of any other
 * attribute on a profile, and offers one way to set a field that checks the profile's entity type. No meta key exists for a
 * forbidden attribute, and the metadata filters stop a direct add_post_meta of one.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Fields;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\StateRepository;

/**
 * Profile fields.
 */
final class ProfileFields {

	/**
	 * Allowlists.
	 *
	 * @var FieldAllowlist
	 */
	private FieldAllowlist $allowlist;

	/**
	 * Constructor.
	 *
	 * @param FieldAllowlist|null $allowlist Allowlists.
	 */
	public function __construct( ?FieldAllowlist $allowlist = null ) {
		$this->allowlist = $allowlist ?? new FieldAllowlist();
	}

	/**
	 * Register the meta keys and the write guard. Hooked to init.
	 */
	public function register(): void {
		foreach ( $this->allowlist->all_keys() as $key ) {
			$meta_key = FieldAllowlist::meta_key( $key );
			if ( null === $meta_key ) {
				continue;
			}
			register_post_meta(
				ProfileType::POST_TYPE,
				$meta_key,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => static fn ( $allowed, $meta, $post_id ): bool => current_user_can( 'edit_post', (int) $post_id ),
				)
			);
		}
		add_filter( 'add_post_metadata', array( $this, 'guard_write' ), 10, 3 );
		add_filter( 'update_post_metadata', array( $this, 'guard_write' ), 10, 3 );
	}

	/**
	 * Refuse a metadata write that would store a forbidden attribute, or a field the profile's type may not hold.
	 *
	 * @param mixed  $check    Null to continue, anything else short-circuits.
	 * @param int    $post_id  Post id.
	 * @param string $meta_key Meta key.
	 * @return mixed
	 */
	public function guard_write( $check, $post_id, $meta_key ) {
		if ( null !== $check || ProfileType::POST_TYPE !== get_post_type( (int) $post_id ) || 0 !== strpos( (string) $meta_key, FieldAllowlist::META_PREFIX ) ) {
			return $check;
		}
		$key = substr( (string) $meta_key, strlen( FieldAllowlist::META_PREFIX ) );
		if ( $this->allowlist->is_forbidden( $key ) ) {
			return false;
		}
		if ( in_array( $key, $this->allowlist->all_keys(), true ) ) {
			$type = ( new StateRepository() )->get( (int) $post_id )['entity_type'] ?? null;
			if ( null !== $type && ! $this->allowlist->is_allowed( (string) $type, $key ) ) {
				return false;
			}
		}
		return $check;
	}

	/**
	 * Set one field on a profile.
	 *
	 * @param int    $post_id Profile post id.
	 * @param string $key     Field key.
	 * @param mixed  $value   Value (a list is an array of strings).
	 * @throws \InvalidArgumentException When the profile has no entity type, or the type may not hold the field.
	 */
	public function set( int $post_id, string $key, $value ): void {
		$type = ( new StateRepository() )->get( $post_id )['entity_type'] ?? null;
		if ( null === $type || ! $this->allowlist->is_allowed( (string) $type, $key ) ) {
			throw new \InvalidArgumentException( 'That field cannot be stored on this profile.' );
		}
		if ( 'name' === $key ) {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => (string) $value,
				)
			);
			return;
		}
		$meta_key = (string) FieldAllowlist::meta_key( $key );
		update_post_meta( $post_id, $meta_key, is_array( $value ) ? wp_json_encode( array_values( $value ) ) : (string) $value );
	}
}
