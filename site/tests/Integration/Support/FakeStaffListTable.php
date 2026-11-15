<?php
/**
 * A small list table for the ListTable base tests (REQ-275): three fixed rows, a plain bulk action that skips one id, a destructive
 * one and one with a confirmation field. Records which ids each handler saw.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Support;

use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;

final class FakeStaffListTable extends ListTable {

	public const SCREEN_CAP = 'wiw_fake_screen';
	public const TOUCH_CAP  = 'wiw_fake_touch';
	public const WIPE_CAP   = 'wiw_fake_wipe';
	public const VIEW_CAP   = 'wiw_fake_view';

	/**
	 * Ids each handler saw: action => ids.
	 *
	 * @var array<string, array<int, int>>
	 */
	public static array $seen = array();

	/**
	 * Rows.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static array $rows = array();

	public function __construct() {
		parent::__construct( 'thing', 'things' );
	}

	public static function bulk_action(): string {
		return 'wiw_fake_bulk';
	}

	public static function capability(): string {
		return self::SCREEN_CAP;
	}

	public static function screen_slug(): string {
		return 'wiw-fake-list';
	}

	protected function columns(): array {
		return array(
			'name'  => 'Name',
			'state' => 'State',
		);
	}

	protected function primary_column(): string {
		return 'name';
	}

	protected function fetch( int $page, int $per_page ): array {
		return array(
			'items' => array_slice( self::$rows, ( $page - 1 ) * $per_page, $per_page ),
			'total' => count( self::$rows ),
		);
	}

	protected function item_id( array $item ): int {
		return (int) $item['id'];
	}

	protected function item_name( array $item ): string {
		return (string) $item['name'];
	}

	protected function name_for_id( int $id ): string {
		foreach ( self::$rows as $row ) {
			if ( (int) $row['id'] === $id ) {
				return (string) $row['name'];
			}
		}
		return '#' . $id;
	}

	protected function column_html( array $item, string $column ): string {
		return 'state' === $column ? self::pill( (string) $item['state'], 'info' ) : esc_html( (string) $item['name'] );
	}

	protected function empty_message(): string {
		return 'No fake things.';
	}

	protected function row_actions_for( array $item ): array {
		return array(
			'view' => array(
				'label'      => 'View',
				'url'        => 'https://example.test/thing/' . (int) $item['id'],
				'capability' => self::VIEW_CAP,
			),
			'wipe' => array(
				'label'      => 'Wipe',
				'url'        => $this->step_url( 'wipe', array( (int) $item['id'] ) ),
				'capability' => self::WIPE_CAP,
			),
		);
	}

	protected function bulk_action_definitions(): array {
		$record = static function ( string $key ): callable {
			return static function ( int $id ) use ( $key ) {
				self::$seen[ $key ][] = $id;
				return 2 === $id ? new \WP_Error( 'wiw_fake', 'not in a state that allows this' ) : true;
			};
		};
		return array(
			'touch' => array(
				'label'      => 'Touch',
				'capability' => self::TOUCH_CAP,
				'handler'    => $record( 'touch' ),
				'done'       => '%d touched',
			),
			'wipe'  => array(
				'label'       => 'Wipe',
				'capability'  => self::WIPE_CAP,
				'destructive' => true,
				'handler'     => $record( 'wipe' ),
				'done'        => '%d wiped',
			),
			'note'  => array(
				'label'      => 'Note',
				'capability' => self::TOUCH_CAP,
				'handler'    => static function ( int $id, Request $request ) {
					self::$seen['note'][] = $id . ':' . $request->string( 'note' );
					return true;
				},
				'fields'     => static fn (): string => Form::text( 'note', 'Note', '', array( 'required' => true ) ),
			),
		);
	}
}
