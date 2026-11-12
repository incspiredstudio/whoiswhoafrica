<?php
/**
 * The Claim and profile manager card on the profile screen (REQ-252, REQ-253, REQ-150): claim status and date with the staff-only
 * checkmark, and, for a person who may review claims and only while the profile is claimed, the control to assign or clear the
 * profile manager. It is an admin meta box saved with the profile; no public template renders any of it.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\Request;

/**
 * Claim card.
 */
final class ClaimCard {

	public const FIELD        = 'manager_user_id';
	public const NONCE_ACTION = 'wiw_assign_manager';
	public const NONCE_FIELD  = '_wiw_assign_manager_nonce';

	/**
	 * Hook the box and the save.
	 */
	public static function hooks(): void {
		add_action( 'add_meta_boxes_' . ProfileType::POST_TYPE, array( self::class, 'add_box' ) );
		add_action( 'save_post_' . ProfileType::POST_TYPE, array( self::class, 'save' ), 12, 1 );
	}

	/**
	 * Add the box.
	 */
	public static function add_box(): void {
		add_meta_box( 'wiw_claim_card', __( 'Claim and profile manager', 'whoiswho' ), array( self::class, 'render' ), ProfileType::POST_TYPE, 'side', 'default' );
	}

	/**
	 * Print the card. Staff only: nothing is printed for anyone else.
	 *
	 * @param \WP_Post $post Profile being edited.
	 */
	public static function render( \WP_Post $post ): void {
		if ( ! current_user_can( ProfileManager::STAFF_CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- generated for the profile type, listed in config/capabilities.php.
			return;
		}
		$state = ( new StateRepository() )->get( $post->ID );
		echo '<div class="wiw-claim-card">';
		if ( null === $state || empty( $state['claimed'] ) ) {
			echo '<p>' . esc_html__( 'Not claimed', 'whoiswho' ) . '</p></div>';
			return;
		}
		echo '<p><span aria-hidden="true">&#10003;</span> <strong>' . esc_html__( 'Claimed', 'whoiswho' ) . '</strong>';
		if ( ! empty( $state['claimed_at'] ) ) {
			/* translators: %s: date the claim was approved. */
			echo '<br />' . esc_html( sprintf( __( 'Approved %s', 'whoiswho' ), substr( (string) $state['claimed_at'], 0, 10 ) ) );
		}
		echo '</p>';
		$manager  = new ProfileManager();
		$assigned = $manager->assigned( $post->ID );
		if ( current_user_can( ClaimDecision::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			self::assign_control( $manager, $assigned );
		} elseif ( null !== $assigned ) {
			$user = get_userdata( $assigned );
			/* translators: %s: staff member's name. */
			echo '<p>' . esc_html( sprintf( __( 'Managed by %s', 'whoiswho' ), $user ? $user->display_name : '' ) ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * The assign control: a select inside the profile form, saved with the profile.
	 *
	 * @param ProfileManager $manager  Service.
	 * @param int|null       $assigned Current manager.
	 */
	private static function assign_control( ProfileManager $manager, ?int $assigned ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		echo '<p><label for="' . esc_attr( self::FIELD ) . '"><strong>' . esc_html__( 'Profile manager', 'whoiswho' ) . '</strong></label><br />';
		echo '<select name="' . esc_attr( self::FIELD ) . '" id="' . esc_attr( self::FIELD ) . '"><option value="0">' . esc_html__( 'Not assigned', 'whoiswho' ) . '</option>';
		foreach ( $manager->staff_choices() as $id => $name ) {
			echo '<option value="' . esc_attr( (string) $id ) . '"' . selected( (int) $assigned, $id, false ) . '>' . esc_html( $name ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
		}
		echo '</select></p>';
	}

	/**
	 * Save the manager with the profile: nonce first, then the service checks the capability, the claim and the user.
	 *
	 * @param int $post_id Profile id.
	 */
	public static function save( int $post_id ): void {
		$request = Request::from_globals();
		if ( ! wp_verify_nonce( $request->string( self::NONCE_FIELD ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		try {
			( new ProfileManager() )->assign( $post_id, $request->int( self::FIELD ), get_current_user_id() );
		} catch ( \DomainException $e ) {
			// Refused (not allowed, not claimed or not staff): nothing changes.
			unset( $e );
		}
	}
}
