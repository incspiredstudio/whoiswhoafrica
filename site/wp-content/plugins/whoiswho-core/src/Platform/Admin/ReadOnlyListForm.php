<?php
/**
 * For read-only staff lists built on the shared list table (REQ-275): logs and other lists with no bulk actions. The table is
 * printed inside a GET form to the list's own screen instead of the kit's POST bulk form, so the page carries no POST form at all
 * and typing a page number in the pager opens that page. The form keeps the screen slug and the list's filters (screen_args()).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Admin;

/**
 * Read-only list form, for ListTable subclasses.
 */
trait ReadOnlyListForm {

	/**
	 * The table inside a GET form that keeps the page and the filters. Returns escaped markup.
	 *
	 * @param Request $request Request.
	 */
	public function html( Request $request ): string {
		$this->load( $request );
		ob_start();
		$this->display();
		$table = (string) ob_get_clean();
		$keep  = array_merge( array( 'page' => static::screen_slug() ), $this->screen_args( $request ) );
		unset( $keep['paged'] );
		$hidden = '';
		foreach ( $keep as $name => $value ) {
			$hidden .= '<input type="hidden" name="' . esc_attr( (string) $name ) . '" value="' . esc_attr( (string) $value ) . '" />';
		}
		return '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="wiw-list-form wiw-list-form--read-only">' . $hidden . $table . '</form>';
	}
}
