<?php
/**
 * The one place an owner's proposed change to a profile is built and filed (REQ-274, REQ-261, REQ-253). It takes the submitted
 * form, keeps only the fields an owner may change (AssignedProfiles::FIELDS through MyProfiles::form, the contact details and the
 * photograph), files them as a pending revision through ProfileEditor (which applies the field allowlist and the validator), and
 * never touches the live profile. A profile that is not the owner's is refused, and so is a second change while one is waiting
 * for review. A proposed photograph is checked and kept in the media library server side; it is not the profile's picture until a
 * reviewer approves the change. Domain service: the public account pages call it.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust;

use WhoIsWho\Core\Commerce\Invitations\ImageCheck;
use WhoIsWho\Core\Directory\Edit\EditRefused;
use WhoIsWho\Core\Directory\Edit\SideFields;
use WhoIsWho\Core\Platform\Admin\Request;

/**
 * Owner edits.
 */
final class OwnerEdits {

	/**
	 * Fields shown and read as a longer text box.
	 */
	public const LONG = array( 'summary', 'bio', 'mandate' );

	/**
	 * Service.
	 *
	 * @var MyProfiles
	 */
	private MyProfiles $profiles;

	/**
	 * Rule.
	 *
	 * @var AssignedProfiles
	 */
	private AssignedProfiles $rule;

	/**
	 * Constructor.
	 *
	 * @param MyProfiles|null       $profiles Service.
	 * @param AssignedProfiles|null $rule     Assignment rule.
	 */
	public function __construct( ?MyProfiles $profiles = null, ?AssignedProfiles $rule = null ) {
		$this->profiles = $profiles ?? new MyProfiles();
		$this->rule     = $rule ?? new AssignedProfiles();
	}

	/**
	 * Whether the user owns the profile.
	 *
	 * @param int $user_id    User.
	 * @param int $profile_id Profile.
	 */
	public function owns( int $user_id, int $profile_id ): bool {
		return $this->rule->is_assigned( $user_id, $profile_id );
	}

	/**
	 * The form of one owned profile: the descriptive fields with their live values, the contact details and the current photo.
	 *
	 * @param int $profile_id Profile.
	 * @return array{fields: array<string, string>, contact: array<string, string>, image: int}
	 */
	public function form( int $profile_id ): array {
		$side    = SideFields::current( $profile_id );
		$contact = array();
		foreach ( array_keys( SideFields::CONTACT ) as $key ) {
			$contact[ $key ] = (string) $side[ $key ];
		}
		return array(
			'fields'  => $this->profiles->form( $profile_id ),
			'contact' => $contact,
			'image'   => (int) $side[ SideFields::IMAGE ],
		);
	}

	/**
	 * File the changed fields as a pending revision.
	 *
	 * @param int     $profile_id Profile.
	 * @param Request $request    The submitted form.
	 * @param int     $user_id    The signed-in owner.
	 * @return int The pending revision id.
	 * @throws EditRefused When the profile is not theirs, a change already waits, nothing changed or a value is refused.
	 */
	public function submit( int $profile_id, Request $request, int $user_id ): int {
		if ( ! $this->owns( $user_id, $profile_id ) ) {
			throw EditRefused::because( 'That profile is not yours.' );
		}
		if ( $this->profiles->pending( $profile_id, $user_id ) ) {
			throw EditRefused::because( 'Your last change is still waiting for review.' );
		}
		$changes = array();
		foreach ( $this->profiles->form( $profile_id ) as $key => $live ) {
			if ( ! $request->has( $key ) ) {
				continue;
			}
			$value = in_array( $key, self::LONG, true ) ? $request->text( $key ) : $request->string( $key );
			if ( trim( $value ) !== trim( $live ) ) {
				$changes[ $key ] = trim( $value );
			}
		}
		$side = SideFields::current( $profile_id );
		foreach ( array_keys( SideFields::CONTACT ) as $key ) {
			if ( $request->has( $key ) && trim( $request->string( $key ) ) !== (string) $side[ $key ] ) {
				$changes[ $key ] = trim( $request->string( $key ) );
			}
		}
		$image = $request->file( 'image' );
		if ( null !== $image && UPLOAD_ERR_NO_FILE !== $image['error'] ) {
			$changes[ SideFields::IMAGE ] = $this->store_image( $image );
		}
		return $this->profiles->submit( $profile_id, $changes, $user_id );
	}

	/**
	 * Keep a proposed photograph in the media library, checked for type and size.
	 *
	 * @param array{name: string, tmp_name: string, size: int, error: int} $image Upload.
	 * @return int Attachment id.
	 * @throws EditRefused When the file is not an acceptable picture.
	 */
	private function store_image( array $image ): int {
		if ( null !== ImageCheck::check( $image ) ) {
			throw EditRefused::because( 'The picture must be a JPEG, PNG or WebP file up to 2 MB.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment = media_handle_sideload(
			array(
				'name'     => $image['name'],
				'tmp_name' => $image['tmp_name'],
			),
			0
		);
		if ( ! is_int( $attachment ) ) {
			throw EditRefused::because( 'The picture could not be saved.' );
		}
		return $attachment;
	}
}
