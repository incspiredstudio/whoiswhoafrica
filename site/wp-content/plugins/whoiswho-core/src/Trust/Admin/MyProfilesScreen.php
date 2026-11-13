<?php
/**
 * The My profiles screen (REQ-261, REQ-253, REQ-047): the one screen of a limited profile manager. It lists only the profiles
 * assigned to the signed-in user with their status (Published or Pending) and any note on a returned change, and opens a form for
 * the descriptive text and links of one of them. Saving files a pending revision for review and leaves the live profile as it
 * is. Held by wiw_edit_assigned_profiles; everyone else gets a 403 and no menu entry. The profile is checked again on every save.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust\Admin;

use WhoIsWho\Core\Commerce\Invitations\ImageCheck;
use WhoIsWho\Core\Directory\Edit\EditRefused;
use WhoIsWho\Core\Directory\Edit\SideFields;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Trust\AssignedProfiles;
use WhoIsWho\Core\Trust\MyProfiles;

/**
 * My profiles screen.
 */
final class MyProfilesScreen {

	public const SCREEN = 'wiw-my-profiles';
	public const CAP    = AssignedProfiles::CAPABILITY;
	public const ACTION = 'wiw_my_profile_save';

	/**
	 * Fields shown as a longer text box.
	 */
	private const LONG = array( 'summary', 'bio', 'mandate' );

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
	 * Register the screen and its action, in the admin only, and tidy the menu of a limited user.
	 */
	public static function hooks(): void {
		add_action( 'init', array( self::class, 'register_admin' ), 1 );
		add_action( 'admin_menu', array( self::class, 'trim_menu' ), 999 );
	}

	/**
	 * Register in the admin menu.
	 */
	public static function register_admin(): void {
		if ( ! is_admin() ) {
			return;
		}
		$registry = ScreenRegistry::shared();
		$registry->register(
			self::SCREEN,
			__( 'My profiles', 'whoiswho' ),
			self::CAP,
			static function (): void {
				echo ( new self() )->html( Request::from_globals(), get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
			},
			false,
			'directory'
		);
		$registry->hook();
		ActionGuard::register(
			self::ACTION,
			self::CAP,
			static function ( Request $request ): void {
				( new self() )->handle_save( $request, get_current_user_id() );
			}
		);
	}

	/**
	 * A limited user's menu holds My profiles and what their capabilities allow: the dashboard and profile pages that every
	 * signed-in user gets are removed.
	 */
	public static function trim_menu(): void {
		if ( ! ( new AssignedProfiles() )->is_limited( get_current_user_id() ) ) {
			return;
		}
		remove_menu_page( 'index.php' );
		remove_menu_page( 'profile.php' );
	}

	/**
	 * File a change from the form.
	 *
	 * @param Request $request Request: profile and the field values.
	 * @param int     $user_id The signed-in user.
	 */
	public function handle_save( Request $request, int $user_id ): void {
		$id     = $request->int( 'profile' );
		$result = 'saved';
		try {
			$this->save( $id, $request, $user_id );
		} catch ( EditRefused $e ) {
			$result = 'refused';
		}
		wp_safe_redirect( add_query_arg( 'result', $result, admin_url( 'admin.php?page=' . self::SCREEN . '&profile=' . $id ) ) );
		exit;
	}

	/**
	 * File the changed fields as a pending revision.
	 *
	 * @param int     $profile_id Profile.
	 * @param Request $request    Request.
	 * @param int     $user_id    User.
	 * @return int The pending revision id.
	 * @throws EditRefused When the profile is not theirs, nothing changed or a value is refused.
	 */
	public function save( int $profile_id, Request $request, int $user_id ): int {
		if ( ! $this->rule->is_assigned( $user_id, $profile_id ) ) {
			throw EditRefused::because( 'That profile is not assigned to you.' );
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
	 * Keep a proposed photograph in the media library, checked for type and size. It is not the profile's picture until a reviewer
	 * approves the change.
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

	/**
	 * The screen markup.
	 *
	 * @param Request $request Request.
	 * @param int     $user_id The signed-in user.
	 * @throws \DomainException Without the capability.
	 */
	public function html( Request $request, int $user_id ): string {
		if ( ! user_can( $user_id, self::CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'Not allowed to use My profiles.' );
		}
		$id = $request->int( 'profile' );
		if ( $id > 0 && $this->rule->is_assigned( $user_id, $id ) ) {
			return $this->form_html( $id, $request->string( 'result' ) );
		}
		return $this->list_html( $user_id );
	}

	/**
	 * The list.
	 *
	 * @param int $user_id User.
	 */
	private function list_html( int $user_id ): string {
		$rows = $this->profiles->for_user( $user_id );
		$out  = '<div class="wrap"><h1>' . esc_html__( 'My profiles', 'whoiswho' ) . '</h1>';
		$out .= '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Profile', 'whoiswho' ) . '</th><th>' . esc_html__( 'Status', 'whoiswho' ) . '</th><th>' . esc_html__( 'Note from the reviewer', 'whoiswho' ) . '</th></tr></thead><tbody>';
		if ( array() === $rows ) {
			$out .= '<tr><td colspan="3">' . esc_html__( 'No profiles are assigned to you.', 'whoiswho' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$out .= '<tr><td><a href="' . esc_url( admin_url( 'admin.php?page=' . self::SCREEN . '&profile=' . $row['id'] ) ) . '">' . esc_html( $row['name'] ) . '</a></td>';
			$out .= '<td>' . esc_html( $row['status'] ) . '</td><td>' . esc_html( $row['note'] ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/**
	 * The edit form of one assigned profile.
	 *
	 * @param int    $id     Profile.
	 * @param string $result Result word from the last save.
	 */
	private function form_html( int $id, string $result ): string {
		$out  = '<div class="wrap"><h1>' . esc_html( get_the_title( $id ) ) . '</h1>';
		$out .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::SCREEN ) ) . '">' . esc_html__( 'Back to My profiles', 'whoiswho' ) . '</a></p>';
		if ( 'saved' === $result ) {
			$out .= '<div class="notice notice-success inline"><p>' . esc_html__( 'Your change was sent for review. The live profile has not changed yet.', 'whoiswho' ) . '</p></div>';
		} elseif ( 'refused' === $result ) {
			$out .= '<div class="notice notice-error inline"><p>' . esc_html__( 'Nothing was sent: there was no change, or a value was not accepted.', 'whoiswho' ) . '</p></div>';
		}
		$out .= '<p>' . esc_html__( 'Edits are checked by a reviewer before they appear on the public profile.', 'whoiswho' ) . '</p>';
		$out .= '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" /><input type="hidden" name="profile" value="' . esc_attr( (string) $id ) . '" />';
		$out .= '<table class="form-table"><tbody>';
		foreach ( $this->profiles->form( $id ) as $key => $value ) {
			$label = ucfirst( str_replace( '_', ' ', $key ) );
			$out  .= '<tr><th><label for="wiw-mp-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
			if ( in_array( $key, self::LONG, true ) ) {
				$out .= '<textarea id="wiw-mp-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" rows="6" cols="70">' . esc_textarea( $value ) . '</textarea>';
			} else {
				$out .= '<input class="regular-text" id="wiw-mp-' . esc_attr( $key ) . '" type="url" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
			}
			$out .= '</td></tr>';
		}
		$side = SideFields::current( $id );
		foreach ( SideFields::CONTACT as $key => $kind ) {
			$labels = array(
				'email'   => __( 'Contact email', 'whoiswho' ),
				'phone'   => __( 'Contact phone', 'whoiswho' ),
				'address' => __( 'Contact address', 'whoiswho' ),
			);
			$out   .= '<tr><th><label for="wiw-mp-' . esc_attr( $key ) . '">' . esc_html( $labels[ $kind ] ) . '</label></th><td>';
			$out   .= '<input class="regular-text" id="wiw-mp-' . esc_attr( $key ) . '" type="' . ( 'email' === $kind ? 'email' : 'text' ) . '" name="' . esc_attr( $key ) . '" maxlength="300" value="' . esc_attr( (string) $side[ $key ] ) . '" />';
			$out   .= '<p class="description">' . esc_html__( 'Staff decide whether this is shown publicly. A change waits for a reviewer.', 'whoiswho' ) . '</p></td></tr>';
		}
		$out .= '<tr><th><label for="wiw-mp-image">' . esc_html__( 'Photograph', 'whoiswho' ) . '</label></th><td>';
		if ( null !== $side[ SideFields::IMAGE ] ) {
			$out .= wp_get_attachment_image( (int) $side[ SideFields::IMAGE ], 'thumbnail' );
		}
		$out .= '<input id="wiw-mp-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" /><p class="description">' . esc_html__( 'JPEG, PNG or WebP, up to 2 MB. The current photograph stays until a reviewer approves the new one.', 'whoiswho' ) . '</p></td></tr>';
		return $out . '</tbody></table><button class="button button-primary">' . esc_html__( 'Send for review', 'whoiswho' ) . '</button></form></div>';
	}
}
