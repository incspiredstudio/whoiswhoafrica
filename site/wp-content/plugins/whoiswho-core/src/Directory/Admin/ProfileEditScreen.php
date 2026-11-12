<?php
/**
 * The staff Profile edit screen (REQ-125, REQ-188, REQ-232, REQ-244; prototype A06): the allowlisted fields of a profile, a
 * mandatory reason, the "this resolves the open item" select and term assignment. Needs edit_wiw_profiles on the screen and on
 * the save action (a Reviewer does not hold it). The handler reads only allowlisted field names and calls ProfileEditor.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Admin;

use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Directory\Edit\EditRefused;
use WhoIsWho\Core\Directory\Edit\ProfileEditor;
use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;

/**
 * Profile edit screen.
 */
final class ProfileEditScreen {

	public const SCREEN     = 'wiw-profile-edit';
	public const CAPABILITY = ProfileEditor::EDIT_CAPABILITY;
	public const SAVE       = 'wiw_profile_edit_save';

	/**
	 * Hook registration into the admin.
	 */
	public static function hooks(): void {
		add_action(
			'init',
			static function (): void {
				if ( ! is_admin() ) {
					return;
				}
				$registry = ScreenRegistry::shared();
				$registry->register(
					self::SCREEN,
					__( 'Edit profile', 'whoiswho' ),
					self::CAPABILITY,
					static function (): void {
						echo ( new self() )->html( Request::from_globals() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
					}
				);
				$registry->hook();
				ActionGuard::register( self::SAVE, self::CAPABILITY, array( self::class, 'handle_save' ) );
				ProfilePanels::register_actions();
			},
			1
		);
	}

	/**
	 * The link to the screen for a profile.
	 *
	 * @param int $profile_id Profile.
	 */
	public static function url( int $profile_id ): string {
		return add_query_arg(
			array(
				'page'       => self::SCREEN,
				'profile_id' => $profile_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * The changes a request asks for: only allowlisted fields of the profile's type are read.
	 *
	 * @param Request $request Request.
	 * @param string  $type    Entity type.
	 * @return array<string, mixed>
	 */
	public static function changes_from( Request $request, string $type ): array {
		$out = array();
		foreach ( ( new FieldAllowlist() )->for_type( $type ) as $key => $rule ) {
			$name = 'f_' . $key;
			if ( ! $request->has( $name ) ) {
				continue;
			}
			switch ( $rule['type'] ) {
				case 'list':
					$lines       = preg_split( '/\R/', $request->text( $name ) );
					$out[ $key ] = array_values( array_filter( array_map( 'trim', is_array( $lines ) ? $lines : array() ), static fn ( $line ): bool => '' !== $line ) );
					break;
				case 'int':
					$out[ $key ] = '' === $request->string( $name ) ? null : $request->int( $name );
					break;
				case 'string':
					$out[ $key ] = 'summary' === $key || 'bio' === $key || 'mandate' === $key ? $request->text( $name ) : $request->string( $name );
					break;
				default:
					$out[ $key ] = $request->string( $name );
			}
		}
		return $out;
	}

	/**
	 * Save handler (capability and nonce already checked).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_save( Request $request ): void {
		$id    = $request->int( 'profile_id' );
		$type  = (string) ( ( new StateRepository() )->get( $id )['entity_type'] ?? '' );
		$terms = array();
		foreach ( Taxonomies::all() as $taxonomy ) {
			if ( $request->has( 'terms_present_' . $taxonomy ) ) {
				$terms[ $taxonomy ] = $request->ints( 'terms_' . $taxonomy );
			}
		}
		try {
			$result = ( new ProfileEditor() )->edit( $id, self::changes_from( $request, $type ), $request->string( 'reason' ), get_current_user_id(), $request->int( 'resolves' ), $terms );
			self::back( $id, $result['applied'] ? 'saved' : 'pending' );
		} catch ( EditRefused $e ) {
			self::back( $id, 'refused', $e->getMessage() );
		}
	}

	/**
	 * Redirect back to the screen.
	 *
	 * @param int    $profile_id Profile.
	 * @param string $result     Result word.
	 * @param string $why        A refusal message fit to show.
	 */
	private static function back( int $profile_id, string $result, string $why = '' ): void {
		$url = add_query_arg( 'result', $result, self::url( $profile_id ) );
		wp_safe_redirect( '' === $why ? $url : add_query_arg( 'why', rawurlencode( $why ), $url ) );
		exit;
	}

	/**
	 * The screen markup.
	 *
	 * @param Request $request Request (profile_id, result, why).
	 * @throws \DomainException Without the capability.
	 */
	public function html( Request $request ): string {
		if ( ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- generated for the profile type, listed in config/capabilities.php.
			throw new \DomainException( 'Not allowed to edit profiles.' );
		}
		$id    = $request->int( 'profile_id' );
		$state = $id > 0 ? ( new StateRepository() )->get( $id ) : null;
		$out   = '<div class="wrap"><h1>' . esc_html__( 'Edit profile', 'whoiswho' ) . '</h1>';
		if ( null === $state || null === get_post( $id ) ) {
			return $out . '<p>' . esc_html__( 'Choose a profile from the Profiles screen.', 'whoiswho' ) . '</p></div>';
		}
		$out .= $this->notice( $request );
		$out .= '<h2>' . esc_html( get_the_title( $id ) ) . '</h2><p>' . esc_html( (string) $state['entity_type'] . ' / ' . (string) $state['pub_state'] ) . '</p>';
		if ( (int) $state['claimed'] > 0 ) {
			$out .= '<div class="notice notice-info inline"><p>' . esc_html__( 'This profile is claimed. An edit by its assigned manager goes to review before it shows.', 'whoiswho' ) . '</p></div>';
		}
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::SAVE ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::SAVE ) ) . '" /><input type="hidden" name="profile_id" value="' . esc_attr( (string) $id ) . '" />';
		$out .= '<table class="form-table" role="presentation"><tbody>' . $this->fields( $id, (string) $state['entity_type'] ) . '</tbody></table>';
		$out .= $this->terms( $id );
		$out .= '<p><label>' . esc_html__( 'Reason or request reference (required)', 'whoiswho' ) . '<br /><input type="text" name="reason" class="large-text" required="required" /></label></p>';
		$out .= $this->resolves( $id );
		return $out . '<p><button class="button button-primary">' . esc_html__( 'Save changes', 'whoiswho' ) . '</button></p></form>' . ( new ProfilePanels() )->html( $id, $state ) . '</div>';
	}

	/**
	 * The result notice.
	 *
	 * @param Request $request Request.
	 */
	private function notice( Request $request ): string {
		$result = $request->string( 'result' );
		if ( 'refused' === $result ) {
			return '<div class="notice notice-error"><p>' . esc_html( rawurldecode( $request->string( 'why' ) ) ) . '</p></div>';
		}
		$words = array(
			'saved'   => __( 'Saved. A version and an audit row were written.', 'whoiswho' ),
			'pending' => __( 'Sent to review as a pending revision.', 'whoiswho' ),
		);
		return isset( $words[ $result ] ) ? '<div class="notice notice-success"><p>' . esc_html( $words[ $result ] ) . '</p></div>' : '';
	}

	/**
	 * One row per allowlisted field.
	 *
	 * @param int    $id   Profile.
	 * @param string $type Entity type.
	 */
	private function fields( int $id, string $type ): string {
		$values = ( new ProfileEditor() )->current_values( $id );
		$out    = '';
		foreach ( ( new FieldAllowlist() )->for_type( $type ) as $key => $rule ) {
			$name  = 'f_' . $key;
			$value = $values[ $key ] ?? '';
			$label = ucfirst( str_replace( '_', ' ', (string) $key ) ) . ( ! empty( $rule['required'] ) ? ' *' : '' );
			if ( 'list' === $rule['type'] ) {
				$input = '<textarea name="' . esc_attr( $name ) . '" rows="4" class="large-text">' . esc_textarea( is_array( $value ) ? implode( "\n", $value ) : '' ) . '</textarea><p class="description">' . esc_html__( 'One per line.', 'whoiswho' ) . '</p>';
			} elseif ( in_array( $key, array( 'bio', 'summary', 'mandate' ), true ) ) {
				$input = '<textarea name="' . esc_attr( $name ) . '" rows="6" class="large-text">' . esc_textarea( (string) $value ) . '</textarea>';
			} else {
				$input = '<input type="' . ( 'url' === $rule['type'] ? 'url' : 'text' ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '" class="regular-text" />';
			}
			$out .= '<tr><th scope="row"><label>' . esc_html( $label ) . '</label></th><td>' . $input . '</td></tr>';
		}
		return $out;
	}

	/**
	 * Term selects, shown only to a user who may assign terms.
	 *
	 * @param int $id Profile.
	 */
	private function terms( int $id ): string {
		if ( ! current_user_can( 'assign_terms' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return '';
		}
		$out = '<h3>' . esc_html__( 'Lists', 'whoiswho' ) . '</h3>';
		foreach ( Taxonomies::all() as $taxonomy ) {
			$have  = wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'ids' ) );
			$have  = is_array( $have ) ? array_map( 'intval', $have ) : array();
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 500,
				)
			);
			if ( ! is_array( $terms ) || array() === $terms ) {
				continue;
			}
			$out .= '<p><label>' . esc_html( ucfirst( str_replace( array( 'wiw_', '_' ), array( '', ' ' ), $taxonomy ) ) ) . '<br /><input type="hidden" name="terms_present_' . esc_attr( $taxonomy ) . '" value="1" /><select multiple="multiple" name="terms_' . esc_attr( $taxonomy ) . '[]" size="5">';
			foreach ( $terms as $term ) {
				$out .= '<option value="' . esc_attr( (string) $term->term_id ) . '"' . selected( in_array( (int) $term->term_id, $have, true ), true, false ) . '>' . esc_html( $term->name ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
			}
			$out .= '</select></label></p>';
		}
		return $out;
	}

	/**
	 * The "this resolves the open item" select: the open corrections on this profile.
	 *
	 * @param int $id Profile.
	 */
	private function resolves( int $id ): string {
		$open = array();
		foreach ( ( new SupportItems() )->list_by_types( array( ItemType::CORRECTION ), '', 200 ) as $item ) {
			if ( (int) $item['profile_id'] === $id && ! ItemStatus::is_closed( (string) $item['status'] ) ) {
				$open[] = $item;
			}
		}
		if ( array() === $open ) {
			return '';
		}
		$out = '<p><label>' . esc_html__( 'This resolves the open item', 'whoiswho' ) . '<br /><select name="resolves"><option value="0">' . esc_html__( 'None', 'whoiswho' ) . '</option>';
		foreach ( $open as $item ) {
			$out .= '<option value="' . esc_attr( (string) $item['id'] ) . '">' . esc_html( (string) $item['reference'] ) . '</option>';
		}
		return $out . '</select></label></p>';
	}
}
