<?php
/**
 * The staff Add profile screen in the Directory menu (REQ-209, REQ-208, REQ-145, REQ-244, REQ-267). A staff member with
 * wiw_add_profile picks the entity type, then fills the fields of that type (taken from the allowlist in
 * config/profile-fields.php), the classification (Industry picker, Organisation type, Country and City, as on Edit profile), the
 * four narrative sections, an optional photo or logo from the media library with its
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
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Import\Images\ImagePolicy;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
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
				add_action( 'admin_menu', array( self::class, 'hide_core_add_new' ), 999 );
				add_action( 'load-post-new.php', array( self::class, 'redirect_core_add_new' ) );
			},
			1
		);
	}

	/**
	 * Only one way to add a profile: drop WordPress's own "Add profile" item under its Profiles menu, so Directory > Add
	 * profile is the one a person sees.
	 */
	public static function hide_core_add_new(): void {
		remove_submenu_page( 'edit.php?post_type=' . ProfileType::POST_TYPE, 'post-new.php?post_type=' . ProfileType::POST_TYPE );
	}

	/**
	 * Where WordPress's own new-profile screen sends a person instead: Directory > Add profile, for someone who may use it. Anyone
	 * else gets null and WordPress's own capability check answers as before.
	 *
	 * @param string $post_type Post type of the new-post screen.
	 */
	public static function core_add_new_target( string $post_type ): ?string {
		if ( ProfileType::POST_TYPE !== $post_type || ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return null;
		}
		return self::url();
	}

	/**
	 * Send post-new.php?post_type=wiw_profile to Directory > Add profile.
	 */
	public static function redirect_core_add_new(): void {
		global $typenow;
		$target = self::core_add_new_target( is_string( $typenow ) ? $typenow : '' );
		if ( null !== $target ) {
			wp_safe_redirect( $target );
			exit;
		}
	}

	/**
	 * The media library picker and the character counter (the Edit profile screen's counter), on this screen only.
	 *
	 * @param string $hook Admin page hook suffix.
	 */
	public static function enqueue( $hook ): void {
		if ( false === strpos( (string) $hook, self::SCREEN ) || ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'wiw-add-profile', plugins_url( 'assets/admin/directory/add-profile.js', WIW_CORE_FILE ), array( 'media-views' ), WIW_CORE_VERSION, true );
		wp_enqueue_script( 'wiw-char-count', plugins_url( 'assets/admin/directory/char-count.js', WIW_CORE_FILE ), array(), WIW_CORE_VERSION, true );
		ClassificationPicker::enqueue();
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
			'entity_type'      => $type,
			'name'             => $request->string( 'name' ),
			'country'          => $request->string( 'country' ),
			'city'             => $request->string( 'city' ),
			'primary_industry' => $request->string( 'primary_industry' ),
			'org_type'         => $request->string( 'org_type' ),
			'sources'          => $request->text( 'sources' ),
			'wikidata_qid'     => strtoupper( $request->string( 'wikidata_qid' ) ),
			'rc_number'        => $request->string( 'rc_number' ),
			'image_id'         => $request->string( 'image_id' ),
			'image_licence'    => $request->string( 'image_licence' ),
			'image_credit'     => $request->string( 'image_credit' ),
			'image_source'     => $request->string( 'image_source' ),
		);
		foreach ( $fields as $key => $rule ) {
			$raw[ 'f_' . $key ] = $rule['long'] ? $request->text( 'f_' . $key ) : $request->string( 'f_' . $key );
		}
		$result               = ( new AddProfileValidator( $type, $form ) )->validate( new FormInput( $raw ) );
		$values               = $result->values();
		$values['industries'] = implode( ',', array_filter( $request->ints( 'industries' ), static fn ( int $id ): bool => $id > 0 ) );
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
		$classification = ( new ClassificationPicker( ClassificationPicker::ADD_NAMES ) )->from_request( $request );
		$override       = '1' === $request->string( 'create_anyway' );
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
				$override,
				$classification
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
		$out     = '<div class="wrap wiw-staff wiw-staff--form wiw-add-profile"><h1>' . esc_html__( 'Add profile', 'whoiswho' ) . '</h1>';
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
		$hidden = '<input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" /><input type="hidden" name="name" value="' . esc_attr( $name ) . '" />';
		if ( '' !== $back ) {
			$hidden .= '<input type="hidden" name="return" value="' . esc_attr( $back ) . '" />';
		}
		$types = array();
		foreach ( $form->types() as $type ) {
			$types[ $type ] = self::type_label( $type );
		}
		$body  = Form::open(
			$hidden,
			array(
				'method' => 'get',
				'action' => admin_url( 'admin.php' ),
			)
		) . Form::select( 'entity_type', __( 'Type', 'whoiswho' ), $types, '', array( 'id' => 'wiw-add-type' ) );
		$body .= Form::actions( '<button type="submit" class="button button-primary">' . esc_html__( 'Continue', 'whoiswho' ) . '</button>' ) . '</form>';
		return Form::section( __( 'What are you adding?', 'whoiswho' ), $body, array( 'description' => __( 'Choose what you are adding. The form then shows the fields for that type.', 'whoiswho' ) ) );
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
		$value   = static fn ( string $key ): string => (string) ( $values[ $key ] ?? '' );
		$out     = '<p>' . esc_html( sprintf( /* translators: %s: entity type. */ __( 'Type: %s.', 'whoiswho' ), self::type_label( $type ) ) ) . ' <a href="' . esc_url( self::url( $name, $back ) ) . '">' . esc_html__( 'Change type', 'whoiswho' ) . '</a></p>';
		$out    .= '<p>' . esc_html__( 'Fields marked * are required. The new profile goes to the review queue and is not public until a reviewer approves it.', 'whoiswho' ) . '</p>';
		$hidden  = '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />';
		$hidden .= '<input type="hidden" name="entity_type" value="' . esc_attr( $type ) . '" />';
		if ( '' !== $back ) {
			$hidden .= '<input type="hidden" name="return" value="' . esc_attr( $back ) . '" />';
		}
		$out    .= Form::open(
			$hidden,
			array(
				'class'   => 'wiw-add-profile-form',
				'unsaved' => true,
			)
		);
		$main    = $this->row( 'name', __( 'Name', 'whoiswho' ) . ' *', '<input type="text" id="wiw-f-name" name="name" class="regular-text" maxlength="200" required="required" value="' . esc_attr( $name ) . '" />', $errors );
		$current = array(
			'primary_industry' => (int) $value( 'primary_industry' ),
			'industries'       => array_values( array_filter( array_map( 'intval', explode( ',', $value( 'industries' ) ) ) ) ),
			'org_type'         => (int) $value( 'org_type' ),
			'country'          => (int) $value( 'country' ),
			'city'             => (int) $value( 'city' ),
		);
		$classes = '';
		foreach ( ( new ClassificationPicker( ClassificationPicker::ADD_NAMES ) )->rows( $current, $type, true ) as $picker_row ) {
			$classes .= $this->row( $picker_row['name'], $picker_row['label'], $picker_row['html'], $errors );
		}
		foreach ( $form->for_type( $type ) as $key => $rule ) {
			if ( in_array( $key, AddProfileFields::SECTIONS, true ) ) {
				continue;
			}
			$main .= $this->row( 'f_' . $key, $form->label( $type, $key ) . ( $rule['required'] ? ' *' : '' ), $this->input( $key, $rule, $value( 'f_' . $key ) ), $errors );
		}
		$main .= $this->row( 'wikidata_qid', __( 'Wikidata id (optional, for example Q42)', 'whoiswho' ), '<input type="text" id="wiw-f-wikidata_qid" name="wikidata_qid" maxlength="20" value="' . esc_attr( $value( 'wikidata_qid' ) ) . '" />', $errors );
		if ( in_array( $type, AddProfileValidator::ORG_TYPES, true ) ) {
			$main .= $this->row( 'rc_number', __( 'Registration number (optional)', 'whoiswho' ), '<input type="text" id="wiw-f-rc_number" name="rc_number" maxlength="40" value="' . esc_attr( $value( 'rc_number' ) ) . '" />', $errors );
		}
		$out     .= Form::section( __( 'Profile details', 'whoiswho' ), $main, array( 'id' => 'wiw-add-details' ) );
		$out     .= Form::section( __( 'Classification', 'whoiswho' ), $classes, array( 'id' => 'wiw-add-classification' ) );
		$sections = '';
		foreach ( $form->for_type( $type ) as $key => $rule ) {
			if ( in_array( $key, AddProfileFields::SECTIONS, true ) ) {
				$sections .= $this->row( 'f_' . $key, $form->label( $type, $key ), $this->input( $key, $rule, $value( 'f_' . $key ) ), $errors );
			}
		}
		$out    .= Form::section(
			__( 'Profile sections', 'whoiswho' ),
			$sections,
			array(
				'id'          => 'wiw-add-sections',
				'description' => __( 'Plain text. Leave a blank line between paragraphs. Each section is optional.', 'whoiswho' ),
			)
		);
		$out    .= $this->image_fields( $type, $values, $errors );
		$sources = $this->row( 'sources', __( 'Source URLs *', 'whoiswho' ), '<textarea id="wiw-f-sources" name="sources" rows="4" class="large-text wiw-input wiw-textarea" required="required">' . esc_textarea( $value( 'sources' ) ) . '</textarea>', $errors, __( 'One web address per line, where these facts can be checked. At least one.', 'whoiswho' ) );
		if ( $duplicates ) {
			$sources .= Form::checkbox( 'create_anyway', __( 'None of the matches above is the same. Create anyway.', 'whoiswho' ) );
		}
		$out .= Form::section( __( 'Sources', 'whoiswho' ), $sources, array( 'id' => 'wiw-add-sources' ) );
		return $out . Form::actions( '<button type="submit" class="button button-primary">' . esc_html__( 'Save profile', 'whoiswho' ) . '</button>', array( 'sticky' => true ) ) . '</form>';
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
		$out    = '';
		$name   = $id > 0 ? (string) get_the_title( $id ) : __( 'Nothing chosen', 'whoiswho' );
		$pick   = '<div class="wiw-media-pick"><input type="number" min="0" id="wiw-f-image_id" name="image_id" value="' . esc_attr( $id > 0 ? (string) $id : '' ) . '" class="small-text wiw-input" aria-describedby="wiw-image-name" /> <span id="wiw-image-name" class="wiw-add-image-name">' . esc_html( $name ) . '</span> ';
		$pick  .= '<button type="button" class="button wiw-add-image-pick">' . esc_html__( 'Choose from media library', 'whoiswho' ) . '</button> <button type="button" class="button-link wiw-add-image-clear">' . esc_html__( 'Remove', 'whoiswho' ) . '</button></div>';
		$out   .= $this->row( 'image_id', __( 'Image', 'whoiswho' ), $pick, $errors, __( 'The number is the media library item id; the button fills it in.', 'whoiswho' ) );
		$select = '<select id="wiw-f-image_licence" name="image_licence" class="wiw-input wiw-select"><option value="">' . esc_html__( 'Choose a licence', 'whoiswho' ) . '</option>';
		foreach ( ImagePolicy::licence_choices() as $licence => $label ) {
			$select .= '<option value="' . esc_attr( $licence ) . '"' . selected( (string) ( $values['image_licence'] ?? '' ), $licence, false ) . '>' . esc_html( $label ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
		}
		$out .= $this->row( 'image_licence', __( 'Licence', 'whoiswho' ), $select . '</select>', $errors );
		$out .= $this->row( 'image_credit', __( 'Credit (who made it, as the licence asks)', 'whoiswho' ), '<input type="text" id="wiw-f-image_credit" name="image_credit" class="regular-text" maxlength="300" value="' . esc_attr( (string) ( $values['image_credit'] ?? '' ) ) . '" />', $errors );
		$out .= $this->row( 'image_source', __( 'Image source URL', 'whoiswho' ), '<input type="url" id="wiw-f-image_source" name="image_source" class="regular-text" maxlength="2000" value="' . esc_attr( (string) ( $values['image_source'] ?? '' ) ) . '" />', $errors );
		return Form::section(
			$person ? __( 'Photo (optional)', 'whoiswho' ) : __( 'Logo (optional)', 'whoiswho' ),
			$out,
			array(
				'id'          => 'wiw-add-image',
				'description' => __( 'Choose a JPEG, PNG or WebP from the media library. Only openly licensed images may be used, and the licence, credit and source are then required.', 'whoiswho' ),
			)
		);
	}

	/**
	 * One form row with its error.
	 *
	 * @param string                $name   Field name.
	 * @param string                $label  Label.
	 * @param string                $input  Input markup (already escaped).
	 * @param array<string, string> $errors Errors.
	 * @param string                $help   Help text under the field.
	 */
	private function row( string $name, string $label, string $input, array $errors, string $help = '' ): string {
		$args = array( 'help' => $help );
		if ( isset( $errors[ $name ] ) ) {
			$args['error'] = self::error_text( (string) $errors[ $name ] );
		}
		return Form::row( 'wiw-f-' . $name, $label, $input, $args );
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
		if ( $rule['long'] ) {
			$rows  = in_array( $key, AddProfileFields::SECTIONS, true ) ? 8 : 4;
			$hint  = 'list' === $rule['type'] ? ' ' . esc_html__( 'One per line.', 'whoiswho' ) : '';
			$count = 'wiw-count-' . $name;
			$used  = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
			$box   = '<textarea id="wiw-f-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" rows="' . esc_attr( (string) $rows ) . '" class="large-text wiw-input wiw-textarea" maxlength="' . esc_attr( (string) $rule['max'] ) . '" data-wiw-count="' . esc_attr( $count ) . '" aria-describedby="' . esc_attr( $count ) . '"' . $required . '>' . esc_textarea( $value ) . '</textarea>';
			return $box . '<p class="wiw-help wiw-help--count"><span id="' . esc_attr( $count ) . '" class="wiw-char-count" aria-live="polite">' . esc_html( sprintf( /* translators: 1: characters used, 2: the limit. */ __( '%1$d / %2$d characters', 'whoiswho' ), $used, $rule['max'] ) ) . '</span>' . $hint . '</p>';
		}
		$html_type = 'url' === $rule['type'] ? 'url' : ( 'int' === $rule['type'] ? 'number' : 'text' );
		return '<input type="' . esc_attr( $html_type ) . '" id="wiw-f-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" class="regular-text wiw-input" maxlength="' . esc_attr( (string) $rule['max'] ) . '" value="' . esc_attr( $value ) . '"' . $required . ' />';
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
