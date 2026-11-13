<?php
/**
 * The "Add profile to nominations" form on the Nominations screen (REQ-267, REQ-268). Staff search for an existing profile by
 * name, pick it and a current category, and may add a short note and a staff-only Contact email. There is no free-text nominee
 * name. The one action is guarded by the wiw_manage_awards capability and a nonce, runs the one validator, and calls
 * NominationService::add_profile, which checks the capability again, links the profile and starts the nomination Nominated and
 * Not live (a pending-review profile can be added but never set Live). The Contact email is stored on the nomination and never
 * shown again here: not in the list, not in the result message.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards\Admin;

use WhoIsWho\Core\Awards\CategoryService;
use WhoIsWho\Core\Awards\NominationsSwitch;
use WhoIsWho\Core\Awards\NominationService;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Forms\FormInput;

/**
 * Add profile form.
 */
final class AddProfileForm {

	public const ACTION = 'wiw_nomination_add_profile';
	public const CAP    = 'wiw_manage_awards';

	/**
	 * Nomination service.
	 *
	 * @var NominationService
	 */
	private NominationService $service;

	/**
	 * Picker.
	 *
	 * @var ProfilePicker
	 */
	private ProfilePicker $picker;

	/**
	 * Constructor.
	 *
	 * @param NominationService  $service Nomination service.
	 * @param ProfilePicker|null $picker  Profile picker.
	 */
	public function __construct( NominationService $service, ?ProfilePicker $picker = null ) {
		$this->service = $service;
		$this->picker  = $picker ?? new ProfilePicker();
	}

	/**
	 * Register the guarded action, in the admin only.
	 */
	public static function hooks(): void {
		add_action(
			'init',
			static function (): void {
				if ( ! is_admin() ) {
					return;
				}
				ActionGuard::register(
					self::ACTION,
					self::CAP,
					static function ( Request $request ): void {
						$notice = ( new self( NominationService::for_site() ) )->handle( $request );
						wp_safe_redirect(
							add_query_arg(
								array(
									'page'       => NominationsSwitch::SCREEN,
									'wiw_notice' => rawurlencode( $notice ),
								),
								admin_url( 'admin.php' )
							)
						);
						exit;
					}
				);
			},
			1
		);
	}

	/**
	 * Run the form (capability and nonce already checked by ActionGuard; the service checks the capability again).
	 *
	 * @param Request $request Request.
	 * @return string Message for the person.
	 */
	public function handle( Request $request ): string {
		$input  = new FormInput(
			array(
				'profile_id'    => $request->string( 'profile_id' ),
				'category_id'   => $request->string( 'category_id' ),
				'note'          => $request->string( 'note' ),
				'contact_email' => $request->string( 'contact_email' ),
			)
		);
		$result = ( new AddProfileValidator() )->validate( $input );
		if ( ! $result->is_valid() ) {
			return 'Choose a profile and a category, and check the Contact email.';
		}
		try {
			$this->service->add_profile( (int) $result->value( 'profile_id' ), (int) $result->value( 'category_id' ), $result->value( 'note' ), $result->value( 'contact_email' ) );
		} catch ( \DomainException $refusal ) {
			return $refusal->getMessage();
		} catch ( \RuntimeException $failure ) {
			return $failure->getMessage();
		}
		return 'The profile was added to the nominations. It is Nominated and Not live.';
	}

	/**
	 * The form markup: a search, then the picker and fields when the search found profiles.
	 *
	 * @param string $query Search text from the address.
	 */
	public function html( string $query = '' ): string {
		$out   = '<h2>' . esc_html__( 'Add profile to nominations', 'whoiswho' ) . '</h2>';
		$out  .= '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( NominationsSwitch::SCREEN ) . '" />';
		$out  .= '<label>' . esc_html__( 'Search profiles by name', 'whoiswho' ) . ' <input type="search" name="profile_q" value="' . esc_attr( $query ) . '" maxlength="100" /></label> ';
		$out  .= '<button type="submit" class="button">' . esc_html__( 'Search', 'whoiswho' ) . '</button></form>';
		$found = $this->picker->search( $query );
		if ( '' !== trim( $query ) && array() === $found ) {
			return $out . '<p>' . esc_html__( 'No existing profile matches. Only existing profiles can be nominated.', 'whoiswho' ) . '</p>';
		}
		if ( array() === $found ) {
			return $out;
		}
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		$out .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />';
		$out .= '<p><label>' . esc_html__( 'Profile', 'whoiswho' ) . ' <select name="profile_id">';
		foreach ( $found as $profile ) {
			$label = $profile['name'] . ' (' . $profile['type'] . ', ' . ( 'published_pending' === $profile['state'] ? __( 'pending review', 'whoiswho' ) : __( 'reviewed', 'whoiswho' ) ) . ')';
			$out  .= '<option value="' . esc_attr( (string) $profile['id'] ) . '">' . esc_html( $label ) . '</option>';
		}
		$out .= '</select></label></p>';
		$out .= '<p><label>' . esc_html__( 'Category', 'whoiswho' ) . ' <select name="category_id">';
		foreach ( CategoryService::for_site()->grouped() as $group ) {
			foreach ( $group['categories'] as $category ) {
				$out .= '<option value="' . esc_attr( (string) $category['id'] ) . '">' . esc_html( (string) $category['title'] ) . '</option>';
			}
		}
		$out .= '</select></label></p>';
		$out .= '<p><label>' . esc_html__( 'Note (optional, staff only)', 'whoiswho' ) . '<br /><textarea name="note" rows="3" cols="50" maxlength="1000"></textarea></label></p>';
		$out .= '<p><label>' . esc_html__( 'Contact email (optional, staff only, never shown publicly)', 'whoiswho' ) . ' <input type="email" name="contact_email" maxlength="254" class="regular-text" /></label></p>';
		return $out . '<p><button type="submit" class="button button-primary">' . esc_html__( 'Add to nominations', 'whoiswho' ) . '</button></p></form>';
	}
}
