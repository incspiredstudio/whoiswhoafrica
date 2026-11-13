<?php
/**
 * The Sponsored stories admin screen (REQ-179, REQ-269): the list of deals with four progress dots, status, next step and a status
 * filter, and the New sponsored story form that covers steps 1 and 2 (agree the deal, record the payment). Editors and
 * Administrators see the list; only the Administrator (wiw_manage_register) sees amounts and the forms. There is no deal type
 * choice in the form: the only type is the sponsored post. Every post is nonce-checked by ActionGuard and goes to DealService.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
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
					}
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
	 * @throws \DomainException When the user may not see the screen.
	 */
	public function html( string $notice = '', string $filter = '' ): string {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'You are not allowed to see sponsored stories.' );
		}
		$money  = current_user_can( self::MONEY );
		$deals  = new DealService( new ArrangementRepository(), AuditLogger::for_site() );
		$places = new PlacementRepository();
		$today  = gmdate( 'Y-m-d' );
		$out    = '<div class="wrap"><h1>' . esc_html__( 'Sponsored stories', 'whoiswho' ) . '</h1>';
		if ( '' !== $notice ) {
			$out .= '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
		}
		$out .= '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" /><select name="status"><option value="">' . esc_html__( 'All statuses', 'whoiswho' ) . '</option>';
		foreach ( self::LABELS as $status => $label ) {
			$out .= '<option value="' . esc_attr( $status ) . '"' . selected( $filter, $status, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$out  .= '</select> <button type="submit" class="button">' . esc_html__( 'Filter', 'whoiswho' ) . '</button></form>';
		$out  .= '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Sponsor', 'whoiswho' ) . '</th><th>' . esc_html__( 'Story', 'whoiswho' ) . '</th>' . ( $money ? '<th>' . esc_html__( 'Amount', 'whoiswho' ) . '</th>' : '' ) . '<th>' . esc_html__( 'Progress', 'whoiswho' ) . '</th><th>' . esc_html__( 'Status', 'whoiswho' ) . '</th><th>' . esc_html__( 'Next step', 'whoiswho' ) . '</th></tr></thead><tbody>';
		$shown = 0;
		foreach ( ( new ArrangementRepository() )->all() as $row ) {
			$placements = $places->for_arrangement( (int) $row['id'] );
			$status     = DealStatus::of( $row, $placements, $today );
			if ( '' !== $filter && $status !== $filter ) {
				continue;
			}
			++$shown;
			$view     = $deals->view( $row );
			$progress = DealStatus::progress( $row, $placements );
			$customer = ( new CustomerRepository() )->get( (int) $row['customer_id'] );
			$story    = empty( $row['story_id'] ) ? '' : get_the_title( (int) $row['story_id'] );
			$dots     = '';
			foreach ( $progress['steps'] as $done ) {
				$dots .= $done ? '●' : '○';
			}
			$out .= '<tr><td>' . esc_html( null === $customer ? '' : (string) $customer['name'] ) . '</td><td>' . esc_html( $story ) . '</td>';
			if ( $money ) {
				$amount = ArrangementRepository::money( $view );
				$out   .= '<td>' . esc_html( null === $amount ? '' : $amount->currency() . ' ' . number_format_i18n( $amount->minor() / 100, 2 ) ) . '</td>';
			}
			$out .= '<td class="wiw-dots">' . esc_html( $dots ) . '</td><td>' . esc_html( self::LABELS[ $status ] ) . '</td><td>' . esc_html( null === $progress['next'] ? '' : self::NEXT[ $progress['next'] ] ) . '</td></tr>';
		}//end foreach
		if ( 0 === $shown ) {
			$out .= '<tr><td colspan="6">' . esc_html__( 'No sponsored stories yet.', 'whoiswho' ) . '</td></tr>';
		}
		$out .= '</tbody></table>';
		if ( $money ) {
			$out .= $this->new_deal_form();
		}
		return $out . '</div>';
	}

	/**
	 * The New sponsored story form: steps 1 and 2. There is no deal type field.
	 */
	private function new_deal_form(): string {
		$out  = '<h2>' . esc_html__( 'New sponsored story', 'whoiswho' ) . '</h2>';
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" /><input type="hidden" name="op" value="create" />';
		$out .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />';
		$out .= '<p><label>' . esc_html__( 'Existing customer', 'whoiswho' ) . ' <select name="customer_id"><option value="0">' . esc_html__( 'Choose', 'whoiswho' ) . '</option>';
		foreach ( $this->customers() as $customer ) {
			$out .= '<option value="' . (int) $customer['id'] . '">' . esc_html( (string) $customer['name'] ) . '</option>';
		}
		$out .= '</select></label> <label>' . esc_html__( 'or add a new customer', 'whoiswho' ) . ' <input type="text" name="customer_name" class="regular-text" maxlength="190" /></label></p>';
		$out .= '<p><label>' . esc_html__( 'Amount agreed', 'whoiswho' ) . ' <input type="text" name="amount" inputmode="decimal" /></label> <label>' . esc_html__( 'Currency', 'whoiswho' ) . ' <input type="text" name="currency" value="NGN" maxlength="3" size="4" /></label></p>';
		$out .= '<p><label>' . esc_html__( 'Note', 'whoiswho' ) . ' <textarea name="notes" rows="2" cols="40"></textarea></label></p>';
		$out .= '<fieldset><legend>' . esc_html__( 'Payment received (optional)', 'whoiswho' ) . '</legend>';
		$out .= '<label>' . esc_html__( 'Method', 'whoiswho' ) . ' <input type="text" name="method" maxlength="40" /></label> <label>' . esc_html__( 'Reference', 'whoiswho' ) . ' <input type="text" name="reference" maxlength="190" /></label> <label>' . esc_html__( 'Date received', 'whoiswho' ) . ' <input type="date" name="received_on" /></label></fieldset>';
		return $out . '<p><button type="submit" class="button button-primary">' . esc_html__( 'Create deal', 'whoiswho' ) . '</button></p></form>';
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
		echo wp_kses(
			$this->html( $request->string( 'wiw_notice' ), $request->string( 'status' ) ),
			array(
				'div'      => array( 'class' => true ),
				'h1'       => array(),
				'h2'       => array(),
				'p'        => array(),
				'table'    => array( 'class' => true ),
				'thead'    => array(),
				'tbody'    => array(),
				'tr'       => array(),
				'th'       => array(),
				'td'       => array(
					'class'   => true,
					'colspan' => true,
				),
				'form'     => array(
					'method' => true,
					'action' => true,
				),
				'fieldset' => array(),
				'legend'   => array(),
				'label'    => array(),
				'select'   => array( 'name' => true ),
				'option'   => array(
					'value'    => true,
					'selected' => true,
				),
				'textarea' => array(
					'name' => true,
					'rows' => true,
					'cols' => true,
				),
				'input'    => array(
					'type'      => true,
					'name'      => true,
					'value'     => true,
					'class'     => true,
					'maxlength' => true,
					'size'      => true,
					'inputmode' => true,
				),
				'button'   => array(
					'type'  => true,
					'class' => true,
				),
			)
		);
	}
}
