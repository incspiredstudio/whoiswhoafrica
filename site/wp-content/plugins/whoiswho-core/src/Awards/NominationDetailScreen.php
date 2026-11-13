<?php
/**
 * The Nomination detail screen (REQ-031, REQ-196, REQ-202): one nomination with its nominator, nominee, evidence, change history,
 * duplicate warnings and internal notes, for staff with wiw_manage_awards only. It is reached from the Nominations list and has no
 * menu item of its own. A duplicate warning names the other nomination by number and never says who made it. The notes are never
 * public and never in an export. A nomination from the public form can be linked, once, to an existing profile. Every change is
 * made by NominationService, which checks the capability again and writes the audit row.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Awards\Admin\ProfilePicker;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;

/**
 * Nomination detail screen.
 */
final class NominationDetailScreen {

	public const SCREEN = 'wiw-nomination';
	public const ACTION = 'wiw_nomination_detail_op';
	public const CAP    = 'wiw_manage_awards';

	/**
	 * Nomination service.
	 *
	 * @var NominationService
	 */
	private NominationService $service;

	/**
	 * Constructor.
	 *
	 * @param NominationService $service The service every change calls.
	 */
	public function __construct( NominationService $service ) {
		$this->service = $service;
	}

	/**
	 * Register the screen and its action, in the admin only.
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
					__( 'Nomination', 'whoiswho' ),
					self::CAP,
					static function (): void {
						( new self( NominationService::for_site() ) )->render();
					},
					true
				);
				$registry->hook();
				ActionGuard::register(
					self::ACTION,
					self::CAP,
					static function ( Request $request ): void {
						$notice = ( new self( NominationService::for_site() ) )->handle( $request );
						wp_safe_redirect( self::url( $request->int( 'nomination_id' ), $notice ) );
						exit;
					}
				);
			},
			1
		);
	}

	/**
	 * Address of the detail screen for a nomination.
	 *
	 * @param int    $id     Nomination.
	 * @param string $notice Message to show, or empty.
	 */
	public static function url( int $id, string $notice = '' ): string {
		$args = array(
			'page'          => self::SCREEN,
			'nomination_id' => $id,
		);
		if ( '' !== $notice ) {
			$args['wiw_notice'] = rawurlencode( $notice );
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Run one change (capability and nonce already checked by ActionGuard; the service checks the capability again).
	 *
	 * @param Request $request Request.
	 * @return string Message for the person.
	 */
	public function handle( Request $request ): string {
		$id = $request->int( 'nomination_id' );
		try {
			switch ( $request->string( 'op' ) ) {
				case 'notes':
					$this->service->save_notes( $id, $request->text( 'notes' ) );
					return 'Notes saved.';
				case 'link':
					$this->service->link_profile( $id, $request->int( 'profile_id' ) );
					return 'The nomination is linked to the profile.';
			}
		} catch ( \DomainException $refusal ) {
			return $refusal->getMessage();
		}
		return 'Nothing was changed.';
	}

	/**
	 * The screen markup.
	 *
	 * @param int    $id        Nomination.
	 * @param string $notice    Message to show after an action.
	 * @param string $profile_q Search text for the profile link control.
	 * @throws \DomainException When the user may not manage awards.
	 */
	public function html( int $id, string $notice = '', string $profile_q = '' ): string {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'You are not allowed to manage nominations.' );
		}
		$back = '<p><a href="' . esc_url( add_query_arg( 'page', NominationsSwitch::SCREEN, admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Back to Nominations', 'whoiswho' ) . '</a></p>';
		$row  = $this->service->find( $id );
		$out  = '<div class="wrap"><h1>' . esc_html__( 'Nomination', 'whoiswho' ) . '</h1>' . $back;
		if ( null === $row ) {
			return $out . '<p>' . esc_html__( 'That nomination does not exist.', 'whoiswho' ) . '</p></div>';
		}
		if ( '' !== $notice ) {
			$out .= '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
		}
		$profile_id = (int) $row['profile_id'];
		$nominee    = '' !== (string) $row['nominee_name'] ? (string) $row['nominee_name'] : ( $profile_id > 0 ? get_the_title( $profile_id ) : '' );
		$out       .= $this->facts(
			array(
				__( 'Reference', 'whoiswho' )       => (string) $row['reference'],
				__( 'Nominee', 'whoiswho' )         => $nominee,
				__( 'Organisation', 'whoiswho' )    => (string) $row['nominee_org'],
				__( 'Linked profile', 'whoiswho' )  => $profile_id > 0 ? get_the_title( $profile_id ) : __( '(none)', 'whoiswho' ),
				__( 'Category', 'whoiswho' )        => $this->category_title( (int) $row['category_id'] ),
				__( 'Stage', 'whoiswho' )           => NominationsScreen::label( (string) $row['stage'] ),
				__( 'Status', 'whoiswho' )          => NominationsScreen::label( (string) $row['status'] ),
				__( 'Source', 'whoiswho' )          => NominationsScreen::label( (string) $row['source'] ),
				__( 'Nominator', 'whoiswho' )       => (string) $row['nominator_name'],
				__( 'Nominator email', 'whoiswho' ) => (string) $row['nominator_email'],
				__( 'Relationship', 'whoiswho' )    => (int) $row['relationship_declared'] ? (string) $row['relationship_note'] : '',
				__( 'Submitted', 'whoiswho' )       => (string) $row['created_at'],
			)
		);
		$out       .= '<h2>' . esc_html__( 'Evidence', 'whoiswho' ) . '</h2><p>' . nl2br( esc_html( (string) $row['evidence'] ) ) . '</p>';
		$out       .= $this->duplicates( $id, $nominee, (string) $row['nominee_org'], (int) $row['category_id'] );
		$out       .= $this->history( $id );
		$out       .= '<h2>' . esc_html__( 'Internal notes', 'whoiswho' ) . '</h2>';
		$out       .= '<p>' . esc_html__( 'For staff only. Never shown publicly and never in an export.', 'whoiswho' ) . '</p>';
		$out       .= $this->form(
			'notes',
			$id,
			'<p><textarea name="notes" rows="5" cols="60" maxlength="' . esc_attr( (string) NominationService::NOTES_MAX ) . '">' . esc_textarea( (string) $row['notes'] ) . '</textarea></p><p><button type="submit" class="button button-primary">' . esc_html__( 'Save notes', 'whoiswho' ) . '</button></p>'
		);
		if ( 0 === $profile_id ) {
			$out .= $this->link_control( $id, $profile_q );
		}
		return $out . '</div>';
	}

	/**
	 * A two-column table of labelled values; empty values are left out.
	 *
	 * @param array<string, string> $facts Label => value.
	 */
	private function facts( array $facts ): string {
		$out = '<table class="widefat striped"><tbody>';
		foreach ( $facts as $label => $value ) {
			if ( '' === $value ) {
				continue;
			}
			$out .= '<tr><th scope="row">' . esc_html( (string) $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}
		return $out . '</tbody></table>';
	}

	/**
	 * Duplicate warnings: other nominations of the same nominee in the same category, by number only.
	 *
	 * @param int    $id          Nomination.
	 * @param string $nominee     Nominee name.
	 * @param string $org         Organisation.
	 * @param int    $category_id Category.
	 */
	private function duplicates( int $id, string $nominee, string $org, int $category_id ): string {
		$ids = ( new NominationDuplicates() )->same_nominee( $nominee, $org, $category_id, $id );
		if ( array() === $ids ) {
			return '';
		}
		$out = '<h2>' . esc_html__( 'Duplicate warnings', 'whoiswho' ) . '</h2>';
		foreach ( $ids as $other ) {
			$link = add_query_arg(
				array(
					'page'          => self::SCREEN,
					'nomination_id' => $other,
				),
				admin_url( 'admin.php' )
			);
			/* translators: %d: another nomination's number. */
			$out .= '<div class="notice notice-warning inline"><p>' . esc_html__( 'Possible duplicate:', 'whoiswho' ) . ' <a href="' . esc_url( $link ) . '">' . esc_html( sprintf( __( 'nomination #%d', 'whoiswho' ), $other ) ) . '</a> ' . esc_html__( 'has the same nominee in this category.', 'whoiswho' ) . '</p></div>';
		}
		return $out;
	}

	/**
	 * The change history, oldest first, with the staff member for each change.
	 *
	 * @param int $id Nomination.
	 */
	private function history( int $id ): string {
		$out  = '<h2>' . esc_html__( 'Change history', 'whoiswho' ) . '</h2>';
		$rows = $this->service->history( $id );
		if ( array() === $rows ) {
			return $out . '<p>' . esc_html__( 'No changes yet.', 'whoiswho' ) . '</p>';
		}
		$out .= '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'When (UTC)', 'whoiswho' ), __( 'Change', 'whoiswho' ), __( 'From', 'whoiswho' ), __( 'To', 'whoiswho' ), __( 'Staff member', 'whoiswho' ) ) as $heading ) {
			$out .= '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		$out .= '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$user = get_userdata( (int) $row['user_id'] );
			$out .= '<tr><td>' . esc_html( (string) $row['changed_at'] ) . '</td><td>' . esc_html( (string) $row['change_type'] ) . '</td><td>' . esc_html( NominationsScreen::label( (string) $row['old_value'] ) ) . '</td><td>' . esc_html( NominationsScreen::label( (string) $row['new_value'] ) ) . '</td><td>' . esc_html( false === $user ? __( '(removed user)', 'whoiswho' ) : (string) $user->display_name ) . '</td></tr>';
		}
		return $out . '</tbody></table>';
	}

	/**
	 * The control that links a public-form nomination to an existing profile.
	 *
	 * @param int    $id        Nomination.
	 * @param string $profile_q Search text.
	 */
	private function link_control( int $id, string $profile_q ): string {
		$out   = '<h2>' . esc_html__( 'Link to an existing profile', 'whoiswho' ) . '</h2>';
		$out  .= '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" /><input type="hidden" name="nomination_id" value="' . esc_attr( (string) $id ) . '" />';
		$out  .= '<label>' . esc_html__( 'Search profiles by name', 'whoiswho' ) . ' <input type="search" name="profile_q" value="' . esc_attr( $profile_q ) . '" maxlength="100" /></label> <button type="submit" class="button">' . esc_html__( 'Search', 'whoiswho' ) . '</button></form>';
		$found = ( new ProfilePicker() )->search( $profile_q );
		if ( array() === $found ) {
			return $out;
		}
		$select = '<p><label>' . esc_html__( 'Profile', 'whoiswho' ) . ' <select name="profile_id">';
		foreach ( $found as $profile ) {
			$select .= '<option value="' . esc_attr( (string) $profile['id'] ) . '">' . esc_html( $profile['name'] . ' (' . $profile['type'] . ')' ) . '</option>';
		}
		return $out . $this->form( 'link', $id, $select . '</select></label></p><p><button type="submit" class="button">' . esc_html__( 'Link profile', 'whoiswho' ) . '</button></p>' );
	}

	/**
	 * A guarded POST form.
	 *
	 * @param string $op    Operation.
	 * @param int    $id    Nomination.
	 * @param string $inner Inner markup (already escaped).
	 */
	private function form( string $op, int $id, string $inner ): string {
		$out  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		$out .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />';
		$out .= '<input type="hidden" name="op" value="' . esc_attr( $op ) . '" />';
		$out .= '<input type="hidden" name="nomination_id" value="' . esc_attr( (string) $id ) . '" />';
		return $out . $inner . '</form>';
	}

	/**
	 * Category title.
	 *
	 * @param int $category_id Category.
	 */
	private function category_title( int $category_id ): string {
		global $wpdb;
		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT title FROM ' . AwardsTables::table( AwardsTables::CATEGORIES ) . ' WHERE id = %d', $category_id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	/**
	 * Print the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to see this page.', 'whoiswho' ), '', array( 'response' => 403 ) );
		}
		$request = Request::from_globals();
		echo wp_kses(
			$this->html( $request->int( 'nomination_id' ), $request->string( 'wiw_notice' ), $request->string( 'profile_q' ) ),
			array(
				'div'      => array( 'class' => true ),
				'h1'       => array(),
				'h2'       => array(),
				'p'        => array(),
				'br'       => array(),
				'a'        => array( 'href' => true ),
				'label'    => array(),
				'table'    => array( 'class' => true ),
				'thead'    => array(),
				'tbody'    => array(),
				'tr'       => array(),
				'th'       => array( 'scope' => true ),
				'td'       => array(),
				'form'     => array(
					'method' => true,
					'action' => true,
				),
				'textarea' => array(
					'name'      => true,
					'rows'      => true,
					'cols'      => true,
					'maxlength' => true,
				),
				'select'   => array( 'name' => true ),
				'option'   => array( 'value' => true ),
				'input'    => array(
					'type'      => true,
					'name'      => true,
					'value'     => true,
					'maxlength' => true,
				),
				'button'   => array(
					'type'  => true,
					'class' => true,
				),
			)
		);
	}
}
