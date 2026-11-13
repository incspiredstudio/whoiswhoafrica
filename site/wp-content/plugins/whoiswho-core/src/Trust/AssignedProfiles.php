<?php
/**
 * Assigned-profiles-only access (REQ-253, REQ-261, REQ-244). A user whose role holds wiw_edit_assigned_profiles and no staff
 * profile capability may open and edit only the profiles assigned to them (the profile manager of a claimed profile, see
 * ProfileManager), and only the descriptive text and links. Every other profile answers 403. Status, slug, Claimed, arrangements,
 * labels and the lists are never writable by such a user. The plugin ships no role for this: the Administrator creates a role and
 * grants it the capability (plus read and upload_files). Staff holding edit_others_wiw_profiles are not limited by this rule.
 * The decision is pure; the hooks are thin.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust;

use WhoIsWho\Core\Directory\ProfileType;

/**
 * Assigned profiles.
 */
final class AssignedProfiles {

	public const CAPABILITY = 'wiw_edit_assigned_profiles';

	/**
	 * The fields an assigned user may change: descriptive text and links. Contact details and the photograph have their own
	 * stores and are changed through the My profiles screen.
	 */
	public const FIELDS = array( 'summary', 'bio', 'mandate', 'website', 'official_website' );

	/**
	 * Meta capabilities this rule answers for a profile.
	 */
	private const META_CAPS = array( 'edit_post', 'read_post', 'delete_post' );

	/**
	 * Profile manager lookup.
	 *
	 * @var ProfileManager|null
	 */
	private ?ProfileManager $manager;

	/**
	 * Constructor.
	 *
	 * @param ProfileManager|null $manager Profile manager lookup.
	 */
	public function __construct( ?ProfileManager $manager = null ) {
		$this->manager = $manager;
	}

	/**
	 * Register the capability filter.
	 */
	public static function hooks(): void {
		$rule = new self();
		add_filter( 'map_meta_cap', array( $rule, 'map' ), 10, 4 );
	}

	/**
	 * Whether a user is limited to their assigned profiles: they hold the assigned capability but not the staff one.
	 *
	 * @param int $user_id User.
	 */
	public function is_limited( int $user_id ): bool {
		return $user_id > 0 && user_can( $user_id, self::CAPABILITY ) && ! user_can( $user_id, 'edit_others_wiw_profiles' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
	}

	/**
	 * Whether a profile is assigned to a user.
	 *
	 * @param int $user_id    User.
	 * @param int $profile_id Profile.
	 */
	public function is_assigned( int $user_id, int $profile_id ): bool {
		return $user_id > 0 && ( $this->manager ?? new ProfileManager() )->assigned( $profile_id ) === $user_id;
	}

	/**
	 * Whether a field may be changed by an assigned user.
	 *
	 * @param string $field Field key.
	 */
	public static function field_allowed( string $field ): bool {
		return in_array( $field, self::FIELDS, true );
	}

	/**
	 * The meta-capability rule: a limited user may edit and read an assigned profile, never delete it, and gets nothing on any
	 * other profile.
	 *
	 * @param mixed  $caps    Primitive capabilities required.
	 * @param string $cap     Capability being checked.
	 * @param int    $user_id User.
	 * @param mixed  $args    Arguments; the first is the post id.
	 * @return mixed
	 */
	public function map( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, self::META_CAPS, true ) || ! is_array( $args ) || ! isset( $args[0] ) ) {
			return $caps;
		}
		$post_id = (int) $args[0];
		if ( ProfileType::POST_TYPE !== get_post_type( $post_id ) || ! $this->is_limited( (int) $user_id ) ) {
			return $caps;
		}
		if ( 'delete_post' !== $cap && $this->is_assigned( (int) $user_id, $post_id ) ) {
			return array( self::CAPABILITY );
		}
		return array( 'do_not_allow' );
	}
}
