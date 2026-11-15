<?php
/**
 * One award group's categories as a shared staff list table (REQ-200, REQ-266, REQ-275). The Award categories screen prints one
 * table per group, in the group's order. Row actions repeat the screen's single operations: Rename (opens the rename card), Move
 * up, Move down, Archive or Restore, and Remove (the confirmation step for that one row). Bulk actions repeat Archive, Restore
 * and Remove through the same CategoryService calls, so the capability is checked and the audit row written per category; a
 * category with nominations is skipped with the service's own reason. Rename and the order need one value per category and stay
 * single actions.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;

/**
 * Categories table for one group.
 */
final class CategoriesTable extends ListTable {

	public const BULK = 'wiw_award_categories_bulk';

	/**
	 * Group key.
	 *
	 * @var string
	 */
	private string $group;

	/**
	 * Service.
	 *
	 * @var CategoryService
	 */
	private CategoryService $categories;

	/**
	 * Constructor.
	 *
	 * @param string               $group      Group key; empty for the table that only processes bulk actions.
	 * @param CategoryService|null $categories Service; this site's by default.
	 */
	public function __construct( string $group = '', ?CategoryService $categories = null ) {
		$this->group      = $group;
		$this->categories = $categories ?? CategoryService::for_site();
		parent::__construct( 'category', 'award-categories' . ( '' === $group ? '' : '-' . sanitize_key( $group ) ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function bulk_action(): string {
		return self::BULK;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function capability(): string {
		return CategoriesScreen::CAP;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return CategoriesScreen::SCREEN;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'title'  => __( 'Category', 'whoiswho' ),
			'state'  => __( 'State', 'whoiswho' ),
			'order'  => __( 'Order', 'whoiswho' ),
			'in_use' => __( 'Nominations', 'whoiswho' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function primary_column(): string {
		return 'title';
	}

	/**
	 * Every category of the group on one page: a group holds a handful.
	 */
	protected function per_page(): int {
		return 200;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $page     Page, from 1.
	 * @param int $per_page Rows per page.
	 */
	protected function fetch( int $page, int $per_page ): array {
		$rows  = '' === $this->group ? array() : $this->categories->in_group( $this->group, true );
		$count = count( $rows );
		foreach ( $rows as $i => &$row ) {
			$row['position'] = $i + 1;
			$row['of']       = $count;
		}
		unset( $row );
		return array(
			'items' => array_slice( $rows, ( $page - 1 ) * $per_page, $per_page ),
			'total' => $count,
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_id( array $item ): int {
		return (int) $item['id'];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_name( array $item ): string {
		return (string) $item['title'];
	}

	/**
	 * The category title for the confirmation step.
	 *
	 * @param int $id Category.
	 */
	protected function name_for_id( int $id ): string {
		$row = $this->categories->find( $id );
		return null === $row ? '#' . $id : (string) $row['title'];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function empty_message(): string {
		return __( 'No categories in this group yet.', 'whoiswho' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item   Item.
	 * @param string               $column Column key.
	 */
	protected function column_html( array $item, string $column ): string {
		switch ( $column ) {
			case 'title':
				return '<strong class="row-title">' . esc_html( (string) $item['title'] ) . '</strong>';
			case 'state':
				return (int) $item['archived'] ? self::pill( __( 'Archived', 'whoiswho' ), 'neutral' ) : self::pill( __( 'Current', 'whoiswho' ), 'success' );
			case 'order':
				/* translators: 1: position, 2: number of categories in the group. */
				return esc_html( sprintf( __( '%1$d of %2$d', 'whoiswho' ), (int) $item['position'], (int) $item['of'] ) );
			case 'in_use':
				return esc_html( (string) $this->categories->nomination_count( (int) $item['id'] ) );
		}
		return '';
	}

	/**
	 * The address of one single operation, as a link: the screen's guarded action with its nonce.
	 *
	 * @param string $op Operation.
	 * @param int    $id Category.
	 */
	public static function op_url( string $op, int $id ): string {
		return add_query_arg(
			array(
				'action'      => CategoriesScreen::ACTION,
				'op'          => $op,
				'category_id' => $id,
				'_wpnonce'    => ActionGuard::nonce_for( CategoriesScreen::ACTION ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function row_actions_for( array $item ): array {
		$id       = (int) $item['id'];
		$archived = (bool) (int) $item['archived'];
		$actions  = array(
			'wiw-rename' => array(
				'label'      => __( 'Rename', 'whoiswho' ),
				'url'        => add_query_arg( 'rename', $id, self::screen_url() ) . '#wiw-rename',
				'capability' => CategoriesScreen::CAP,
			),
		);
		if ( (int) $item['position'] > 1 ) {
			$actions['wiw-up'] = array(
				'label'      => __( 'Move up', 'whoiswho' ),
				'url'        => self::op_url( 'up', $id ),
				'capability' => CategoriesScreen::CAP,
			);
		}
		if ( (int) $item['position'] < (int) $item['of'] ) {
			$actions['wiw-down'] = array(
				'label'      => __( 'Move down', 'whoiswho' ),
				'url'        => self::op_url( 'down', $id ),
				'capability' => CategoriesScreen::CAP,
			);
		}
		$actions[ $archived ? 'wiw-restore' : 'wiw-archive' ] = array(
			'label'      => $archived ? __( 'Restore', 'whoiswho' ) : __( 'Archive', 'whoiswho' ),
			'url'        => self::op_url( $archived ? 'restore' : 'archive', $id ),
			'capability' => CategoriesScreen::CAP,
		);
		$actions['wiw-remove']                                = array(
			'label'       => __( 'Remove', 'whoiswho' ),
			'url'         => $this->step_url( 'remove', array( $id ) ),
			'capability'  => CategoriesScreen::CAP,
			'destructive' => true,
		);
		return $actions;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function bulk_action_definitions(): array {
		$categories = $this->categories;
		$archive    = static function ( bool $archived ) use ( $categories ): callable {
			return static function ( int $id ) use ( $categories, $archived ) {
				$row = $categories->find( $id );
				if ( null !== $row && (int) $row['archived'] === (int) $archived ) {
					return new \WP_Error( 'wiw_category_unchanged', $archived ? __( 'already archived', 'whoiswho' ) : __( 'not archived', 'whoiswho' ) );
				}
				$categories->archive( $id, $archived );
				return true;
			};
		};
		return array(
			'archive' => array(
				'label'      => __( 'Archive', 'whoiswho' ),
				'capability' => CategoriesScreen::CAP,
				/* translators: %d: number of categories archived. */
				'done'       => __( '%d archived', 'whoiswho' ),
				'handler'    => $archive( true ),
			),
			'restore' => array(
				'label'      => __( 'Restore', 'whoiswho' ),
				'capability' => CategoriesScreen::CAP,
				/* translators: %d: number of categories restored. */
				'done'       => __( '%d restored', 'whoiswho' ),
				'handler'    => $archive( false ),
			),
			'remove'  => array(
				'label'       => __( 'Remove', 'whoiswho' ),
				'capability'  => CategoriesScreen::CAP,
				'destructive' => true,
				/* translators: %d: number of categories removed. */
				'done'        => __( '%d removed', 'whoiswho' ),
				'handler'     => static function ( int $id ) use ( $categories ): bool {
					$categories->remove( $id );
					return true;
				},
			),
		);
	}

	/**
	 * Keep nothing but the page slug when coming back: every group shows on one page.
	 *
	 * @param Request $request Request.
	 * @return array<string, string|int>
	 */
	protected function screen_args( Request $request ): array {
		unset( $request );
		return array();
	}

	/**
	 * No pager on a single page: this table shares its screen with other tables, and the pager uses fixed element ids.
	 */
	protected function pager_on_one_page(): bool {
		return false;
	}
}
