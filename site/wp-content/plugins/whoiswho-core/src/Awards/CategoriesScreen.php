<?php
/**
 * The Award categories admin screen (REQ-200, REQ-266): categories under the five award groups, and nothing else. No eligibility
 * or criteria field lives here; that text is page copy. Needs wiw_manage_awards; every post is nonce-checked by ActionGuard.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;

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
	 * A small post form for one operation.
	 *
	 * @param string               $op     Operation.
	 * @param string               $label  Button label.
	 * @param array<string, mixed> $fields Hidden fields.
	 * @param string               $extra  Extra inner markup (already escaped).
	 */
	private function form( string $op, string $label, array $fields, string $extra = '' ): string {
		$out  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		$out .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />';
		$out .= '<input type="hidden" name="op" value="' . esc_attr( $op ) . '" />';
		foreach ( $fields as $name => $value ) {
			$out .= '<input type="hidden" name="' . esc_attr( (string) $name ) . '" value="' . esc_attr( (string) $value ) . '" />';
		}
		return $out . $extra . '<button type="submit" class="button">' . esc_html( $label ) . '</button></form> ';
	}

	/**
	 * The screen markup.
	 *
	 * @param string $notice Message to show after an action.
	 * @throws \DomainException When the user may not manage awards.
	 */
	public function html( string $notice = '' ): string {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'You are not allowed to manage award categories.' );
		}
		$out = '<div class="wrap"><h1>' . esc_html__( 'Award categories', 'whoiswho' ) . '</h1>';
		if ( '' !== $notice ) {
			$out .= '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
		}
		$out .= '<p>' . esc_html__( 'Categories sit under the five award groups. A category with nominations cannot be removed; archive it instead. Eligibility and judging criteria are page copy, edited in the page editor.', 'whoiswho' ) . '</p>';
		foreach ( $this->categories->grouped( true ) as $key => $group ) {
			$out .= '<h2>' . esc_html( $group['label'] ) . '</h2><table class="widefat striped"><tbody>';
			foreach ( $group['categories'] as $category ) {
				$id     = (int) $category['id'];
				$fields = array( 'category_id' => $id );
				$title  = '<input type="text" name="title" value="' . esc_attr( (string) $category['title'] ) . '" maxlength="' . (int) CategoryService::MAX_TITLE . '" class="regular-text" />';
				$out   .= '<tr><td>' . $this->form( 'rename', __( 'Rename', 'whoiswho' ), $fields, $title );
				$out   .= ( (int) $category['archived'] ? '<em>' . esc_html__( 'Archived', 'whoiswho' ) . '</em>' : '' ) . '</td><td>';
				$out   .= $this->form( 'up', __( 'Move up', 'whoiswho' ), $fields ) . $this->form( 'down', __( 'Move down', 'whoiswho' ), $fields );
				$out   .= (int) $category['archived'] ? $this->form( 'restore', __( 'Restore', 'whoiswho' ), $fields ) : $this->form( 'archive', __( 'Archive', 'whoiswho' ), $fields );
				$out   .= $this->form( 'remove', __( 'Remove', 'whoiswho' ), $fields ) . '</td></tr>';
			}
			$new  = '<input type="text" name="title" placeholder="' . esc_attr__( 'New category', 'whoiswho' ) . '" maxlength="' . (int) CategoryService::MAX_TITLE . '" class="regular-text" />';
			$out .= '<tr><td colspan="2">' . $this->form( 'add', __( 'Add category', 'whoiswho' ), array( 'group' => $key ), $new ) . '</td></tr></tbody></table>';
		}
		return $out . '</div>';
	}

	/**
	 * Print the screen.
	 */
	public function render(): void {
		$request = Request::from_globals();
		echo wp_kses(
			$this->html( $request->string( 'wiw_notice' ) ),
			array(
				'div'    => array( 'class' => true ),
				'h1'     => array(),
				'h2'     => array(),
				'p'      => array(),
				'em'     => array(),
				'table'  => array( 'class' => true ),
				'tbody'  => array(),
				'tr'     => array(),
				'td'     => array( 'colspan' => true ),
				'form'   => array(
					'method' => true,
					'action' => true,
					'style'  => true,
				),
				'input'  => array(
					'type'        => true,
					'name'        => true,
					'value'       => true,
					'maxlength'   => true,
					'class'       => true,
					'placeholder' => true,
				),
				'button' => array(
					'type'  => true,
					'class' => true,
				),
			)
		);
	}
}
