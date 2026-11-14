<?php
/**
 * The staff Profile edit screen (REQ-125, REQ-188, REQ-232, REQ-244, REQ-162; prototype A06): the allowlisted fields of a profile
 * with their plain labels from config/profile-fields.php, the long texts (summary, mandate and the four narrative sections) as
 * multi-line boxes that keep paragraph breaks with a character counter, a mandatory reason, the "this resolves the open item"
 * select and term assignment, and a Photo or Logo panel (media library picker, open licence, credit, source). Needs
 * edit_wiw_profiles on the screen and on both actions (a Reviewer does not hold it). The handlers read only allowlisted field
 * names and call ProfileEditor or ProfileImageEditor.
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
use WhoIsWho\Core\Directory\Edit\ProfileImageEditor;
use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Import\Images\ImagePolicy;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Review\Admin\Labels;

/**
 * Profile edit screen.
 */
final class ProfileEditScreen {

	public const SCREEN     = 'wiw-profile-edit';
	public const CAPABILITY = ProfileEditor::EDIT_CAPABILITY;
	public const SAVE       = 'wiw_profile_edit_save';
	public const IMAGE      = 'wiw_profile_image';

	/**
	 * Fields shown as a multi-line box that keeps paragraph breaks (issue 806).
	 */
	public const LONG_TEXT = array( 'summary', 'mandate', 'bio', 'achievement', 'influence', 'legacy' );

	/**
	 * The length limit a string field has when its rule names none (FieldValidator's default).
	 */
	private const DEFAULT_MAX = 300;

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
					},
					true,
					// A detail screen, opened from its list: no menu item.
					'directory',
					ProfilesScreen::SCREEN
				);
				$registry->hook();
				ActionGuard::register( self::SAVE, self::CAPABILITY, array( self::class, 'handle_save' ) );
				ActionGuard::register( self::IMAGE, self::CAPABILITY, array( self::class, 'handle_image' ) );
				ProfilePanels::register_actions();
				add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
			},
			1
		);
	}

	/**
	 * The media library picker and the character counter, on this screen only.
	 *
	 * @param string $hook Admin page hook suffix.
	 */
	public static function enqueue( $hook ): void {
		if ( false === strpos( (string) $hook, self::SCREEN ) || ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- generated for the profile type, listed in config/capabilities.php.
			return;
		}
		if ( current_user_can( 'upload_files' ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'wiw-add-profile', plugins_url( 'assets/admin/directory/add-profile.js', WIW_CORE_FILE ), array( 'media-views' ), WIW_CORE_VERSION, true );
		}
		wp_enqueue_script( 'wiw-char-count', plugins_url( 'assets/admin/directory/char-count.js', WIW_CORE_FILE ), array(), WIW_CORE_VERSION, true );
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
					// A long text keeps its line breaks and blank lines (paragraphs); browsers send CRLF, stored as LF.
					$out[ $key ] = in_array( $key, self::LONG_TEXT, true ) ? str_replace( "\r\n", "\n", $request->text( $name ) ) : $request->string( $name );
					break;
				default:
					$out[ $key ] = $request->string( $name );
			}
		}//end foreach
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
	 * Image handler (capability and nonce already checked): set, change or remove the photo or logo through ProfileImageEditor.
	 *
	 * @param Request $request Request (profile_id, op, image_id, image_licence, image_credit, image_source, reason).
	 */
	public static function handle_image( Request $request ): void {
		$id     = $request->int( 'profile_id' );
		$images = new ProfileImageEditor();
		try {
			if ( 'remove' === $request->string( 'op' ) ) {
				$result = $images->remove( $id, $request->string( 'reason' ), get_current_user_id() );
			} else {
				$result = $images->set(
					$id,
					$request->int( 'image_id' ),
					array(
						'licence'    => $request->string( 'image_licence' ),
						'creator'    => $request->string( 'image_credit' ),
						'source_url' => $request->string( 'image_source' ),
					),
					$request->string( 'reason' ),
					get_current_user_id()
				);
			}
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
		$out .= '<h2>' . esc_html( get_the_title( $id ) ) . '</h2><p>' . esc_html( Labels::entity_type( (string) $state['entity_type'] ) . ' · ' . Labels::state( (string) $state['pub_state'] ) ) . '</p>';
		if ( (int) $state['claimed'] > 0 ) {
			$out .= '<div class="notice notice-info inline"><p>' . esc_html__( 'This profile is claimed. An edit by its assigned manager goes to review before it shows.', 'whoiswho' ) . '</p></div>';
		}
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::SAVE ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::SAVE ) ) . '" /><input type="hidden" name="profile_id" value="' . esc_attr( (string) $id ) . '" />';
		$out .= '<table class="form-table" role="presentation"><tbody>' . $this->fields( $id, (string) $state['entity_type'] ) . '</tbody></table>';
		$out .= $this->terms( $id );
		$out .= '<p><label>' . esc_html__( 'Reason or request reference (required)', 'whoiswho' ) . '<br /><input type="text" name="reason" class="large-text" required="required" value="' . esc_attr( rawurldecode( $request->string( 'reason' ) ) ) . '" /></label></p>';
		$out .= $this->resolves( $id, $request->int( 'resolves' ) );
		$out .= '<p><button class="button button-primary">' . esc_html__( 'Save changes', 'whoiswho' ) . '</button></p></form>';
		return $out . $this->image_panel( $id, (string) $state['entity_type'] ) . ( new ProfilePanels() )->html( $id, $state ) . '</div>';
	}

	/**
	 * The photo (people) or logo (organisations) panel: media library picker, licence, credit, source and reason; a Remove button
	 * when an image is set (issue 806).
	 *
	 * @param int    $id   Profile.
	 * @param string $type Entity type.
	 */
	private function image_panel( int $id, string $type ): string {
		$person  = in_array( $type, array( 'individual', 'entrepreneur' ), true );
		$current = ProfileImageEditor::current( $id );
		$has     = $current['id'] > 0;
		$out     = '<h2 id="wiw-image">' . esc_html( $person ? __( 'Photo', 'whoiswho' ) : __( 'Logo', 'whoiswho' ) ) . '</h2>';
		$out    .= '<p class="description">' . esc_html__( 'Choose a JPEG, PNG or WebP from the media library. Only openly licensed images may be used: the licence, the creator or credit, and the page the image comes from are required.', 'whoiswho' ) . '</p>';
		$out    .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wiw-profile-image-form"><input type="hidden" name="action" value="' . esc_attr( self::IMAGE ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::IMAGE ) ) . '" /><input type="hidden" name="profile_id" value="' . esc_attr( (string) $id ) . '" />';
		$out    .= '<table class="form-table" role="presentation"><tbody>';
		$preview = $has ? wp_get_attachment_image( $current['id'], 'thumbnail', false, array( 'class' => 'wiw-image-preview' ) ) . '<br />' : '';
		$name    = $has ? (string) get_the_title( $current['id'] ) : __( 'Nothing chosen', 'whoiswho' );
		$pick    = $preview . '<input type="number" min="0" id="wiw-f-image_id" name="image_id" value="' . esc_attr( $has ? (string) $current['id'] : '' ) . '" class="small-text" aria-describedby="wiw-image-name" /> <span id="wiw-image-name" class="wiw-add-image-name">' . esc_html( $name ) . '</span> ';
		$pick   .= '<button type="button" class="button wiw-add-image-pick">' . esc_html__( 'Choose from media library', 'whoiswho' ) . '</button>';
		$pick   .= '<p class="description">' . esc_html__( 'The number is the media library item id; the button fills it in.', 'whoiswho' ) . '</p>';
		$out    .= $this->row( 'image_id', __( 'Image', 'whoiswho' ), $pick );
		$select  = '<select id="wiw-f-image_licence" name="image_licence"><option value="">' . esc_html__( 'Choose a licence', 'whoiswho' ) . '</option>';
		foreach ( ImagePolicy::licence_choices() as $licence => $label ) {
			$select .= '<option value="' . esc_attr( $licence ) . '"' . selected( $current['licence'], $licence, false ) . '>' . esc_html( $label ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
		}
		$out .= $this->row( 'image_licence', __( 'Licence', 'whoiswho' ), $select . '</select>' );
		$out .= $this->row( 'image_credit', __( 'Creator or credit (as the licence asks)', 'whoiswho' ), '<input type="text" id="wiw-f-image_credit" name="image_credit" class="regular-text" maxlength="' . esc_attr( (string) ProfileImageEditor::CREDIT_MAX ) . '" value="' . esc_attr( $current['creator'] ) . '" />' );
		$out .= $this->row( 'image_source', __( 'Image source URL', 'whoiswho' ), '<input type="url" id="wiw-f-image_source" name="image_source" class="regular-text" maxlength="2000" value="' . esc_attr( $current['source_url'] ) . '" />' );
		$out .= $this->row( 'image_reason', __( 'Reason or request reference (required)', 'whoiswho' ), '<input type="text" id="wiw-f-image_reason" name="reason" class="regular-text" required="required" />' );
		$out .= '</tbody></table><p><button type="submit" name="op" value="set" class="button button-primary">' . esc_html( $person ? __( 'Save photo', 'whoiswho' ) : __( 'Save logo', 'whoiswho' ) ) . '</button>';
		if ( $has ) {
			$out .= ' <button type="submit" name="op" value="remove" class="button">' . esc_html( $person ? __( 'Remove photo', 'whoiswho' ) : __( 'Remove logo', 'whoiswho' ) ) . '</button>';
		}
		return $out . '</p></form>';
	}

	/**
	 * One form row whose label points at its field.
	 *
	 * @param string $name  Field id suffix.
	 * @param string $label Label.
	 * @param string $input Input markup (already escaped).
	 */
	private function row( string $name, string $label, string $input ): string {
		return '<tr><th scope="row"><label for="wiw-f-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>' . $input . '</td></tr>';
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
		$values    = ( new ProfileEditor() )->current_values( $id );
		$allowlist = new FieldAllowlist();
		$out       = '';
		foreach ( $allowlist->for_type( $type ) as $key => $rule ) {
			$key   = (string) $key;
			$name  = 'f_' . $key;
			$value = $values[ $key ] ?? '';
			$label = $allowlist->label( $type, $key ) . ( ! empty( $rule['required'] ) ? ' *' : '' );
			$attrs = 'id="wiw-f-' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '"';
			if ( 'list' === $rule['type'] ) {
				$input = '<textarea ' . $attrs . ' rows="4" class="large-text">' . esc_textarea( is_array( $value ) ? implode( "\n", $value ) : '' ) . '</textarea><p class="description">' . esc_html__( 'One per line.', 'whoiswho' ) . '</p>';
			} elseif ( in_array( $key, self::LONG_TEXT, true ) ) {
				$input = $this->long_text( $key, $name, $attrs, is_scalar( $value ) ? (string) $value : '', (int) ( $rule['max'] ?? self::DEFAULT_MAX ) );
			} else {
				$input = '<input type="' . ( 'url' === $rule['type'] ? 'url' : 'text' ) . '" ' . $attrs . ' value="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '" class="regular-text" />';
			}
			$out .= '<tr><th scope="row"><label for="wiw-f-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>' . $input . '</td></tr>';
		}
		return $out;
	}

	/**
	 * A multi-line box that keeps paragraph breaks, limited to the field's maximum, with a character counter (issue 806).
	 *
	 * @param string $key   Field key.
	 * @param string $name  Input name.
	 * @param string $attrs The id and name attributes (escaped).
	 * @param string $value Current text.
	 * @param int    $max   Maximum characters.
	 */
	private function long_text( string $key, string $name, string $attrs, string $value, int $max ): string {
		$count = 'wiw-count-' . $name;
		$used  = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
		$rows  = $max >= 6000 ? 10 : 5;
		$out   = '<textarea ' . $attrs . ' rows="' . esc_attr( (string) $rows ) . '" class="large-text" maxlength="' . esc_attr( (string) $max ) . '" data-wiw-count="' . esc_attr( $count ) . '" aria-describedby="' . esc_attr( $count ) . '">' . esc_textarea( $value ) . '</textarea>';
		$out  .= '<p class="description"><span id="' . esc_attr( $count ) . '" class="wiw-char-count" aria-live="polite">' . esc_html( sprintf( /* translators: 1: characters used, 2: the limit. */ __( '%1$d / %2$d characters', 'whoiswho' ), $used, $max ) ) . '</span>';
		if ( in_array( $key, array( 'bio', 'achievement', 'influence', 'legacy' ), true ) ) {
			$out .= ' ' . esc_html__( 'Plain text. Leave a blank line between paragraphs.', 'whoiswho' );
		}
		return $out . '</p>';
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
			$object = get_taxonomy( $taxonomy );
			$name   = $object instanceof \WP_Taxonomy ? (string) $object->labels->name : Labels::humanise( str_replace( 'wiw_', '', $taxonomy ) );
			$out   .= '<p><label>' . esc_html( $name ) . '<br /><input type="hidden" name="terms_present_' . esc_attr( $taxonomy ) . '" value="1" /><select multiple="multiple" name="terms_' . esc_attr( $taxonomy ) . '[]" size="5">';
			foreach ( $terms as $term ) {
				$out .= '<option value="' . esc_attr( (string) $term->term_id ) . '"' . selected( in_array( (int) $term->term_id, $have, true ), true, false ) . '>' . esc_html( $term->name ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
			}
			$out .= '</select></label></p>';
		}//end foreach
		return $out;
	}

	/**
	 * The "this resolves the open item" select: the open corrections on this profile.
	 *
	 * @param int $id       Profile.
	 * @param int $selected Item to preselect (from the Corrections screen's Apply link).
	 */
	private function resolves( int $id, int $selected = 0 ): string {
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
			$out .= '<option value="' . esc_attr( (string) $item['id'] ) . '"' . selected( $selected, (int) $item['id'], false ) . '>' . esc_html( (string) $item['reference'] ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
		}
		return $out . '</select></label></p>';
	}
}
