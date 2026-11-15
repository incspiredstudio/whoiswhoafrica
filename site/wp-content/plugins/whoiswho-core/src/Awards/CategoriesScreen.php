<?php
/**
 * The Award categories admin screen (REQ-200, REQ-266): categories under the five award groups, and nothing else. No eligibility
 * or criteria field lives here; that text is page copy. Needs wiw_manage_awards; every post is nonce-checked by ActionGuard. Each
 * group is a shared staff list table (CategoriesTable, REQ-275) with row quick actions and bulk Archive, Restore and Remove.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;

/**
 * Categories screen.
 */
final class CategoriesScreen {

	public const SCREEN = 'wiw-award-categories';
	public const ACTION = 'wiw_award_category';
	public const CAP    = 'wiw_manage_awards';
	public const OPS    = array( 'add', 'rename', 'up', 'down', 'archive', 'restore', 'remove' );

	/**
	 * Service.
	 *
	 * @var CategoryService
	 */
	private CategoryService $categories;

	/**
	 * Constructor.
	 *
	 * @param CategoryService $categories Service.
	 */
	public function __construct( CategoryService $categories ) {
		$this->categories = $categories;
	}

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
					__( 'Award categories', 'whoiswho' ),
					self::CAP,
					static function (): void {
						( new self( CategoryService::for_site() ) )->render();
					},
					false,
					'awards'
				);
				$registry->hook();
				ActionGuard::register(
					self::ACTION,
					self::CAP,
					static function ( Request $request ): void {
						$notice = ( new self( CategoryService::for_site() ) )->handle( $request );
						wp_safe_redirect( add_query_arg( 'wiw_notice', rawurlencode( $notice ), admin_url( 'admin.php?page=' . self::SCREEN ) ) );
						exit;
					}
				);
				CategoriesTable::register_bulk_handler();
			},
			1
		);
	}

	/**
	 * Run one operation (capability and nonce already checked by ActionGuard; the service checks the capability again).
	 *
	 * @param Request $request Request.
	 * @return string Message for the person.
	 */
	public function handle( Request $request ): string {
		$op = $request->string( 'op' );
		$id = $request->int( 'category_id' );
		try {
			switch ( $op ) {
				case 'add':
					$this->categories->add( $request->string( 'group' ), $request->string( 'title' ) );
					return 'Category added.';
				case 'rename':
					$this->categories->rename( $id, $request->string( 'title' ) );
					return 'Category renamed.';
				case 'up':
				case 'down':
					$this->move( $id, 'up' === $op ? -1 : 1 );
					return 'Order saved.';
				case 'archive':
				case 'restore':
					$this->categories->archive( $id, 'archive' === $op );
					return 'archive' === $op ? 'Category archived.' : 'Category restored.';
				case 'remove':
					$this->categories->remove( $id );
					return 'Category removed.';
			}
		} catch ( \DomainException $refusal ) {
			return $refusal->getMessage();
		}//end try
		return 'Nothing was changed.';
	}

	/**
	 * Move a category one place within its group.
	 *
	 * @param int $id    Category.
	 * @param int $delta -1 up, 1 down.
	 * @throws \DomainException When the category is unknown.
	 */
	private function move( int $id, int $delta ): void {
		$row = $this->categories->find( $id );
		if ( null === $row ) {
			throw new \DomainException( 'That category does not exist.' );
		}
		$ids = array_column( $this->categories->in_group( (string) $row['group_key'], true ), 'id' );
		$at  = array_search( $id, $ids, true );
		$to  = false === $at ? -1 : $at + $delta;
		if ( $to < 0 || $to >= count( $ids ) ) {
			return;
		}
		$swap       = $ids[ $to ];
		$ids[ $to ] = $id;
		$ids[ $at ] = $swap;
		$this->categories->reorder( (string) $row['group_key'], $ids );
	}

	/**
	 * The hidden fields of one operation's form.
	 *
	 * @param string               $op     Operation.
	 * @param array<string, mixed> $fields Hidden fields.
	 */
	private function hidden( string $op, array $fields ): string {
		$out  = '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		$out .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />';
		$out .= '<input type="hidden" name="op" value="' . esc_attr( $op ) . '" />';
		foreach ( $fields as $name => $value ) {
			$out .= '<input type="hidden" name="' . esc_attr( (string) $name ) . '" value="' . esc_attr( (string) $value ) . '" />';
		}
		return $out;
	}

	/**
	 * The screen markup: one card per award group, each with its categories table and an Add category form, and the rename card
	 * when a Rename row action asked for it. A confirmation step of a table shows on its own.
	 *
	 * @param string       $notice  Message to show after an action.
	 * @param Request|null $request Request: rename, and the tables' notice and confirmation step.
	 * @throws \DomainException When the user may not manage awards.
	 */
	public function html( string $notice = '', ?Request $request = null ): string {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'You are not allowed to manage award categories.' );
		}
		$request = $request ?? new Request( array() );
		$out     = '<div class="wrap wiw-staff"><h1>' . esc_html__( 'Award categories', 'whoiswho' ) . '</h1>';
		if ( '' !== $notice ) {
			$out .= '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
		}
		if ( '' !== $request->string( ListTable::STEP_ACTION ) ) {
			return $out . ( new CategoriesTable( '', $this->categories ) )->html( $request ) . '</div>';
		}
		$out .= '<p class="wiw-lead">' . esc_html__( 'Categories sit under the five award groups. A category with nominations cannot be removed; archive it instead. Eligibility and judging criteria are page copy, edited in the page editor.', 'whoiswho' ) . '</p>';
		$out .= $this->rename_html( $request->int( 'rename' ) );
		$list = new Request( array_filter( array( ListTable::NOTICE_VAR => $request->string( ListTable::NOTICE_VAR ) ) ) );
		foreach ( $this->categories->grouped( true ) as $key => $group ) {
			$add  = Form::open( $this->hidden( 'add', array( 'group' => $key ) ), array( 'class' => 'wiw-inline-fields wiw-add-category' ) );
			$add .= Form::text(
				'title',
				__( 'New category', 'whoiswho' ),
				'',
				array(
					'id'        => 'wiw-f-title-' . sanitize_key( (string) $key ),
					'maxlength' => CategoryService::MAX_TITLE,
				)
			);
			$add .= '<div class="wiw-inline-fields__action"><button type="submit" class="button">' . esc_html__( 'Add category', 'whoiswho' ) . '</button></div></form>';
			$out .= Form::section(
				(string) $group['label'],
				( new CategoriesTable( (string) $key, $this->categories ) )->html( $list ) . Form::section(
					__( 'Add a category', 'whoiswho' ),
					$add,
					array(
						'level' => 3,
						'class' => 'wiw-card--inset',
					)
				),
				array( 'id' => 'wiw-group-' . sanitize_key( (string) $key ) )
			);
			// The list notice is shown once, above the first group.
			$list = new Request( array() );
		}//end foreach
		return $out . '</div>';
	}

	/**
	 * The rename card for one category.
	 *
	 * @param int $id Category, or 0 for none.
	 */
	private function rename_html( int $id ): string {
		$row = $id > 0 ? $this->categories->find( $id ) : null;
		if ( null === $row ) {
			return '';
		}
		$body  = Form::open( $this->hidden( 'rename', array( 'category_id' => $id ) ) );
		$body .= Form::text(
			'title',
			__( 'New name', 'whoiswho' ),
			(string) $row['title'],
			array(
				'id'        => 'wiw-f-rename-title',
				'maxlength' => CategoryService::MAX_TITLE,
				'required'  => true,
			)
		);
		$body .= Form::actions( '<button type="submit" class="button button-primary">' . esc_html__( 'Rename', 'whoiswho' ) . '</button> <a class="button" href="' . esc_url( CategoriesTable::screen_url() ) . '">' . esc_html__( 'Cancel', 'whoiswho' ) . '</a>' ) . '</form>';
		/* translators: %s: category name. */
		return Form::section( sprintf( __( 'Rename %s', 'whoiswho' ), (string) $row['title'] ), $body, array( 'id' => 'wiw-rename' ) );
	}

	/**
	 * Print the screen.
	 */
	public function render(): void {
		$request = Request::from_globals();
		echo $this->html( $request->string( 'wiw_notice' ), $request ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
	}
}
