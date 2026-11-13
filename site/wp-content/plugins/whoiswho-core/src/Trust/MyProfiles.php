<?php
/**
 * What an assigned user sees on My profiles (REQ-261, REQ-253): only the profiles assigned to them that are published or waiting
 * for review, each with its status (Published, or Pending when a change of theirs awaits a decision), the descriptive fields they
 * may change with the live values, and the note of the last change that was returned to them. Saving never touches the live
 * profile: it files a pending revision through ProfileEditor, which a Reviewer or Administrator decides. Domain service; the
 * screen stays thin.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust;

use WhoIsWho\Core\Directory\Edit\EditRefused;
use WhoIsWho\Core\Directory\Edit\ProfileEditor;
use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;

/**
 * My profiles.
 */
final class MyProfiles {

	public const PUBLISHED = 'Published';
	public const PENDING   = 'Pending';

	/**
	 * Allowlist.
	 *
	 * @var FieldAllowlist
	 */
	private FieldAllowlist $allowlist;

	/**
	 * Editor.
	 *
	 * @var ProfileEditor
	 */
	private ProfileEditor $editor;

	/**
	 * Constructor.
	 *
	 * @param ProfileEditor|null  $editor    Editor.
	 * @param FieldAllowlist|null $allowlist Allowlist.
	 */
	public function __construct( ?ProfileEditor $editor = null, ?FieldAllowlist $allowlist = null ) {
		$this->allowlist = $allowlist ?? new FieldAllowlist();
		$this->editor    = $editor ?? new ProfileEditor( $this->allowlist );
	}

	/**
	 * The profiles assigned to a user that are published or have a change waiting.
	 *
	 * @param int $user_id User.
	 * @return array<int, array{id: int, name: string, status: string, note: string}>
	 */
	public function for_user( int $user_id ): array {
		global $wpdb;
		if ( $user_id < 1 ) {
			return array();
		}
		$ids = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT profile_id FROM ' . StateRepository::table() . ' WHERE assigned_user_id = %d ORDER BY profile_id ASC', $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		$out = array();
		foreach ( array_map( 'intval', $ids ) as $id ) {
			if ( ProfileType::POST_TYPE !== get_post_type( $id ) ) {
				continue;
			}
			$pending = $this->pending( $id, $user_id );
			$state   = (string) ( new StateRepository() )->pub_state( $id );
			if ( ! $pending && ! PubState::is_public( $state ) ) {
				continue;
			}
			$out[] = array(
				'id'     => $id,
				'name'   => get_the_title( $id ),
				'status' => $pending ? self::PENDING : self::PUBLISHED,
				'note'   => $pending ? '' : $this->returned_note( $id, $user_id ),
			);
		}
		return $out;
	}

	/**
	 * Whether a change by this user waits for a decision.
	 *
	 * @param int $profile_id Profile.
	 * @param int $user_id    User.
	 */
	public function pending( int $profile_id, int $user_id ): bool {
		foreach ( ( new VersionRepository() )->pending_for_profile( $profile_id ) as $row ) {
			if ( 'revision' === $row['created_via'] && (int) $row['author_id'] === $user_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The note on the user's most recent change if it was returned, otherwise an empty string.
	 *
	 * @param int $profile_id Profile.
	 * @param int $user_id    User.
	 */
	public function returned_note( int $profile_id, int $user_id ): string {
		global $wpdb;
		$table = VersionRepository::table();
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT d.approval_status, d.reason FROM ' . $table . ' d INNER JOIN ' . $table . ' p ON p.id = d.revision_of WHERE p.profile_id = %d AND p.author_id = %d AND p.created_via = %s ORDER BY d.id DESC LIMIT 1', $profile_id, $user_id, 'revision' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) && 'rejected' === $row['approval_status'] ? (string) $row['reason'] : '';
	}

	/**
	 * The fields this user may change on a profile, with their live values: key => value.
	 *
	 * @param int $profile_id Profile.
	 * @return array<string, string>
	 */
	public function form( int $profile_id ): array {
		$type    = (string) ( ( new StateRepository() )->get( $profile_id )['entity_type'] ?? '' );
		$current = $this->editor->current_values( $profile_id );
		$out     = array();
		foreach ( array_keys( $this->allowlist->for_type( $type ) ) as $key ) {
			if ( AssignedProfiles::field_allowed( (string) $key ) ) {
				$out[ (string) $key ] = (string) ( $current[ $key ] ?? '' );
			}
		}
		return $out;
	}

	/**
	 * Submit changes for review. The live profile does not change.
	 *
	 * @param int                   $profile_id Profile.
	 * @param array<string, string> $values     Field => new value; only changed values are filed.
	 * @param int                   $user_id    The assigned user.
	 * @return int The pending revision id.
	 * @throws EditRefused When refused or nothing changed.
	 */
	public function submit( int $profile_id, array $values, int $user_id ): int {
		$result = $this->editor->edit( $profile_id, $values, 'Change proposed by the profile manager', $user_id );
		return $result['version_id'];
	}
}
