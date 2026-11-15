<?php
/**
 * The shared staff list table (REQ-275): one base for every staff table, built on WP_List_Table. A subclass declares its columns,
 * its items and total, its row quick actions and its bulk actions; the base adds the checkbox column and select all, a bulk
 * dropdown with only the actions the current user may run, paging (20 a page by default), an empty state, a confirmation step
 * for destructive actions or actions that need input, and the bulk processing.
 *
 * Bulk actions only repeat single-item actions that already exist on the screen, through the same domain service, so they add no
 * new power: the base checks the action's capability once, then runs the handler for each id, and the handler (through its
 * service) checks again per item and writes the audit row per item. The form posts to admin-post.php through ActionGuard (the
 * screen capability and the nonce wiw_action_{bulk_action()}), and the base checks the nonce again itself. Ids are read through
 * Request only. The result comes back as one notice ("3 approved, 1 skipped (...)") with counts and fixed reasons, never names.
 *
 * Subclass API (see docs/plugins/platform.md, "Staff UI kit"):
 *  - bulk_action(), capability(), screen_slug(), columns(), primary_column(), fetch(), item_id(), item_name(), column_html();
 *  - optionally row_actions_for(), bulk_action_definitions(), screen_args(), empty_message(), per_page().
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Admin\Ui;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
if ( ! function_exists( 'convert_to_screen' ) ) {
	require_once ABSPATH . 'wp-admin/includes/template.php';
}
if ( ! class_exists( '\WP_Screen' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
}
if ( ! function_exists( 'get_column_headers' ) ) {
	require_once ABSPATH . 'wp-admin/includes/screen.php';
}

/**
 * Staff list table base.
 *
 * @phpstan-type BulkDefinition array{label: string, capability: string, destructive?: bool, handler: callable, done?: string, fields?: callable}
 * @phpstan-type RowAction array{label: string, url: string, capability: string, confirm?: string}
 */
abstract class ListTable extends \WP_List_Table {

	public const PER_PAGE = 20;

	/**
	 * Request names the base uses.
	 */
	public const FIELD_ACTION  = 'wiw_bulk';
	public const FIELD_ACTION2 = 'wiw_bulk2';
	public const FIELD_IDS     = 'ids';
	public const FIELD_CONFIRM = 'confirm';
	public const FIELD_BACK    = 'wiw_back';
	public const STEP_ACTION   = 'wiw_confirm';
	public const STEP_IDS      = 'wiw_ids';
	public const NOTICE_VAR    = 'wiw_list_notice';

	/**
	 * Bulk results.
	 */
	public const DONE          = 'done';
	public const NEEDS_CONFIRM = 'confirm';
	public const REFUSED_NONCE = 'nonce';
	public const REFUSED_CAP   = 'capability';
	public const UNKNOWN       = 'unknown';
	public const NO_ROWS       = 'none';

	/**
	 * The most names a confirmation step lists before "and N more".
	 */
	private const STEP_NAMES = 20;

	/**
	 * Total items for the pager.
	 *
	 * @var int
	 */
	private int $total = 0;

	/**
	 * Constructor.
	 *
	 * @param string $singular Singular noun for the rows (for example profile).
	 * @param string $plural   Plural noun (for example profiles); used in CSS classes.
	 */
	public function __construct( string $singular, string $plural ) {
		parent::__construct(
			array(
				'singular' => $singular,
				'plural'   => $plural,
				'ajax'     => false,
				'screen'   => 'wiw-list-' . static::screen_slug(),
			)
		);
	}

	/* ---------------------------------------------------------------- What a subclass declares. */

	/**
	 * The admin-post action the bulk form posts to, for example wiw_review_bulk. Also the nonce action.
	 */
	abstract public static function bulk_action(): string;

	/**
	 * The screen capability: needed to post the bulk form at all (each bulk action names its own capability too).
	 */
	abstract public static function capability(): string;

	/**
	 * The screen slug (admin.php?page=...), where the table lives and where it sends the user back.
	 */
	abstract public static function screen_slug(): string;

	/**
	 * Columns: key => heading. The checkbox column is added by the base.
	 *
	 * @return array<string, string>
	 */
	abstract protected function columns(): array;

	/**
	 * The primary column key: it carries the row actions.
	 */
	abstract protected function primary_column(): string;

	/**
	 * One page of items and the total.
	 *
	 * @param int $page     Page, from 1.
	 * @param int $per_page Items per page.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	abstract protected function fetch( int $page, int $per_page ): array;

	/**
	 * The id a row's checkbox carries; 0 gives the row no checkbox (a row that bulk actions do not apply to).
	 *
	 * @param array<string, mixed> $item Item.
	 */
	abstract protected function item_id( array $item ): int;

	/**
	 * A row's name as plain text, for the checkbox label.
	 *
	 * @param array<string, mixed> $item Item.
	 */
	abstract protected function item_name( array $item ): string;

	/**
	 * One cell's markup, already escaped.
	 *
	 * @param array<string, mixed> $item   Item.
	 * @param string               $column Column key.
	 */
	abstract protected function column_html( array $item, string $column ): string;

	/**
	 * Row quick actions: key => label, url, capability and an optional confirm question. The base drops those the user may not
	 * use. A url may point at the confirmation step of a bulk action for one row: see step_url().
	 *
	 * @param array<string, mixed> $item Item.
	 * @return array<string, RowAction>
	 */
	protected function row_actions_for( array $item ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- a subclass uses it.
		return array();
	}

	/**
	 * Bulk actions: key => label, capability, destructive, handler and optional done and fields.
	 *  - handler: callable( int $id, Request $request ): bool|\WP_Error. True when done; false or a WP_Error when skipped (the
	 *    error message, a fixed sentence with no names, is shown as the reason).
	 *  - done: a sprintf pattern for the count done, for example "%d approved".
	 *  - fields: callable(): string, extra form fields (escaped markup) for the confirmation step, for example a shared reason.
	 *    An action with fields always goes through the confirmation step; a destructive one without fields asks with confirm()
	 *    in the browser, or through the step without JavaScript.
	 *
	 * @return array<string, BulkDefinition>
	 */
	protected function bulk_action_definitions(): array {
		return array();
	}

	/**
	 * Query args to keep when coming back to the list (filters, the page number). The page slug is always kept.
	 *
	 * @param Request $request Request.
	 * @return array<string, string|int>
	 */
	protected function screen_args( Request $request ): array {
		$paged = $request->int( 'paged' );
		return $paged > 1 ? array( 'paged' => $paged ) : array();
	}

	/**
	 * The text shown when there are no rows.
	 */
	protected function empty_message(): string {
		return __( 'Nothing to show.', 'whoiswho' );
	}

	/**
	 * Rows per page.
	 */
	protected function per_page(): int {
		return self::PER_PAGE;
	}

	/**
	 * A row's name for the confirmation step, by id. The default reads the post title.
	 *
	 * @param int $id Id.
	 */
	protected function name_for_id( int $id ): string {
		$title = get_the_title( $id );
		return '' === $title ? '#' . $id : $title;
	}

	/* ---------------------------------------------------------------- Registration. */

	/**
	 * Register the bulk handler through ActionGuard (screen capability and nonce). Call it on init in the admin.
	 */
	public static function register_bulk_handler(): void {
		ActionGuard::register(
			static::bulk_action(),
			static::capability(),
			static function ( Request $request ): void {
				static::make()->handle_bulk( $request );
			}
		);
	}

	/**
	 * A new table. A subclass with constructor arguments overrides this.
	 */
	public static function make(): static {
		// @phpstan-ignore new.static
		return new static();
	}

	/* ---------------------------------------------------------------- Bulk processing. */

	/**
	 * The bulk actions the current user may run: key => definition.
	 *
	 * @return array<string, BulkDefinition>
	 */
	public function allowed_bulk_actions(): array {
		$out = array();
		foreach ( $this->bulk_action_definitions() as $key => $definition ) {
			if ( current_user_can( $definition['capability'] ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- each definition names a wiw_* capability.
				$out[ $key ] = $definition;
			}
		}
		return $out;
	}

	/**
	 * Run a bulk action from a request. Checks the nonce, the action and its capability, then calls the handler per id.
	 *
	 * @param Request $request Request: wiw_bulk (or wiw_bulk2), ids[], confirm, _wpnonce.
	 * @return array{status: string, action: string, done: int, skipped: int, reasons: array<int, string>, ids: array<int, int>}
	 */
	public function process_bulk( Request $request ): array {
		$result = array(
			'status'  => self::UNKNOWN,
			'action'  => '',
			'done'    => 0,
			'skipped' => 0,
			'reasons' => array(),
			'ids'     => array(),
		);
		if ( false === wp_verify_nonce( $request->nonce(), 'wiw_action_' . static::bulk_action() ) ) {
			$result['status'] = self::REFUSED_NONCE;
			return $result;
		}
		$key         = self::chosen_action( $request );
		$definitions = $this->bulk_action_definitions();
		if ( ! isset( $definitions[ $key ] ) ) {
			return $result;
		}
		$definition       = $definitions[ $key ];
		$result['action'] = $key;
		if ( ! current_user_can( static::capability() ) || ! current_user_can( $definition['capability'] ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- named wiw_* capabilities.
			$result['status'] = self::REFUSED_CAP;
			return $result;
		}
		$ids           = self::ids_from( $request );
		$result['ids'] = $ids;
		if ( array() === $ids ) {
			$result['status'] = self::NO_ROWS;
			return $result;
		}
		if ( self::needs_confirm( $definition ) && 1 !== $request->int( self::FIELD_CONFIRM ) ) {
			$result['status'] = self::NEEDS_CONFIRM;
			return $result;
		}
		foreach ( $ids as $id ) {
			try {
				$outcome = call_user_func( $definition['handler'], $id, $request );
			} catch ( \RuntimeException | \DomainException $e ) {
				$outcome = new \WP_Error( 'wiw_bulk_refused', $e->getMessage() );
			}
			if ( true === $outcome ) {
				++$result['done'];
				continue;
			}
			++$result['skipped'];
			$reason = $outcome instanceof \WP_Error ? $outcome->get_error_message() : '';
			$reason = '' === $reason ? __( 'not in a state that allows this', 'whoiswho' ) : $reason;
			if ( ! in_array( $reason, $result['reasons'], true ) && count( $result['reasons'] ) < 3 ) {
				$result['reasons'][] = $reason;
			}
		}
		$result['status'] = self::DONE;
		return $result;
	}

	/**
	 * The admin-post handler: process, keep the notice for the next page and go back (to the confirmation step when needed).
	 *
	 * @param Request $request Request.
	 */
	public function handle_bulk( Request $request ): void {
		$result = $this->process_bulk( $request );
		$back   = $this->back_url( $request );
		if ( self::NEEDS_CONFIRM === $result['status'] ) {
			wp_safe_redirect( $this->step_url( $result['action'], $result['ids'], $back ) );
			exit;
		}
		if ( self::UNKNOWN !== $result['status'] || '' !== self::chosen_action( $request ) ) {
			self::store_notice( $this->notice_for( $result ) );
		}
		wp_safe_redirect( add_query_arg( self::NOTICE_VAR, '1', $back ) );
		exit;
	}

	/**
	 * The notice for a result: type and text.
	 *
	 * @param array{status: string, action: string, done: int, skipped: int, reasons: array<int, string>, ids: array<int, int>} $result Result.
	 * @return array{type: string, text: string}
	 */
	public function notice_for( array $result ): array {
		switch ( $result['status'] ) {
			case self::REFUSED_NONCE:
				return array(
					'type' => 'error',
					'text' => __( 'The page had expired. Nothing was changed. Try again.', 'whoiswho' ),
				);
			case self::REFUSED_CAP:
				return array(
					'type' => 'error',
					'text' => __( 'You are not allowed to do that. Nothing was changed.', 'whoiswho' ),
				);
			case self::NO_ROWS:
				return array(
					'type' => 'warning',
					'text' => __( 'Select at least one row first.', 'whoiswho' ),
				);
			case self::DONE:
				break;
			default:
				return array(
					'type' => 'warning',
					'text' => __( 'Choose a bulk action first.', 'whoiswho' ),
				);
		}//end switch
		$definition = $this->bulk_action_definitions()[ $result['action'] ] ?? array();
		/* translators: %d: number of rows done. */
		$pattern = (string) ( $definition['done'] ?? __( '%d done', 'whoiswho' ) );
		$text    = sprintf( $pattern, $result['done'] );
		if ( $result['skipped'] > 0 ) {
			/* translators: 1: number skipped, 2: why. */
			$text .= ', ' . sprintf( __( '%1$d skipped (%2$s)', 'whoiswho' ), $result['skipped'], implode( '; ', $result['reasons'] ) );
		}
		return array(
			'type' => $result['skipped'] > 0 ? ( $result['done'] > 0 ? 'warning' : 'error' ) : 'success',
			'text' => $text . '.',
		);
	}

	/**
	 * The chosen bulk action: the top dropdown, else the bottom one.
	 *
	 * @param Request $request Request.
	 */
	private static function chosen_action( Request $request ): string {
		$key = $request->string( self::FIELD_ACTION );
		if ( '' === $key || '-1' === $key ) {
			$key = $request->string( self::FIELD_ACTION2 );
		}
		return '-1' === $key ? '' : sanitize_key( $key );
	}

	/**
	 * The ids: the checkboxes (ids[]) or the confirmation step's list (wiw_ids, comma separated). Positive, unique.
	 *
	 * @param Request $request Request.
	 * @return array<int, int>
	 */
	public static function ids_from( Request $request ): array {
		$ids = $request->ints( self::FIELD_IDS );
		if ( array() === $ids && '' !== $request->string( self::STEP_IDS ) ) {
			$ids = array_map( 'intval', explode( ',', $request->string( self::STEP_IDS ) ) );
		}
		return array_values( array_unique( array_filter( $ids, static fn ( int $id ): bool => $id > 0 ) ) );
	}

	/**
	 * Whether an action needs the confirmation (destructive, or extra fields).
	 *
	 * @param array<string, mixed> $definition Definition.
	 */
	private static function needs_confirm( array $definition ): bool {
		return ! empty( $definition['destructive'] ) || isset( $definition['fields'] );
	}

	/**
	 * Keep a notice for the current user's next page.
	 *
	 * @param array{type: string, text: string} $notice Notice.
	 */
	private static function store_notice( array $notice ): void {
		set_transient( self::NOTICE_VAR . '_' . get_current_user_id(), $notice, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Take the kept notice, once.
	 *
	 * @return array{type: string, text: string}|null
	 */
	private static function take_notice(): ?array {
		$key    = self::NOTICE_VAR . '_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) || ! isset( $notice['type'], $notice['text'] ) ) {
			return null;
		}
		delete_transient( $key );
		return array(
			'type' => (string) $notice['type'],
			'text' => (string) $notice['text'],
		);
	}

	/* ---------------------------------------------------------------- Addresses. */

	/**
	 * The list's address with the kept args.
	 *
	 * @param array<string, string|int> $args Extra args.
	 */
	public static function screen_url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => static::screen_slug() ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Where to go back after a bulk action: the posted back address when it is this list, else the list.
	 *
	 * @param Request $request Request.
	 */
	private function back_url( Request $request ): string {
		$fallback = static::screen_url();
		$back     = esc_url_raw( $request->string( self::FIELD_BACK ) );
		if ( '' === $back || 0 !== strpos( $back, admin_url( 'admin.php' ) ) ) {
			return $fallback;
		}
		$back = remove_query_arg( array( self::STEP_ACTION, self::STEP_IDS, self::NOTICE_VAR ), $back );
		return wp_validate_redirect( $back, $fallback );
	}

	/**
	 * The confirmation step of a bulk action for some ids (also usable as a row action for one row).
	 *
	 * @param string          $key  Bulk action key.
	 * @param array<int, int> $ids  Ids.
	 * @param string          $back The list address to come back to; the plain list by default.
	 */
	public function step_url( string $key, array $ids, string $back = '' ): string {
		$base = '' === $back ? static::screen_url() : $back;
		return add_query_arg(
			array(
				self::STEP_ACTION => $key,
				self::STEP_IDS    => implode( ',', array_map( 'intval', $ids ) ),
			),
			$base
		);
	}

	/* ---------------------------------------------------------------- Rendering. */

	/**
	 * The whole table, or the confirmation step when the address asks for one. Returns escaped markup.
	 *
	 * @param Request $request Request.
	 */
	public function html( Request $request ): string {
		$out    = '';
		$notice = '1' === $request->string( self::NOTICE_VAR ) ? self::take_notice() : null;
		if ( null !== $notice ) {
			$out .= '<div class="notice notice-' . esc_attr( $notice['type'] ) . ' is-dismissible wiw-list-notice" role="status"><p>' . esc_html( $notice['text'] ) . '</p></div>';
		}
		$step = sanitize_key( $request->string( self::STEP_ACTION ) );
		if ( '' !== $step && isset( $this->allowed_bulk_actions()[ $step ] ) ) {
			return $out . $this->step_html( $step, self::ids_from( $request ), $request );
		}
		$this->load( $request );
		ob_start();
		$this->display();
		$table = (string) ob_get_clean();
		$back  = static::screen_url( $this->screen_args( $request ) );
		$out  .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wiw-list-form" data-wiw-list="' . esc_attr( $this->_args['plural'] ) . '">';
		$out  .= $this->hidden_fields( $back ) . $table . '</form>';
		return $out;
	}

	/**
	 * Load one page of items and the pager.
	 *
	 * @param Request $request Request.
	 */
	public function load( Request $request ): void {
		$per_page    = max( 1, $this->per_page() );
		$page        = max( 1, $request->int( 'paged' ) );
		$found       = $this->fetch( $page, $per_page );
		$this->items = $found['items'];
		$this->total = $found['total'];
		$this->set_pagination_args(
			array(
				'total_items' => $this->total,
				'per_page'    => $per_page,
				'total_pages' => max( 1, (int) ceil( $this->total / $per_page ) ),
			)
		);
		$this->_column_headers = array( $this->get_columns(), array(), array(), $this->primary_column() );
	}

	/**
	 * The hidden fields of the bulk form.
	 *
	 * @param string $back    The list address to come back to.
	 * @param string $confirm The confirm field's value: 1 on the confirmation step.
	 */
	private function hidden_fields( string $back, string $confirm = '0' ): string {
		return '<input type="hidden" name="action" value="' . esc_attr( static::bulk_action() ) . '" />'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( static::bulk_action() ) ) . '" />'
			. '<input type="hidden" name="' . esc_attr( self::FIELD_BACK ) . '" value="' . esc_attr( $back ) . '" />'
			. '<input type="hidden" name="' . esc_attr( self::FIELD_CONFIRM ) . '" value="' . esc_attr( $confirm ) . '" class="wiw-list-confirm" />';
	}

	/**
	 * The confirmation step: what will happen, to which rows, the extra fields and the confirm button.
	 *
	 * @param string          $key     Bulk action key.
	 * @param array<int, int> $ids     Ids.
	 * @param Request         $request Request.
	 */
	private function step_html( string $key, array $ids, Request $request ): string {
		$definition = $this->allowed_bulk_actions()[ $key ];
		$back       = remove_query_arg( array( self::STEP_ACTION, self::STEP_IDS, self::NOTICE_VAR ), static::screen_url( $this->screen_args( $request ) ) );
		$out        = '<section class="wiw-card wiw-list-step" aria-labelledby="wiw-step-title"><h2 id="wiw-step-title" class="wiw-card__title">' . esc_html( $definition['label'] ) . '</h2>';
		if ( array() === $ids ) {
			return $out . '<p>' . esc_html__( 'Select at least one row first.', 'whoiswho' ) . '</p><p><a class="button" href="' . esc_url( $back ) . '">' . esc_html__( 'Back to the list', 'whoiswho' ) . '</a></p></section>';
		}
		/* translators: %d: number of rows. */
		$out  .= '<p class="wiw-card__desc">' . esc_html( sprintf( _n( 'This applies to %d row:', 'This applies to %d rows:', count( $ids ), 'whoiswho' ), count( $ids ) ) ) . '</p><ul class="wiw-list-step__names">';
		$shown = array_slice( $ids, 0, self::STEP_NAMES );
		foreach ( $shown as $id ) {
			$out .= '<li>' . esc_html( $this->name_for_id( $id ) ) . '</li>';
		}
		if ( count( $ids ) > count( $shown ) ) {
			/* translators: %d: number of rows not listed. */
			$out .= '<li>' . esc_html( sprintf( __( 'and %d more', 'whoiswho' ), count( $ids ) - count( $shown ) ) ) . '</li>';
		}
		$out .= '</ul><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wiw-form">' . $this->hidden_fields( $back, '1' );
		$out .= '<input type="hidden" name="' . esc_attr( self::FIELD_ACTION ) . '" value="' . esc_attr( $key ) . '" />';
		foreach ( $ids as $id ) {
			$out .= '<input type="hidden" name="' . esc_attr( self::FIELD_IDS ) . '[]" value="' . esc_attr( (string) $id ) . '" />';
		}
		if ( isset( $definition['fields'] ) ) {
			$out .= (string) call_user_func( $definition['fields'] );
		}
		$button = ! empty( $definition['destructive'] ) ? 'button button-primary wiw-button--danger' : 'button button-primary';
		$out   .= Form::actions( '<button type="submit" class="' . esc_attr( $button ) . '">' . esc_html( $definition['label'] ) . '</button> <a class="button" href="' . esc_url( $back ) . '">' . esc_html__( 'Cancel', 'whoiswho' ) . '</a>' );
		return $out . '</form></section>';
	}

	/**
	 * A status pill: short text with a tone (neutral, info, success, warning, danger).
	 *
	 * @param string $text Text.
	 * @param string $tone Tone.
	 */
	public static function pill( string $text, string $tone = 'neutral' ): string {
		$tone = in_array( $tone, array( 'neutral', 'info', 'success', 'warning', 'danger' ), true ) ? $tone : 'neutral';
		return '<span class="wiw-pill wiw-pill--' . esc_attr( $tone ) . '">' . esc_html( $text ) . '</span>';
	}

	/**
	 * The row actions the current user may use: key => escaped link markup.
	 *
	 * @param array<string, mixed> $item Item.
	 * @return array<string, string>
	 */
	public function visible_row_actions( array $item ): array {
		$out = array();
		foreach ( $this->row_actions_for( $item ) as $key => $action ) {
			if ( '' === $action['url'] || ! current_user_can( $action['capability'] ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- each action names a capability.
				continue;
			}
			$confirm     = isset( $action['confirm'] ) ? ' data-wiw-confirm="' . esc_attr( $action['confirm'] ) . '"' : '';
			$out[ $key ] = '<a href="' . esc_url( $action['url'] ) . '"' . $confirm . '>' . esc_html( $action['label'] ) . '</a>';
		}
		return $out;
	}

	/* ---------------------------------------------------------------- WP_List_Table hooks. */

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		$columns = $this->columns();
		if ( array() !== $this->allowed_bulk_actions() ) {
			$columns = array_merge( array( 'cb' => '<input type="checkbox" />' ), $columns );
		}
		return $columns;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_primary_column_name() {
		return $this->primary_column();
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepare_items(): void {
		$this->load( Request::from_globals() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function no_items(): void {
		echo '<span class="wiw-list-empty">' . esc_html( $this->empty_message() ) . '</span>';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function column_cb( $item ): string {
		$id = $this->item_id( (array) $item );
		if ( $id <= 0 ) {
			return '';
		}
		$dom = 'wiw-cb-' . $this->_args['plural'] . '-' . $id;
		/* translators: %s: row name. */
		return '<label class="screen-reader-text" for="' . esc_attr( $dom ) . '">' . esc_html( sprintf( __( 'Select %s', 'whoiswho' ), $this->item_name( (array) $item ) ) ) . '</label><input type="checkbox" id="' . esc_attr( $dom ) . '" name="' . esc_attr( self::FIELD_IDS ) . '[]" value="' . esc_attr( (string) $id ) . '" />';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item        Item.
	 * @param string               $column_name Column.
	 */
	protected function column_default( $item, $column_name ): string {
		return $this->column_html( (array) $item, (string) $column_name );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item        Item.
	 * @param string               $column_name Column.
	 * @param string               $primary     Primary column.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $column_name !== $primary ) {
			return '';
		}
		return $this->row_actions( $this->visible_row_actions( (array) $item ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $which top or bottom.
	 */
	protected function bulk_actions( $which = '' ): void {
		$allowed = $this->allowed_bulk_actions();
		if ( array() === $allowed ) {
			return;
		}
		$name = 'top' === $which ? self::FIELD_ACTION : self::FIELD_ACTION2;
		$id   = 'wiw-bulk-' . $this->_args['plural'] . '-' . ( 'top' === $which ? 'top' : 'bottom' );
		echo '<label for="' . esc_attr( $id ) . '" class="screen-reader-text">' . esc_html__( 'Bulk action', 'whoiswho' ) . '</label>';
		/* translators: 1: bulk action label, 2: number of rows selected. */
		$ask = __( '%1$s: %2$d selected. This changes every selected row. Continue?', 'whoiswho' );
		echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '" class="wiw-bulk-select" data-wiw-confirm-pattern="' . esc_attr( $ask ) . '"><option value="-1">' . esc_html__( 'Bulk actions', 'whoiswho' ) . '</option>';
		foreach ( $allowed as $key => $definition ) {
			$attrs  = ! empty( $definition['destructive'] ) ? ' data-wiw-destructive="1"' : '';
			$attrs .= isset( $definition['fields'] ) ? ' data-wiw-step="1"' : '';
			echo '<option value="' . esc_attr( $key ) . '"' . $attrs . '>' . esc_html( $definition['label'] ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed attributes.
		}
		echo '</select> <button type="submit" class="button action wiw-bulk-apply">' . esc_html__( 'Apply', 'whoiswho' ) . '</button>';
		if ( 'top' === $which ) {
			/* translators: %d: number of rows selected. */
			$many = __( '%d selected', 'whoiswho' );
			echo ' <span class="wiw-selected-count" aria-live="polite" data-wiw-selected-one="' . esc_attr__( '1 selected', 'whoiswho' ) . '" data-wiw-selected-many="' . esc_attr( $many ) . '"></span>';
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * The same as WordPress's, without its own nonce field (the form carries the ActionGuard nonce).
	 *
	 * @param string $which top or bottom.
	 */
	protected function display_tablenav( $which ): void {
		echo '<div class="tablenav ' . esc_attr( $which ) . ( 'top' === $which ? ' wiw-bulkbar' : '' ) . '">';
		if ( $this->has_items() ) {
			echo '<div class="alignleft actions bulkactions">';
			$this->bulk_actions( $which );
			echo '</div>';
		}
		$this->extra_tablenav( $which );
		$this->pagination( $which );
		echo '<br class="clear" /></div>';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<int, string>
	 */
	protected function get_table_classes() {
		return array( 'widefat', 'fixed', 'striped', 'table-view-list', 'wiw-list-table', 'wiw-list-table--' . $this->_args['plural'] );
	}
}
