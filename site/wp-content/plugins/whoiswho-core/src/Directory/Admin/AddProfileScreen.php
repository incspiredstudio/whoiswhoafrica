<?php
/**
 * The staff Add profile screen in the Directory menu (REQ-209, REQ-208, REQ-145, REQ-244, REQ-267). A staff member with
 * wiw_add_profile picks the entity type, then fills the fields of that type (taken from the allowlist in
 * config/profile-fields.php), the country, the four narrative sections, an optional photo or logo from the media library with its
 * open licence, credit and source, and at least one source URL. Before saving, possible duplicates are listed with links, and the
 * profile is only created when none is found or the staff member ticks "create anyway". The new profile starts Imported not public,
 * in the review queue. From the Nominations screen, "Create profile" opens this screen with the name filled in and comes back to
 * the Nominations screen after saving.
 *
 * The screen and its one action need wiw_add_profile (registry and ActionGuard), the handler reads values only through Request and
 * the one validator, and a submission that cannot be saved yet is kept for an hour in a transient tied to the staff member, so the
 * form comes back filled in.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Admin;

use WhoIsWho\Core\Awards\NominationsSwitch;
use WhoIsWho\Core\Directory\Add\AddProfileFields;
use WhoIsWho\Core\Directory\Add\AddProfileService;
use WhoIsWho\Core\Directory\Add\AddProfileValidator;
use WhoIsWho\Core\Directory\Add\DuplicateFinder;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Import\Images\ImagePolicy;
use WhoIsWho\Core\Import\Mapping\TermMapper;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Forms\FormInput;

/**
 * Add profile screen.
 */
final class AddProfileScreen {

	public const SCREEN     = 'wiw-profile-add';
	public const CAPABILITY = AddProfileService::CAPABILITY;
	public const ACTION     = 'wiw_profile_add';

	/**
	 * Where a "return" value may send the staff member after saving.
	 */
	public const RETURN_NOMINATIONS = 'nominations';

	/**
	 * How long an unsaved submission is kept.
	 */
	private const DRAFT_TTL = HOUR_IN_SECONDS;

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
					__( 'Add profile', 'whoiswho' ),
					self::CAPABILITY,
					static function (): void {
						echo ( new self() )->html( Request::from_globals() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
					},
					false,
					'directory'
				);
				$registry->hook();
				ActionGuard::register( self::ACTION, self::CAPABILITY, array( self::class, 'handle' ) );
				add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
			},
			1
		);
	}

	/**
	 * The media library picker, on this screen only.
	 *
	 * @param string $hook Admin page hook suffix.
	 */
	public static function enqueue( $hook ): void {
		if ( false === strpos( (string) $hook, self::SCREEN ) || ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'wiw-add-profile', plugins_url( 'assets/admin/directory/add-profile.js', WIW_CORE_FILE ), array( 'media-views' ), WIW_CORE_VERSION, true );
	}

	/**
	 * The link to the screen.
	 *
	 * @param string $name   Name to fill in.
	 * @param string $back Where to go after saving (RETURN_NOMINATIONS or empty).
	 */
	public static function url( string $name = '', string $back = '' ): string {
		$args = array( 'page' => self::SCREEN );
		if ( '' !== $name ) {
			$args['name'] = rawurlencode( $name );
		}
		if ( self::RETURN_NOMINATIONS === $back ) {
			$args['return'] = $back;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Save handler (capability and nonce already checked by ActionGuard).
	 *
	 * @param Request $request Request.
	 */
	public static function handle( Request $request ): void {
		$result = ( new self() )->process( $request );
		$back   = self::RETURN_NOMINATIONS === $request->string( 'return' ) ? self::RETURN_NOMINATIONS : '';
		if ( 'created' === $result['status'] ) {
			wp_safe_redirect( self::after_create_url( $result, $back, (string) ( $result['values']['name'] ?? '' ) ) );
			exit;
		}
		$token = wp_generate_password( 20, false, false );
		set_transient( self::draft_key( $token ), $result, self::DRAFT_TTL );
		$args = array(
			'page'  => self::SCREEN,
			'draft' => $token,
		);
		if ( '' !== $back ) {
			$args['return'] = $back;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Where to go after a profile was created: back to the Nominations screen with the name searched, or this screen with a
	 * confirmation.
	 *
	 * @param array<string, mixed> $result Result of process().
	 * @param string               $back Return target.
	 * @param string               $name   Name of the new profile.
	 */
	public static function after_create_url( array $result, string $back, string $name ): string {
		if ( self::RETURN_NOMINATIONS === $back ) {
			return add_query_arg(
				array(
					'page'       => NominationsSwitch::SCREEN,
					'profile_q'  => rawurlencode( $name ),
					'wiw_notice' => rawurlencode( 'The profile was created and is in the review queue (not public). Choose it below to add it to the nominations.' ),
				),
				admin_url( 'admin.php' )
			);
		}
		return add_query_arg(
			array(
				'page'    => self::SCREEN,
				'created' => (int) ( $result['profile_id'] ?? 0 ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Validate, check for duplicates and create. Capability and nonce are checked by ActionGuard; the service checks the capability
	 * again.
	 *
	 * @param Request $request Request.
	 * @return array{status: string, errors: array<string, string>, matches: array<int, array<string, mixed>>, profile_id: int, message: string, values: array<string, string>}
	 */
	public function process( Request $request ): array {
		$type   = $request->string( 'entity_type' );
		$form   = new AddProfileFields();
		$fields = $form->for_type( $type );
		$raw    = array(
			'entity_type'   => $type,
			'name'          => $request->string( 'name' ),
			'country'       => $request->string( 'country' ),
			'sources'       => $request->text( 'sources' ),
			'wikidata_qid'  => strtoupper( $request->string( 'wikidata_qid' ) ),
			'rc_number'     => $request->string( 'rc_number' ),
			'image_id'      => $request->string( 'image_id' ),
			'image_licence' => $request->string( 'image_licence' ),
			'image_credit'  => $request->string( 'image_credit' ),
			'image_source'  => $request->string( 'image_source' ),
		);
		foreach ( $fields as $key => $rule ) {
			$raw[ 'f_' . $key ] = $rule['long'] ? $request->text( 'f_' . $key ) : $request->string( 'f_' . $key );
		}
		$result = ( new AddProfileValidator( $type, $form ) )->validate( new FormInput( $raw ) );
		$values = $result->values();
		if ( ! $result->is_valid() ) {
			return self::result( 'invalid', $values, $result->errors() );
		}
		$profile = array( 'name' => $values['name'] );
		foreach ( $fields as $key => $rule ) {
			$value = $values[ 'f_' . $key ];
			if ( '' !== $value ) {
				$profile[ $key ] = 'list' === $rule['type'] ? AddProfileValidator::lines( $value ) : $value;
			}
		}
		$ids     = array_filter(
			array(
				'wikidata_qid' => $values['wikidata_qid'],
				'rc_number'    => $values['rc_number'],
			)
		);
		$service = new AddProfileService();
		if ( $service->is_suppressed( $values['name'], $type, $ids ) ) {
			return self::result( 'refused', $values, array(), array(), 0, __( 'This name is on the suppression list, so it cannot be added. Ask an Administrator.', 'whoiswho' ) );
		}
		$override = '1' === $request->string( 'create_anyway' );
		if ( ! $override ) {
			$matches = ( new DuplicateFinder() )->find( $values['name'], $type, $ids );
			if ( array() !== $matches ) {
				return self::result( 'duplicates', $values, array(), $matches );
			}
		}
		try {
			$id = $service->create(
				$type,
				$profile,
				AddProfileValidator::lines( $values['sources'] ),
				(int) $values['country'],
				$ids,
				array(
					'id'      => (int) $values['image_id'],
					'licence' => $values['image_licence'],
					'credit'  => $values['image_credit'],
					'source'  => $values['image_source'],
				),
				$override
			);
		} catch ( \DomainException $refusal ) {
			return self::result( 'refused', $values, array(), array(), 0, $refusal->getMessage() );
		}
		return self::result( 'created', $values, array(), array(), $id );
	}

	/**
	 * A process() result.
	 *
	 * @param string                           $status     invalid, refused, duplicates or created.
	 * @param array<string, string>            $values     Cleaned values.
	 * @param array<string, string>            $errors     Field => code.
	 * @param array<int, array<string, mixed>> $matches    Possible duplicates.
	 * @param int                              $profile_id Created profile.
	 * @param string                           $message    Message for the person.
	 * @return array{status: string, errors: array<string, string>, matches: array<int, array<string, mixed>>, profile_id: int, message: string, values: array<string, string>}
	 */
	private static function result( string $status, array $values, array $errors = array(), array $matches = array(), int $profile_id = 0, string $message = '' ): array {
		return array(
			'status'     => $status,
			'errors'     => $errors,
			'matches'    => $matches,
			'profile_id' => $profile_id,
			'message'    => $message,
			'values'     => $values,
		);
	}

	/**
	 * The transient key of a draft: per staff member, so one person never sees another's draft.
	 *
	 * @param string $token Token.
	 */
	private static function draft_key( string $token ): string {
		return 'wiw_addp_' . get_current_user_id() . '_' . substr( (string) preg_replace( '/[^A-Za-z0-9]/', '', $token ), 0, 20 );
	}

	/**
	 * The screen markup.
	 *
	 * @param Request $request Request (entity_type, name, return, draft, created).
	 * @throws \DomainException Without the capability.
	 */
	public function html( Request $request ): string {
		if ( ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'Not allowed to add profiles.' );
		}
		$out     = '<div class="wrap wiw-add-profile"><h1>' . esc_html__( 'Add profile', 'whoiswho' ) . '</h1>';
		$created = $request->int( 'created' );
		if ( $created > 0 ) {
			$out .= '<div class="notice notice-success"><p>' . esc_html__( 'The profile was created. It is in the review queue and is not public until a reviewer approves it.', 'whoiswho' ) . ' <a href="' . esc_url( ProfileEditScreen::url( $created ) ) . '">' . esc_html__( 'Open the profile', 'whoiswho' ) . '</a></p></div>';
		}
		$draft = array();
		if ( '' !== $request->string( 'draft' ) ) {
			$stored = get_transient( self::draft_key( $request->string( 'draft' ) ) );
			$draft  = is_array( $stored ) ? $stored : array();
		}
		$values = isset( $draft['values'] ) && is_array( $draft['values'] ) ? $draft['values'] : array();
		$type   = (string) ( $values['entity_type'] ?? $request->string( 'entity_type' ) );
		$name   = (string) ( $values['name'] ?? rawurldecode( $request->string( 'name' ) ) );
		$back   = self::RETURN_NOMINATIONS === $request->string( 'return' ) ? self::RETURN_NOMINATIONS : '';
		$form   = new AddProfileFields();
		if ( ! $form->is_type( $type ) ) {
			return $out . $this->type_form( $form, $name, $back ) . '</div>';
		}
		$out .= $this->draft_notice( $draft );
		return $out . $this->main_form( $form, $type, $name, $back, $values, (array) ( $draft['errors'] ?? array() ), ! empty( $draft['matches'] ) ) . '</div>';
	}

	/**
	 * Step one: choose the entity type (a GET form, so the choice is in the address).
	 *
	 * @param AddProfileFields $form   Form fields.
	 * @param string           $name   Name to carry.
	 * @param string           $back Return target.
	 */
	private function type_form( AddProfileFields $form, string $name, string $back ): string {
		$out  = '<p>' . esc_html__( 'Choose what you are adding. The form then shows the fields for that type.', 'whoiswho' ) . '</p>';
		$out .= '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" />';
		$out .= '<input type="hidden" name="name" value="' . esc_attr( $name ) . '" />';
		if ( '' !== $back ) {
			$out .= '<input type="hidden" name="return" value="' . esc_attr( $back ) . '" />';
		}
		$out .= '<p><label for="wiw-add-type">' . esc_html__( 'Type', 'whoiswho' ) . '</label><br /><select id="wiw-add-type" name="entity_type">';
		foreach ( $form->types() as $type ) {
			$out .= '<option value="' . esc_attr( $type ) . '">' . esc_html( self::type_label( $type ) ) . '</option>';
		}
		return $out . '</select></p><p><button type="submit" class="button button-primary">' . esc_html__( 'Continue', 'whoiswho' ) . '</button></p></form>';
	}

	/**
	 * The notice for a submission that came back: errors, a refusal or possible duplicates.
	 *
	 * @param array<string, mixed> $draft Draft.
	 */
	private function draft_notice( array $draft ): string {
		$status = (string) ( $draft['status'] ?? '' );
		if ( 'invalid' === $status ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'Some fields need attention. They are marked below.', 'whoiswho' ) . '</p></div>';
		}
		if ( 'refused' === $status ) {
			return '<div class="notice notice-error"><p>' . esc_html( (string) ( $draft['message'] ?? '' ) ) . '</p></div>';
		}
		if ( 'duplicates' !== $status ) {
			return '';
		}
		$out = '<div class="notice notice-warning wiw-add-duplicates"><p><strong>' . esc_html__( 'This may already be in the directory.', 'whoiswho' ) . '</strong> ' . esc_html__( 'Open the matches to check. If none of them is the same, tick "Create anyway" below and save again.', 'whoiswho' ) . '</p><ul>';
		foreach ( (array) ( $draft['matches'] ?? array() ) as $match ) {
			$basis = 'name' === ( $match['basis'] ?? '' ) ? __( 'similar name', 'whoiswho' ) : __( 'same identifier', 'whoiswho' );
			$out  .= '<li><a href="' . esc_url( ProfileEditScreen::url( (int) $match['id'] ) ) . '" target="_blank" rel="noopener">' . esc_html( (string) $match['name'] ) . '</a> (' . esc_html( self::type_label( (string) $match['type'] ) . ', ' . self::state_label( (string) $match['state'] ) . ', ' . $basis ) . ')</li>';
		}
		return $out . '</ul></div>';
	}

	/**
	 * Step two: the form for one type.
	 *
	 * @param AddProfileFields      $form       Form fields.
	 * @param string                $type       Entity type.
	 * @param string                $name       Name.
	 * @param string                $back     Return target.
	 * @param array<string, string> $values     Values to fill in.
	 * @param array<string, string> $errors     Field => code.
	 * @param bool                  $duplicates Whether possible duplicates were found.
	 */
	private function main_form( AddProfileFields $form, string $type, string $name, string $back, array $values, array $errors, bool $duplicates ): string {
		$value = static fn ( string $key ): string => (string) ( $values[ $key ] ?? '' );
		$out   = '<p>' . esc_html( sprintf( /* translators: %s: entity type. */ __( 'Type: %s.', 'whoiswho' ), self::type_label( $type ) ) ) . ' <a href="' . esc_url( self::url( $name, $back ) ) . '">' . esc_html__( 'Change type', 'whoiswho' ) . '</a></p>';
		$out  .= '<p>' . esc_html__( 'Fields marked * are required. The new profile goes to the review queue and is not public until a reviewer approves it.', 'whoiswho' ) . '</p>';
		$out  .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wiw-add-profile-form">';
		$out  .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />';
		$out  .= '<input type="hidden" name="entity_type" value="' . esc_attr( $type ) . '" />';
		if ( '' !== $back ) {
			$out .= '<input type="hidden" name="return" value="' . esc_attr( $back ) . '" />';
		}
		$out .= '<table class="form-table" role="presentation"><tbody>';
		$out .= $this->row( 'name', __( 'Name', 'whoiswho' ) . ' *', '<input type="text" id="wiw-f-name" name="name" class="regular-text" maxlength="200" required="required" value="' . esc_attr( $name ) . '" />', $errors );
		$out .= $this->row( 'country', __( 'Country', 'whoiswho' ) . ' *', $this->term_select( 'country', $this->countries(), $value( 'country' ), true ), $errors );
		foreach ( $form->for_type( $type ) as $key => $rule ) {
			if ( in_array( $key, AddProfileFields::SECTIONS, true ) ) {
				continue;
			}
			$out .= $this->row( 'f_' . $key, self::field_label( $key ) . ( $rule['required'] ? ' *' : '' ), $this->input( $key, $rule, $value( 'f_' . $key ) ), $errors );
		}
		$out .= $this->row( 'wikidata_qid', __( 'Wikidata id (optional, for example Q42)', 'whoiswho' ), '<input type="text" id="wiw-f-wikidata_qid" name="wikidata_qid" maxlength="20" value="' . esc_attr( $value( 'wikidata_qid' ) ) . '" />', $errors );
		if ( in_array( $type, AddProfileValidator::ORG_TYPES, true ) ) {
			$out .= $this->row( 'rc_number', __( 'Registration number (optional)', 'whoiswho' ), '<input type="text" id="wiw-f-rc_number" name="rc_number" maxlength="40" value="' . esc_attr( $value( 'rc_number' ) ) . '" />', $errors );
		}
		$out .= '</tbody></table>';
		$out .= '<h2>' . esc_html__( 'Profile sections', 'whoiswho' ) . '</h2><p class="description">' . esc_html__( 'Plain text. Leave a blank line between paragraphs. Each section is optional.', 'whoiswho' ) . '</p><table class="form-table" role="presentation"><tbody>';
		foreach ( $form->for_type( $type ) as $key => $rule ) {
			if ( in_array( $key, AddProfileFields::SECTIONS, true ) ) {
				$out .= $this->row( 'f_' . $key, self::field_label( $key ), $this->input( $key, $rule, $value( 'f_' . $key ) ), $errors );
			}
		}
		$out .= '</tbody></table>';
		$out .= $this->image_fields( $type, $values, $errors );
		$out .= '<h2>' . esc_html__( 'Sources', 'whoiswho' ) . '</h2><table class="form-table" role="presentation"><tbody>';
		$out .= $this->row( 'sources', __( 'Source URLs *', 'whoiswho' ), '<textarea id="wiw-f-sources" name="sources" rows="4" class="large-text" required="required">' . esc_textarea( $value( 'sources' ) ) . '</textarea><p class="description">' . esc_html__( 'One web address per line, where these facts can be checked. At least one.', 'whoiswho' ) . '</p>', $errors );
		$out .= '</tbody></table>';
		if ( $duplicates ) {
			$out .= '<p><label><input type="checkbox" name="create_anyway" value="1" /> ' . esc_html__( 'None of the matches above is the same. Create anyway.', 'whoiswho' ) . '</label></p>';
		}
		return $out . '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save profile', 'whoiswho' ) . '</button></p></form>';
	}

	/**
	 * The photo or logo fields.
	 *
	 * @param string                $type   Entity type.
	 * @param array<string, string> $values Values.
	 * @param array<string, string> $errors Errors.
	 */
	private function image_fields( string $type, array $values, array $errors ): string {
		$person = in_array( $type, AddProfileFields::PERSON_TYPES, true );
		$id     = (int) ( $values['image_id'] ?? 0 );
		$out    = '<h2>' . esc_html( $person ? __( 'Photo (optional)', 'whoiswho' ) : __( 'Logo (optional)', 'whoiswho' ) ) . '</h2>';
		$out   .= '<p class="description">' . esc_html__( 'Choose a JPEG, PNG or WebP from the media library. Only openly licensed images may be used, and the licence, credit and source are then required.', 'whoiswho' ) . '</p>';
		$out   .= '<table class="form-table" role="presentation"><tbody>';
		$name   = $id > 0 ? (string) get_the_title( $id ) : __( 'Nothing chosen', 'whoiswho' );
		$pick   = '<input type="number" min="0" id="wiw-f-image_id" name="image_id" value="' . esc_attr( $id > 0 ? (string) $id : '' ) . '" class="small-text" aria-describedby="wiw-image-name" /> <span id="wiw-image-name" class="wiw-add-image-name">' . esc_html( $name ) . '</span> ';
		$pick  .= '<button type="button" class="button wiw-add-image-pick">' . esc_html__( 'Choose from media library', 'whoiswho' ) . '</button> <button type="button" class="button-link wiw-add-image-clear">' . esc_html__( 'Remove', 'whoiswho' ) . '</button>';
		$pick  .= '<p class="description">' . esc_html__( 'The number is the media library item id; the button fills it in.', 'whoiswho' ) . '</p>';
		$out   .= $this->row( 'image_id', __( 'Image', 'whoiswho' ), $pick, $errors );
		$select = '<select id="wiw-f-image_licence" name="image_licence"><option value="">' . esc_html__( 'Choose a licence', 'whoiswho' ) . '</option>';
		foreach ( ImagePolicy::licence_choices() as $licence => $label ) {
			$select .= '<option value="' . esc_attr( $licence ) . '"' . selected( (string) ( $values['image_licence'] ?? '' ), $licence, false ) . '>' . esc_html( $label ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
		}
		$out .= $this->row( 'image_licence', __( 'Licence', 'whoiswho' ), $select . '</select>', $errors );
		$out .= $this->row( 'image_credit', __( 'Credit (who made it, as the licence asks)', 'whoiswho' ), '<input type="text" id="wiw-f-image_credit" name="image_credit" class="regular-text" maxlength="300" value="' . esc_attr( (string) ( $values['image_credit'] ?? '' ) ) . '" />', $errors );
		$out .= $this->row( 'image_source', __( 'Image source URL', 'whoiswho' ), '<input type="url" id="wiw-f-image_source" name="image_source" class="regular-text" maxlength="2000" value="' . esc_attr( (string) ( $values['image_source'] ?? '' ) ) . '" />', $errors );
		return $out . '</tbody></table>';
	}

	/**
	 * One form row with its error.
	 *
	 * @param string                $name   Field name.
	 * @param string                $label  Label.
	 * @param string                $input  Input markup (already escaped).
	 * @param array<string, string> $errors Errors.
	 */
	private function row( string $name, string $label, string $input, array $errors ): string {
		$error = isset( $errors[ $name ] ) ? '<p class="description wiw-field-error" role="alert"><strong>' . esc_html( self::error_text( (string) $errors[ $name ] ) ) . '</strong></p>' : '';
		return '<tr' . ( '' === $error ? '' : ' class="form-invalid"' ) . '><th scope="row"><label for="wiw-f-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>' . $input . $error . '</td></tr>';
	}

	/**
	 * The input of one allowlisted field.
	 *
	 * @param string                                                    $key   Field key.
	 * @param array{type: string, required: bool, max: int, long: bool} $rule  Rule.
	 * @param string                                                    $value Value.
	 */
	private function input( string $key, array $rule, string $value ): string {
		$name     = 'f_' . $key;
		$required = $rule['required'] ? ' required="required"' : '';
		$taxonomy = TermMapper::FIELDS[ $key ] ?? '';
		if ( '' !== $taxonomy ) {
			return $this->term_select( $name, $this->terms( $taxonomy, Taxonomies::PLACE === $taxonomy ? Taxonomies::KIND_CITY : '' ), $value, $rule['required'] );
		}
		if ( $rule['long'] ) {
			$rows = in_array( $key, AddProfileFields::SECTIONS, true ) ? 8 : 4;
			$hint = 'list' === $rule['type'] ? '<p class="description">' . esc_html__( 'One per line.', 'whoiswho' ) . '</p>' : '';
			return '<textarea id="wiw-f-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" rows="' . esc_attr( (string) $rows ) . '" class="large-text" maxlength="' . esc_attr( (string) $rule['max'] ) . '"' . $required . '>' . esc_textarea( $value ) . '</textarea>' . $hint;
		}
		$html_type = 'url' === $rule['type'] ? 'url' : ( 'int' === $rule['type'] ? 'number' : 'text' );
		return '<input type="' . esc_attr( $html_type ) . '" id="wiw-f-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" class="regular-text" maxlength="' . esc_attr( (string) $rule['max'] ) . '" value="' . esc_attr( $value ) . '"' . $required . ' />';
	}

	/**
	 * A select of terms.
	 *
	 * @param string             $name     Field name.
	 * @param array<int, string> $terms    Term id => name.
	 * @param string             $value    Chosen id.
	 * @param bool               $required Required.
	 */
	private function term_select( string $name, array $terms, string $value, bool $required ): string {
		$out = '<select id="wiw-f-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"' . ( $required ? ' required="required"' : '' ) . '><option value="">' . esc_html__( 'Choose', 'whoiswho' ) . '</option>';
		foreach ( $terms as $id => $label ) {
			$out .= '<option value="' . esc_attr( (string) $id ) . '"' . selected( $value, (string) $id, false ) . '>' . esc_html( $label ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
		}
		$out .= '</select>';
		if ( array() === $terms ) {
			$out .= '<p class="description">' . esc_html__( 'This list is empty. Add entries on its list screen first.', 'whoiswho' ) . '</p>';
		}
		return $out;
	}

	/**
	 * Countries and pan-African bodies.
	 *
	 * @return array<int, string>
	 */
	private function countries(): array {
		$out = array();
		foreach ( array( Taxonomies::KIND_COUNTRY, Taxonomies::KIND_BODY ) as $kind ) {
			$out += $this->terms( Taxonomies::PLACE, $kind );
		}
		asort( $out );
		return $out;
	}

	/**
	 * Terms of a list, optionally of one place kind.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $kind     Place kind, or empty.
	 * @return array<int, string>
	 */
	private function terms( string $taxonomy, string $kind = '' ): array {
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => 1000,
			'orderby'    => 'name',
		);
		if ( '' !== $kind ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small controlled list.
				array(
					'key'   => Taxonomies::META_KIND,
					'value' => $kind,
				),
			);
		}
		$terms = get_terms( $args );
		$out   = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( $term instanceof \WP_Term ) {
				$out[ $term->term_id ] = $term->name;
			}
		}
		return $out;
	}

	/**
	 * A field's label.
	 *
	 * @param string $key Field key.
	 */
	public static function field_label( string $key ): string {
		$labels = array(
			'current_title'        => __( 'Current title', 'whoiswho' ),
			'current_organisation' => __( 'Current organisation', 'whoiswho' ),
			'org_type'             => __( 'Organisation type', 'whoiswho' ),
			'hq_city'              => __( 'Head office city', 'whoiswho' ),
			'year_founded'         => __( 'Year founded', 'whoiswho' ),
			'licence_category'     => __( 'Licence category', 'whoiswho' ),
			'official_website'     => __( 'Official website', 'whoiswho' ),
			'public_record_roles'  => __( 'Public roles on record', 'whoiswho' ),
			'published_awards'     => __( 'Published awards', 'whoiswho' ),
			'lifespan'             => __( 'Lifespan (only for people who have died, for example 1924–2013)', 'whoiswho' ),
			'field'                => __( 'Field or profession (for example Music)', 'whoiswho' ),
			'bio'                  => __( 'Bio', 'whoiswho' ),
			'achievement'          => __( 'Achievement', 'whoiswho' ),
			'influence'            => __( 'Influence', 'whoiswho' ),
			'legacy'               => __( 'Legacy', 'whoiswho' ),
		);
		return $labels[ $key ] ?? ucfirst( str_replace( '_', ' ', $key ) );
	}

	/**
	 * An entity type's label.
	 *
	 * @param string $type Entity type.
	 */
	public static function type_label( string $type ): string {
		$labels = array(
			'individual'   => __( 'Person', 'whoiswho' ),
			'entrepreneur' => __( 'Entrepreneur', 'whoiswho' ),
			'organisation' => __( 'Organisation', 'whoiswho' ),
			'institution'  => __( 'Institution', 'whoiswho' ),
		);
		return $labels[ $type ] ?? $type;
	}

	/**
	 * A publication state in words.
	 *
	 * @param string $state State.
	 */
	private static function state_label( string $state ): string {
		return PubState::is_public( $state ) ? __( 'public', 'whoiswho' ) : __( 'not public', 'whoiswho' );
	}

	/**
	 * An error code in words.
	 *
	 * @param string $code Code.
	 */
	private static function error_text( string $code ): string {
		$words = array(
			'required'    => __( 'This is required.', 'whoiswho' ),
			'too_long'    => __( 'This is too long.', 'whoiswho' ),
			'too_many'    => __( 'Give at most 10 source URLs.', 'whoiswho' ),
			'invalid_url' => __( 'Enter a full web address that starts with http:// or https://.', 'whoiswho' ),
			'not_allowed' => __( 'Choose one of the open licences in the list.', 'whoiswho' ),
		);
		return $words[ $code ] ?? __( 'Check this value.', 'whoiswho' );
	}
}
