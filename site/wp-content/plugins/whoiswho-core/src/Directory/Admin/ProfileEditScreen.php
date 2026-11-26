<?php
/**
 * The staff Profile edit screen (REQ-125, REQ-188, REQ-232, REQ-244, REQ-162, REQ-009; prototype A06): the allowlisted fields of a
 * profile with their plain labels from config/profile-fields.php, the long texts (summary, mandate and the four narrative sections)
 * as multi-line boxes that keep paragraph breaks with a character counter, a mandatory reason, the "this resolves the open item"
 * select, the classification (Industry picker, Organisation type, Country and City, see ClassificationPicker; a person's
 * professions stay a list), and a Photo or Logo panel (media library picker, open licence, credit, source). Needs
 * edit_wiw_profiles on the screen and on both actions (a Reviewer does not hold it). The handlers read only allowlisted field
 * names and call ProfileEditor or ProfileImageEditor. The markup uses the staff form kit (Platform\Admin\Ui\Form, REQ-275): card
 * sections, labels above fields, a sticky save bar and the unsaved-changes warning.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Admin;

use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Directory\Edit\EditRefused;
use WhoIsWho\Core\Directory\Edit\ClassificationInput;
use WhoIsWho\Core\Directory\Edit\ProfileEditor;
use WhoIsWho\Core\Directory\Edit\ProfileImageEditor;
use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Translate\OriginalName;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Import\Images\ImagePolicy;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
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
	 * Text fields that the classification pickers replace on the form. They stay in config/profile-fields.php as import inputs.
	 */
	public const CLASSIFIED_FIELDS = array( 'industry', 'sector', 'org_type', 'hq_city', 'field' );

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
		ClassificationPicker::enqueue();
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
			if ( ! $request->has( $name ) || in_array( $key, self::CLASSIFIED_FIELDS, true ) ) {
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
		$classification = ( new ClassificationPicker() )->from_request( $request );
		try {
			$result = ( new ProfileEditor() )->edit( $id, self::changes_from( $request, $type ), $request->string( 'reason' ), get_current_user_id(), $request->int( 'resolves' ), $terms, $classification );
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
	 * The screen markup: the profile's fields in card sections with a sticky save bar, then the photo and the panels (REQ-275).
	 * Field names, validators and save paths are the same as before; only the markup changed.
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
		$out   = '<div class="wrap wiw-staff wiw-staff--form"><h1>' . esc_html__( 'Edit profile', 'whoiswho' ) . '</h1>';
		if ( null === $state || null === get_post( $id ) ) {
			return $out . '<p>' . esc_html__( 'Choose a profile from the Profiles screen.', 'whoiswho' ) . '</p></div>';
		}
		$type = (string) $state['entity_type'];
		$out .= $this->notice( $request );
		$out .= '<header class="wiw-page-head"><h2 class="wiw-page-head__title">' . esc_html( get_the_title( $id ) ) . '</h2><p>' . esc_html( Labels::entity_type( $type ) . ' · ' . Labels::state( (string) $state['pub_state'], (string) ( $state['origin'] ?? '' ) ) ) . '</p>' . $this->original_name( $id ) . '</header>';
		if ( (int) $state['claimed'] > 0 ) {
			$out .= '<div class="notice notice-info inline"><p>' . esc_html__( 'This profile is claimed. An edit by its assigned manager goes to review before it shows.', 'whoiswho' ) . '</p></div>';
		}
		$hidden = '<input type="hidden" name="action" value="' . esc_attr( self::SAVE ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::SAVE ) ) . '" /><input type="hidden" name="profile_id" value="' . esc_attr( (string) $id ) . '" />';
		$fields = $this->fields( $id, $type );
		$out   .= Form::open(
			$hidden,
			array(
				'class'   => 'wiw-profile-edit-form',
				'unsaved' => true,
			)
		);
		$out   .= Form::section(
			__( 'Profile details', 'whoiswho' ),
			$fields[0],
			array(
				'id'          => 'wiw-details',
				'description' => __( 'Fields marked * are required.', 'whoiswho' ),
			)
		);
		if ( '' !== $fields[1] ) {
			$out .= Form::section(
				__( 'Profile sections', 'whoiswho' ),
				$fields[1],
				array(
					'id'          => 'wiw-sections',
					'description' => __( 'Plain text. Leave a blank line between paragraphs. Each section is optional.', 'whoiswho' ),
				)
			);
		}
		$out .= $this->terms( $id, $type );
		$why  = Form::text(
			'reason',
			__( 'Reason or request reference (required)', 'whoiswho' ),
			rawurldecode( $request->string( 'reason' ) ),
			array(
				'id'       => 'wiw-f-reason',
				'class'    => 'large-text',
				'required' => true,
				'help'     => __( 'Every save writes a version and an audit row with this reason.', 'whoiswho' ),
			)
		);
		$out .= Form::section( __( 'Save the change', 'whoiswho' ), $why . $this->resolves( $id, $request->int( 'resolves' ) ), array( 'id' => 'wiw-save' ) );
		$out .= Form::actions( '<button type="submit" class="button button-primary">' . esc_html__( 'Save changes', 'whoiswho' ) . '</button>', array( 'sticky' => true ) ) . '</form>';
		return $out . $this->image_panel( $id, $type ) . ( new ProfilePanels() )->html( $id, $state ) . '</div>';
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
		$hidden  = '<input type="hidden" name="action" value="' . esc_attr( self::IMAGE ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::IMAGE ) ) . '" /><input type="hidden" name="profile_id" value="' . esc_attr( (string) $id ) . '" />';
		$preview = $has ? wp_get_attachment_image( $current['id'], 'thumbnail', false, array( 'class' => 'wiw-image-preview' ) ) : '';
		$name    = $has ? (string) get_the_title( $current['id'] ) : __( 'Nothing chosen', 'whoiswho' );
		$pick    = '<div class="wiw-media-pick">' . $preview . '<input type="number" min="0" id="wiw-f-image_id" name="image_id" value="' . esc_attr( $has ? (string) $current['id'] : '' ) . '" class="small-text wiw-input" aria-describedby="wiw-image-name" /> <span id="wiw-image-name" class="wiw-add-image-name">' . esc_html( $name ) . '</span> ';
		$pick   .= '<button type="button" class="button wiw-add-image-pick">' . esc_html__( 'Choose from media library', 'whoiswho' ) . '</button></div>';
		$body    = Form::row( 'wiw-f-image_id', __( 'Image', 'whoiswho' ), $pick, array( 'help' => __( 'The number is the media library item id; the button fills it in.', 'whoiswho' ) ) );
		$body   .= Form::select(
			'image_licence',
			__( 'Licence', 'whoiswho' ),
			ImagePolicy::licence_choices(),
			(string) $current['licence'],
			array( 'placeholder' => __( 'Choose a licence', 'whoiswho' ) )
		);
		$body   .= '<div class="wiw-field-grid">' . Form::text( 'image_credit', __( 'Creator or credit (as the licence asks)', 'whoiswho' ), (string) $current['creator'], array( 'maxlength' => ProfileImageEditor::CREDIT_MAX ) );
		$body   .= Form::url( 'image_source', __( 'Image source URL', 'whoiswho' ), (string) $current['source_url'], array( 'maxlength' => 2000 ) ) . '</div>';
		$body   .= Form::text(
			'reason',
			__( 'Reason or request reference (required)', 'whoiswho' ),
			'',
			array(
				'id'       => 'wiw-f-image_reason',
				'required' => true,
			)
		);
		$buttons = '<button type="submit" name="op" value="set" class="button button-primary">' . esc_html( $person ? __( 'Save photo', 'whoiswho' ) : __( 'Save logo', 'whoiswho' ) ) . '</button>';
		if ( $has ) {
			$buttons .= ' <button type="submit" name="op" value="remove" class="button">' . esc_html( $person ? __( 'Remove photo', 'whoiswho' ) : __( 'Remove logo', 'whoiswho' ) ) . '</button>';
		}
		$form = Form::open(
			$hidden,
			array(
				'class'   => 'wiw-profile-image-form',
				'unsaved' => true,
			)
		) . $body . Form::actions( $buttons ) . '</form>';
		return Form::section(
			$person ? __( 'Photo', 'whoiswho' ) : __( 'Logo', 'whoiswho' ),
			$form,
			array(
				'id'          => 'wiw-image',
				'description' => __( 'Choose a JPEG, PNG or WebP from the media library. Only openly licensed images may be used: the licence, the creator or credit, and the page the image comes from are required.', 'whoiswho' ),
			)
		);
	}


	/**
	 * The original-language name a translated profile keeps (REQ-276): shown to staff only, read-only. Search matches it; the
	 * public page never shows it.
	 *
	 * @param int $id Profile.
	 */
	private function original_name( int $id ): string {
		$original = OriginalName::get( $id );
		if ( null === $original ) {
			return '';
		}
		$lang = $original['lang'];
		/* translators: %s: language name, for example Arabic. */
		$label = sprintf( __( 'Original name (%s):', 'whoiswho' ), OriginalName::language_name( $lang ) );
		return '<p class="wiw-original-name">' . esc_html( $label ) . ' <span lang="' . esc_attr( $lang ) . '" dir="auto">' . esc_html( $original['name'] ) . '</span> <small>' . esc_html__( 'Staff and search only; not shown on the public page.', 'whoiswho' ) . '</small></p>';
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
	 * The allowlisted fields: the details, and the four narrative sections apart.
	 *
	 * @param int    $id   Profile.
	 * @param string $type Entity type.
	 * @return array{0: string, 1: string} Details markup and sections markup.
	 */
	private function fields( int $id, string $type ): array {
		$values    = ( new ProfileEditor() )->current_values( $id );
		$allowlist = new FieldAllowlist();
		$details   = '';
		$sections  = '';
		foreach ( $allowlist->for_type( $type ) as $key => $rule ) {
			$key = (string) $key;
			if ( in_array( $key, self::CLASSIFIED_FIELDS, true ) ) {
				continue;
			}
			$name  = 'f_' . $key;
			$value = $values[ $key ] ?? '';
			$label = $allowlist->label( $type, $key ) . ( ! empty( $rule['required'] ) ? ' *' : '' );
			$args  = array( 'id' => 'wiw-f-' . $key );
			if ( 'list' === $rule['type'] ) {
				$details .= Form::textarea( $name, $label, is_array( $value ) ? implode( "\n", $value ) : '', array_merge( $args, array( 'help' => __( 'One per line.', 'whoiswho' ) ) ) );
			} elseif ( in_array( $key, self::LONG_TEXT, true ) ) {
				$max   = (int) ( $rule['max'] ?? self::DEFAULT_MAX );
				$story = in_array( $key, array( 'bio', 'achievement', 'influence', 'legacy' ), true );
				$extra = array(
					'rows'      => $max >= 6000 ? 10 : 5,
					'maxlength' => $max,
					'counter'   => true,
				);
				if ( $story ) {
					$extra['counter_note'] = __( 'Plain text. Leave a blank line between paragraphs.', 'whoiswho' );
				}
				$box = Form::textarea( $name, $label, is_scalar( $value ) ? (string) $value : '', array_merge( $args, $extra ) );
				if ( $story ) {
					$sections .= $box;
				} else {
					$details .= $box;
				}
			} else {
				$text     = is_scalar( $value ) ? (string) $value : '';
				$details .= 'url' === $rule['type'] ? Form::url( $name, $label, $text, $args ) : Form::text( $name, $label, $text, $args );
			}//end if
		}//end foreach
		return array( $details, $sections );
	}


	/**
	 * The classification (Industry picker, Organisation type, Country and City) and, for people, the professions list. Shown only to
	 * a user who may assign terms.
	 *
	 * @param int    $id   Profile.
	 * @param string $type Entity type.
	 */
	private function terms( int $id, string $type ): string {
		if ( ! current_user_can( 'assign_terms' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return '';
		}
		$body = '';
		foreach ( ( new ClassificationPicker() )->rows( ( new ClassificationInput() )->current( $id ), $type ) as $row ) {
			$body .= Form::row( 'wiw-f-' . $row['name'], $row['label'], $row['html'] );
		}
		if ( ! in_array( $type, ClassificationInput::ORG_ENTITY_TYPES, true ) ) {
			$body .= $this->professions( $id );
		}
		return Form::section(
			__( 'Classification', 'whoiswho' ),
			$body,
			array(
				'id'          => 'wiw-classification',
				'description' => __( 'Where the profile sits in the directory: its breadcrumb is Country, Sector, Industry. Other industries shows only this profile\'s own.', 'whoiswho' ),
			)
		);
	}

	/**
	 * A person's professions: a multi-select of the Professions list.
	 *
	 * @param int $id Profile.
	 */
	private function professions( int $id ): string {
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomies::PROFESSION,
				'hide_empty' => false,
				'orderby'    => 'name',
				'number'     => 500,
			)
		);
		if ( ! is_array( $terms ) || array() === $terms ) {
			return '';
		}
		$have = wp_get_object_terms( $id, Taxonomies::PROFESSION, array( 'fields' => 'ids' ) );
		$have = is_array( $have ) ? array_map( 'intval', $have ) : array();
		$html = '<input type="hidden" name="terms_present_' . esc_attr( Taxonomies::PROFESSION ) . '" value="1" /><select multiple="multiple" id="wiw-f-professions" name="terms_' . esc_attr( Taxonomies::PROFESSION ) . '[]" size="5" class="wiw-input wiw-select">';
		foreach ( $terms as $term ) {
			$html .= '<option value="' . esc_attr( (string) $term->term_id ) . '"' . selected( in_array( (int) $term->term_id, $have, true ), true, false ) . '>' . esc_html( $term->name ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
		}
		return Form::row( 'wiw-f-professions', __( 'Professions', 'whoiswho' ), $html . '</select>' );
	}

	/**
	 * The "this resolves the open item" select: the open corrections on this profile.
	 *
	 * @param int $id       Profile.
	 * @param int $selected Item to preselect (from the Corrections screen's Apply link).
	 */
	private function resolves( int $id, int $selected = 0 ): string {
		$open = array( '0' => __( 'None', 'whoiswho' ) );
		foreach ( ( new SupportItems() )->list_by_types( array( ItemType::CORRECTION ), '', 200 ) as $item ) {
			if ( (int) $item['profile_id'] === $id && ! ItemStatus::is_closed( (string) $item['status'] ) ) {
				$open[ (string) $item['id'] ] = (string) $item['reference'];
			}
		}
		if ( 1 === count( $open ) ) {
			return '';
		}
		return Form::select( 'resolves', __( 'This resolves the open item', 'whoiswho' ), $open, (string) $selected, array( 'id' => 'wiw-f-resolves' ) );
	}
}
