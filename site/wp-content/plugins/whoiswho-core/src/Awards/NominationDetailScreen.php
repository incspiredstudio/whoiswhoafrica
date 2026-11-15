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
use WhoIsWho\Core\Platform\Admin\Ui\Form;

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
					true,
					'awards'
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
		$out  = '<div class="wrap wiw-staff"><h1>' . esc_html__( 'Nomination', 'whoiswho' ) . '</h1>' . $back;
		if ( null === $row ) {
			return $out . '<p>' . esc_html__( 'That nomination does not exist.', 'whoiswho' ) . '</p></div>';
		}
		if ( '' !== $notice ) {
			$out .= '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
		}
		$profile_id = (int) $row['profile_id'];
		$nominee    = '' !== (string) $row['nominee_name'] ? (string) $row['nominee_name'] : ( $profile_id > 0 ? get_the_title( $profile_id ) : '' );
		$facts      = $this->facts(
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
		$out       .= Form::section( __( 'Details', 'whoiswho' ), $facts, array( 'id' => 'wiw-nomination-details' ) );
		$out       .= Form::section( __( 'Evidence', 'whoiswho' ), '<p>' . nl2br( esc_html( (string) $row['evidence'] ) ) . '</p>', array( 'id' => 'wiw-nomination-evidence' ) );
		$out       .= $this->duplicates( $id, $nominee, (string) $row['nominee_org'], (int) $row['category_id'] );
		$out       .= $this->history( $id );
		$notes      = Form::textarea(
			'notes',
			__( 'Notes', 'whoiswho' ),
			(string) $row['notes'],
			array(
				'rows'      => 5,
				'maxlength' => NominationService::NOTES_MAX,
				'counter'   => true,
			)
		);
		$out       .= Form::section(
			__( 'Internal notes', 'whoiswho' ),
			$this->form( 'notes', $id, $notes . Form::actions( '<button type="submit" class="button button-primary">' . esc_html__( 'Save notes', 'whoiswho' ) . '</button>' ) ),
			array(
				'id'          => 'wiw-nomination-notes',
				'description' => __( 'For staff only. Never shown publicly and never in an export.', 'whoiswho' ),
			)
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
		$out = '<table class="widefat striped wiw-facts"><tbody>';
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
		$out = '';
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
		return Form::section( __( 'Duplicate warnings', 'whoiswho' ), $out, array( 'id' => 'wiw-nomination-duplicates' ) );
	}

	/**
	 * The change history, oldest first, with the staff member for each change.
	 *
	 * @param int $id Nomination.
	 */
	private function history( int $id ): string {
		$table = ( new NominationHistoryTable( $this->service->history( $id ) ) )->html( new Request( array() ) );
		return Form::section( __( 'Change history', 'whoiswho' ), $table, array( 'id' => 'wiw-nomination-history' ) );
	}

	/**
	 * The control that links a public-form nomination to an existing profile.
	 *
	 * @param int    $id        Nomination.
	 * @param string $profile_q Search text.
	 */
	private function link_control( int $id, string $profile_q ): string {
		$search = '<input type="search" id="wiw-f-profile_q" name="profile_q" class="wiw-input regular-text" value="' . esc_attr( $profile_q ) . '" maxlength="100" />';
		$out    = Form::open(
			'<input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" /><input type="hidden" name="nomination_id" value="' . esc_attr( (string) $id ) . '" />',
			array(
				'method' => 'get',
				'action' => admin_url( 'admin.php' ),
				'class'  => 'wiw-inline-fields',
			)
		);
		$out   .= Form::row( 'wiw-f-profile_q', __( 'Search profiles by name', 'whoiswho' ), $search );
		$out   .= '<div class="wiw-inline-fields__action"><button type="submit" class="button">' . esc_html__( 'Search', 'whoiswho' ) . '</button></div></form>';
		$found  = ( new ProfilePicker() )->search( $profile_q );
		if ( array() !== $found ) {
			$options = array();
			foreach ( $found as $profile ) {
				$options[ (string) $profile['id'] ] = $profile['name'] . ' (' . $profile['type'] . ')';
			}
			$out .= $this->form( 'link', $id, Form::select( 'profile_id', __( 'Profile', 'whoiswho' ), $options ) . Form::actions( '<button type="submit" class="button">' . esc_html__( 'Link profile', 'whoiswho' ) . '</button>' ) );
		}
		return Form::section( __( 'Link to an existing profile', 'whoiswho' ), $out, array( 'id' => 'wiw-nomination-link' ) );
	}

	/**
	 * A guarded POST form.
	 *
	 * @param string $op    Operation.
	 * @param int    $id    Nomination.
	 * @param string $inner Inner markup (already escaped).
	 */
	private function form( string $op, int $id, string $inner ): string {
		$out  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wiw-form">';
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
		echo $this->html( $request->int( 'nomination_id' ), $request->string( 'wiw_notice' ), $request->string( 'profile_q' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
	}
}
