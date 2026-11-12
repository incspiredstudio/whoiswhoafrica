<?php
/**
 * Direct profile edit (REQ-125, REQ-188, REQ-232, REQ-143, REQ-244): changes allowlisted fields and terms with a mandatory
 * reason, appends a version row (author, time, reason, old and new values), writes an audit row through AuditLogger, and can close
 * the open correction the edit answers. An edit by the assigned user of a claimed profile is not applied: it is stored as a
 * pending revision for the review step (M1-REV-11). Any other edit applies at once and a published page is queued for purge.
 * Domain service; the screen and handler stay thin.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Edit;

use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\Fields\FieldValidator;
use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Purge\PurgeQueue;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Profile editor.
 */
final class ProfileEditor {

	public const EDIT_CAPABILITY = 'edit_wiw_profiles';

	/**
	 * Allowlist.
	 *
	 * @var FieldAllowlist
	 */
	private FieldAllowlist $allowlist;

	/**
	 * Constructor.
	 *
	 * @param FieldAllowlist|null $allowlist Allowlist.
	 */
	public function __construct( ?FieldAllowlist $allowlist = null ) {
		$this->allowlist = $allowlist ?? new FieldAllowlist();
	}

	/**
	 * The current value of each allowlisted field of a profile (lists as arrays).
	 *
	 * @param int $profile_id Profile.
	 * @return array<string, mixed>
	 */
	public function current_values( int $profile_id ): array {
		$type = (string) ( ( new StateRepository() )->get( $profile_id )['entity_type'] ?? '' );
		$out  = array();
		foreach ( $this->allowlist->for_type( $type ) as $key => $rule ) {
			$key = (string) $key;
			if ( 'name' === $key ) {
				$out[ $key ] = get_the_title( $profile_id );
				continue;
			}
			$raw = (string) get_post_meta( $profile_id, (string) FieldAllowlist::meta_key( $key ), true );
			if ( 'list' === $rule['type'] ) {
				$decoded     = '' === $raw ? array() : json_decode( $raw, true );
				$out[ $key ] = is_array( $decoded ) ? array_values( $decoded ) : array();
			} elseif ( 'int' === $rule['type'] ) {
				$out[ $key ] = '' === $raw ? null : (int) $raw;
			} else {
				$out[ $key ] = $raw;
			}
		}
		return $out;
	}

	/**
	 * Edit a profile.
	 *
	 * @param int                  $profile_id Profile.
	 * @param array<string, mixed> $changes    Field key => new value; null or empty clears an optional field.
	 * @param string               $reason     Why, or the request reference. Required.
	 * @param int                  $actor_id   The user making the edit.
	 * @param int                  $resolves   Support item (a correction) this edit answers, or 0.
	 * @param array<string, int[]> $terms      Taxonomy => the full set of term ids to assign.
	 * @return array{version_id: int, applied: bool, changed: string[]}
	 * @throws EditRefused With a message fit to show.
	 */
	public function edit( int $profile_id, array $changes, string $reason, int $actor_id, int $resolves = 0, array $terms = array() ): array {
		if ( ! user_can( $actor_id, self::EDIT_CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- generated for the profile type, listed in config/capabilities.php.
			throw EditRefused::because( 'You are not allowed to edit profiles.' );
		}
		$reason = trim( $reason );
		if ( '' === $reason ) {
			throw EditRefused::because( 'A reason or request reference is required to edit a profile.' );
		}
		$state = ( new StateRepository() )->get( $profile_id );
		if ( null === $state || ProfileType::POST_TYPE !== get_post_type( $profile_id ) ) {
			throw EditRefused::because( 'That profile does not exist.' );
		}
		$type      = (string) $state['entity_type'];
		$validator = new FieldValidator( $this->allowlist );
		$current   = $this->current_values( $profile_id );
		$old       = array();
		$new       = array();
		$clear     = array();
		foreach ( $changes as $key => $value ) {
			$key = (string) $key;
			if ( $this->allowlist->is_forbidden( $key ) || ! $this->allowlist->is_allowed( $type, $key ) ) {
				throw EditRefused::because( 'That field cannot be stored on a profile.' );
			}
			$rule  = $this->allowlist->for_type( $type )[ $key ];
			$empty = null === $value || '' === $value || array() === $value;
			if ( $empty ) {
				if ( ! empty( $rule['required'] ) ) {
					throw EditRefused::because( 'A required field cannot be left empty.' );
				}
				$was = $current[ $key ];
				if ( is_array( $was ) ? array() !== $was : '' !== (string) $was ) {
					$old[ $key ]   = $current[ $key ];
					$new[ $key ]   = null;
					$clear[ $key ] = true;
				}
				continue;
			}
			if ( ! $validator->value_acceptable( $type, $key, $value ) ) {
				throw EditRefused::because( 'That value is not acceptable for the field.' );
			}
			if ( wp_json_encode( $current[ $key ] ) !== wp_json_encode( $value ) ) {
				$old[ $key ] = $current[ $key ];
				$new[ $key ] = $value;
			}
		}//end foreach
		$term_changes = $this->term_changes( $profile_id, $terms, $actor_id );
		foreach ( $term_changes as $taxonomy => $change ) {
			$old[ 'terms:' . $taxonomy ] = $change['old'];
			$new[ 'terms:' . $taxonomy ] = $change['new'];
		}
		if ( array() === $new ) {
			throw EditRefused::because( 'Nothing changed.' );
		}
		$item = $this->correction( $resolves, $profile_id );

		$pending = (int) ( $state['claimed'] ?? 0 ) > 0 && (int) ( $state['assigned_user_id'] ?? 0 ) === $actor_id && $actor_id > 0;
		$version = ( new VersionRepository() )->append( $profile_id, $actor_id, $pending ? 'revision' : 'edit', $reason, $old, $new, $pending ? 'pending' : null );
		if ( ! $pending ) {
			$fields = new ProfileFields( $this->allowlist );
			foreach ( $new as $key => $value ) {
				if ( 0 === strpos( $key, 'terms:' ) ) {
					continue;
				}
				$fields->set( $profile_id, $key, isset( $clear[ $key ] ) ? '' : $value );
			}
			foreach ( $term_changes as $taxonomy => $change ) {
				wp_set_object_terms( $profile_id, $change['new'], $taxonomy );
			}
		}
		AuditLogger::for_site()->log(
			$pending ? 'profile.revision_submitted' : 'profile.edit',
			array(
				'type' => 'profile',
				'id'   => $profile_id,
			),
			$old,
			$new,
			$reason
		);
		if ( ! $pending && null !== $item ) {
			( new SupportItems() )->set_status( (int) $item['id'], ItemStatus::RESOLVED );
			AuditLogger::for_site()->log(
				'support.resolve',
				array(
					'type' => 'support_item',
					'id'   => (int) $item['id'],
				),
				$item['status'],
				ItemStatus::RESOLVED,
				$reason
			);
		}
		$public = PubState::is_public( (string) $state['pub_state'] );
		if ( ! $pending && $public ) {
			PurgeQueue::for_site()->queue( array( $profile_id ), 'edit' );
		}
		return array(
			'version_id' => $version,
			'applied'    => ! $pending,
			'changed'    => array_keys( $new ),
		);
	}

	/**
	 * Which term sets differ from the current ones. Needs assign_terms.
	 *
	 * @param int                  $profile_id Profile.
	 * @param array<string, int[]> $terms      Taxonomy => term ids.
	 * @param int                  $actor_id   Actor.
	 * @return array<string, array{old: int[], new: int[]}>
	 * @throws EditRefused For an unknown taxonomy or term, or without assign_terms.
	 */
	private function term_changes( int $profile_id, array $terms, int $actor_id ): array {
		$out = array();
		foreach ( $terms as $taxonomy => $ids ) {
			$taxonomy = (string) $taxonomy;
			if ( ! in_array( $taxonomy, Taxonomies::all(), true ) ) {
				throw EditRefused::because( 'That list cannot be assigned.' );
			}
			if ( ! user_can( $actor_id, 'assign_terms' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
				throw EditRefused::because( 'You are not allowed to assign terms.' );
			}
			$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
			foreach ( $ids as $id ) {
				if ( ! term_exists( $id, $taxonomy ) ) {
					throw EditRefused::because( 'That term does not exist.' );
				}
			}
			$have = wp_get_object_terms( $profile_id, $taxonomy, array( 'fields' => 'ids' ) );
			$have = is_array( $have ) ? array_map( 'intval', $have ) : array();
			sort( $have );
			sort( $ids );
			if ( $have !== $ids ) {
				$out[ $taxonomy ] = array(
					'old' => $have,
					'new' => $ids,
				);
			}
		}//end foreach
		return $out;
	}

	/**
	 * The open correction an edit answers, checked to be a correction on this profile.
	 *
	 * @param int $item_id    Support item id, or 0.
	 * @param int $profile_id Profile.
	 * @return array<string, mixed>|null
	 * @throws EditRefused When the item is not an open correction on this profile.
	 */
	private function correction( int $item_id, int $profile_id ): ?array {
		if ( $item_id <= 0 ) {
			return null;
		}
		$item = ( new SupportItems() )->get( $item_id );
		if ( null === $item || ItemType::CORRECTION !== $item['item_type'] || (int) $item['profile_id'] !== $profile_id || ItemStatus::is_closed( (string) $item['status'] ) ) {
			throw EditRefused::because( 'Choose an open correction on this profile.' );
		}
		return $item;
	}
}
