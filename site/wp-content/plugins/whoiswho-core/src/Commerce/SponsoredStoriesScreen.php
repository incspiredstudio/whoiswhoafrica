<?php
/**
 * The Sponsored stories admin screen (REQ-179, REQ-269): the list of deals with four progress dots, status, next step and a status
 * filter, and the New sponsored story form that covers steps 1 and 2 (agree the deal, record the payment). Editors and
 * Administrators see the list; only the Administrator (wiw_manage_register) sees amounts and the forms. There is no deal type
 * choice in the form: the only type is the sponsored post. Every post is nonce-checked by ActionGuard and goes to DealService.
 * The list is a shared staff list table (SponsoredDealsTable, REQ-275); the form uses the staff form kit.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Commerce\Admin\SponsoredDealsTable;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Sponsored stories screen.
 */
final class SponsoredStoriesScreen {

	public const SCREEN = 'wiw-sponsored-stories';
	public const ACTION = 'wiw_sponsored_deal';
	public const CAP    = 'wiw_set_sponsored';
	public const MONEY  = DealService::MONEY_CAP;

	private const NEXT = array(
		'deal_agreed'      => 'Agree the deal',
		'payment_recorded' => 'Record the payment',
		'story_ready'      => 'Choose the story',
		'placement'        => 'Schedule the slot',
	);

	private const LABELS = array(
		DealStatus::CANCELLED         => 'Cancelled',
		DealStatus::LIVE              => 'Live',
		DealStatus::SCHEDULED         => 'Scheduled',
		DealStatus::ENDED             => 'Ended',
		DealStatus::NEEDS_PAYMENT     => 'Needs payment',
		DealStatus::NEEDS_STORY       => 'Needs story',
		DealStatus::READY_TO_SCHEDULE => 'Ready to schedule',
	);

	/**
	 * Register the screen and the action, in the admin only.
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
					__( 'Sponsored stories', 'whoiswho' ),
					self::CAP,
					static function (): void {
						( new self() )->render();
					},
					false,
					'commercial'
				);
				$registry->hook();
				ActionGuard::register(
					self::ACTION,
					self::CAP,
					static function ( Request $request ): void {
						$notice = ( new self() )->handle( $request );
						wp_safe_redirect( add_query_arg( 'wiw_notice', rawurlencode( $notice ), admin_url( 'admin.php?page=' . self::SCREEN ) ) );
						exit;
					}
				);
			},
			1
		);
	}

	/**
	 * Whole naira with up to two decimals to minor units, without floating point.
	 *
	 * @param string $text For example 500000 or 500000.50.
	 * @throws \InvalidArgumentException When it is not an amount.
	 */
	public static function minor_from_naira( string $text ): int {
		$text = str_replace( array( ',', ' ' ), '', trim( $text ) );
		if ( 1 !== preg_match( '/^([0-9]+)(?:\.([0-9]{1,2}))?$/', $text, $m ) ) {
			throw new \InvalidArgumentException( 'The amount is a number such as 500000 or 500000.50.' );
		}
		return (int) $m[1] * 100 + (int) str_pad( $m[2] ?? '0', 2, '0' );
	}

	/**
	 * Run one operation (capability and nonce already checked by ActionGuard; the service checks again).
	 *
	 * @param Request $request Request.
	 * @return string Message for the person.
	 */
	public function handle( Request $request ): string {
		$op    = $request->string( 'op' );
		$deals = $this->deals();
		try {
			switch ( $op ) {
				case 'create':
					$customer = $request->int( 'customer_id' );
					if ( $customer < 1 && '' !== $request->string( 'customer_name' ) ) {
						$customer = ( new CustomerService( new CustomerRepository(), AuditLogger::for_site() ) )->create( array( 'name' => $request->string( 'customer_name' ) ) );
					}
					$fields = array(
						'customer_id'  => $customer,
						'amount_minor' => self::minor_from_naira( $request->string( 'amount' ) ),
						'currency'     => '' === $request->string( 'currency' ) ? Money::DEFAULT_CURRENCY : $request->string( 'currency' ),
						'notes'        => $request->text( 'notes' ),
					);
					$id     = $deals->create( $fields );
					if ( '' !== $request->string( 'method' ) || '' !== $request->string( 'reference' ) ) {
						$deals->record_payment( $id, $this->payment_fields( $request ) );
					}
					return 'Sponsored story deal created.';
				case 'payment':
					$deals->record_payment( $request->int( 'deal_id' ), $this->payment_fields( $request ) );
					return 'Payment recorded.';
			}//end switch
		} catch ( \DomainException | \InvalidArgumentException $refusal ) {
			return $refusal->getMessage();
		}//end try
		return 'Nothing was changed.';
	}

	/**
	 * Payment fields from the request.
	 *
	 * @param Request $request Request.
	 * @return array<string, mixed>
	 */
	private function payment_fields( Request $request ): array {
		return array(
			'method'      => $request->string( 'method' ),
			'reference'   => $request->string( 'reference' ),
			'received_on' => $request->string( 'received_on' ),
		);
	}

	/**
	 * Deal service for this site.
	 */
	private function deals(): DealService {
		return new DealService( new ArrangementRepository(), AuditLogger::for_site() );
	}

	/**
	 * The screen markup.
	 *
	 * @param string $notice Message after an action.
	 * @param string $filter A status to show, or empty for all.
	 * @param int    $page   Page of the list.
	 * @throws \DomainException When the user may not see the screen.
	 */
	public function html( string $notice = '', string $filter = '', int $page = 1 ): string {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'You are not allowed to see sponsored stories.' );
		}
		$money = current_user_can( self::MONEY );
		$out   = '<div class="wrap wiw-staff"><h1>' . esc_html__( 'Sponsored stories', 'whoiswho' ) . '</h1>';
		if ( '' !== $notice ) {
			$out .= '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
		}
		$out .= '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="wiw-filters"><input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" /><label>' . esc_html__( 'Status', 'whoiswho' ) . ' <select name="status"><option value="">' . esc_html__( 'All statuses', 'whoiswho' ) . '</option>';
		foreach ( self::LABELS as $status => $label ) {
			$out .= '<option value="' . esc_attr( $status ) . '"' . selected( $filter, $status, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$out .= '</select></label> <button type="submit" class="button">' . esc_html__( 'Filter', 'whoiswho' ) . '</button></form>';
		$out .= ( new SponsoredDealsTable( $filter ) )->html( new Request( array( 'paged' => (string) max( 1, $page ) ) ) );
		if ( current_user_can( DealExport::CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			$out .= Form::open( '<input type="hidden" name="action" value="' . esc_attr( DealExport::ACTION ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( DealExport::ACTION ) ) . '" />' ) . Form::actions( '<button class="button">' . esc_html__( 'Export deals as CSV', 'whoiswho' ) . '</button>' ) . '</form>';
		}
		if ( $money ) {
			$out .= $this->new_deal_form();
		}
		return $out . '</div>';
	}

	/**
	 * A deal status in words.
	 *
	 * @param string $status DealStatus value.
	 */
	public static function status_label( string $status ): string {
		return self::LABELS[ $status ] ?? $status;
	}

	/**
	 * The next step of a deal in words.
	 *
	 * @param string $step Progress step key.
	 */
	public static function next_label( string $step ): string {
		return self::NEXT[ $step ] ?? $step;
	}

	/**
	 * The New sponsored story form: steps 1 and 2. There is no deal type field.
	 */
	private function new_deal_form(): string {
		$customers = array( '0' => __( 'Choose', 'whoiswho' ) );
		foreach ( $this->customers() as $customer ) {
			$customers[ (string) (int) $customer['id'] ] = (string) $customer['name'];
		}
		$hidden = '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" /><input type="hidden" name="op" value="create" />'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />';
		$deal   = '<div class="wiw-field-grid">'
			. Form::select( 'customer_id', __( 'Existing customer', 'whoiswho' ), $customers, '0', array( 'searchable' => count( $customers ) > 10 ) )
			. Form::text( 'customer_name', __( 'or add a new customer', 'whoiswho' ), '', array( 'maxlength' => 190 ) )
			. Form::text(
				'amount',
				__( 'Amount agreed', 'whoiswho' ),
				'',
				array(
					'attrs' => array( 'inputmode' => 'decimal' ),
					'help'  => __( 'For example 500000 or 500000.50.', 'whoiswho' ),
				)
			)
			. Form::text(
				'currency',
				__( 'Currency', 'whoiswho' ),
				Money::DEFAULT_CURRENCY,
				array(
					'maxlength' => 3,
					'class'     => 'small-text',
				)
			)
			. '</div>'
			. Form::textarea( 'notes', __( 'Note', 'whoiswho' ), '', array( 'rows' => 2 ) );
		$paid   = '<div class="wiw-field-grid">'
			. Form::text( 'method', __( 'Method', 'whoiswho' ), '', array( 'maxlength' => 40 ) )
			. Form::text( 'reference', __( 'Reference', 'whoiswho' ), '', array( 'maxlength' => 190 ) )
			. Form::row( 'wiw-f-received_on', __( 'Date received', 'whoiswho' ), '<input type="date" id="wiw-f-received_on" name="received_on" class="wiw-input" />' )
			. '</div>';
		$out    = Form::open( $hidden, array( 'unsaved' => true ) );
		$out   .= Form::section(
			__( 'New sponsored story', 'whoiswho' ),
			$deal . Form::section(
				__( 'Payment received (optional)', 'whoiswho' ),
				$paid,
				array(
					'level' => 3,
					'class' => 'wiw-card--inset',
				)
			),
			array(
				'id'          => 'wiw-new-deal',
				'description' => __( 'Steps 1 and 2: agree the deal and, when it is in, record the payment. Every deal is a sponsored post.', 'whoiswho' ),
			)
		);
		return $out . Form::actions( '<button type="submit" class="button button-primary">' . esc_html__( 'Create deal', 'whoiswho' ) . '</button>' ) . '</form>';
	}

	/**
	 * Customers for the picker.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function customers(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT id, name FROM ' . CustomerRepository::table() . ' ORDER BY name LIMIT 200', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name, no variable.
	}

	/**
	 * Print the screen.
	 */
	public function render(): void {
		$request = Request::from_globals();
		echo $this->html( $request->string( 'wiw_notice' ), $request->string( 'status' ), max( 1, $request->int( 'paged' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
	}
}
