<?php
/**
 * The shared staff list table (REQ-275): bulk processing refuses a bad nonce and a missing capability, runs the handler once per
 * selected item and counts the skipped ones, and asks for confirmation before a destructive action or one with fields; the bulk
 * dropdown and the row actions show only what the user may use; the empty state shows. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Tests\Integration\Support\FakeStaffListTable;
use WhoIsWho\Tests\WpTestCase;

final class StaffListTableTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		FakeStaffListTable::$seen = array();
		FakeStaffListTable::$rows = array(
			array(
				'id'    => 1,
				'name'  => 'Alpha <b>Thing</b>',
				'state' => 'pending',
			),
			array(
				'id'    => 2,
				'name'  => 'Beta Thing',
				'state' => 'reviewed',
			),
			array(
				'id'    => 3,
				'name'  => 'Gamma Thing',
				'state' => 'pending',
			),
		);
	}

	protected function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * A user holding the given capabilities.
	 *
	 * @param string[] $caps Capabilities.
	 */
	private function user_with( array $caps ): int {
		$id   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$user = get_userdata( $id );
		foreach ( $caps as $cap ) {
			$user->add_cap( $cap );
		}
		wp_set_current_user( $id );
		return $id;
	}

	/**
	 * A bulk request with a valid nonce.
	 *
	 * @param array<string, mixed> $values Values.
	 */
	private function request( array $values ): Request {
		return new Request( array_merge( array( '_wpnonce' => ActionGuard::nonce_for( FakeStaffListTable::bulk_action() ) ), $values ) );
	}

	public function test_REQ_275_bulk_refuses_a_bad_nonce(): void {
		$this->user_with( array( FakeStaffListTable::SCREEN_CAP, FakeStaffListTable::TOUCH_CAP ) );
		$result = ( new FakeStaffListTable() )->process_bulk(
			new Request(
				array(
					'_wpnonce'              => 'bad',
					ListTable::FIELD_ACTION => 'touch',
					ListTable::FIELD_IDS    => array( 1, 3 ),
				)
			)
		);
		$this->assertSame( ListTable::REFUSED_NONCE, $result['status'] );
		$this->assertSame( array(), FakeStaffListTable::$seen, 'no handler runs' );
	}

	public function test_REQ_275_bulk_refuses_without_the_action_capability(): void {
		$this->user_with( array( FakeStaffListTable::SCREEN_CAP ) );
		$result = ( new FakeStaffListTable() )->process_bulk(
			$this->request(
				array(
					ListTable::FIELD_ACTION => 'touch',
					ListTable::FIELD_IDS    => array( 1 ),
				)
			)
		);
		$this->assertSame( ListTable::REFUSED_CAP, $result['status'] );
		$this->assertSame( array(), FakeStaffListTable::$seen );

		$this->user_with( array( FakeStaffListTable::TOUCH_CAP ) );
		$result = ( new FakeStaffListTable() )->process_bulk(
			$this->request(
				array(
					ListTable::FIELD_ACTION => 'touch',
					ListTable::FIELD_IDS    => array( 1 ),
				)
			)
		);
		$this->assertSame( ListTable::REFUSED_CAP, $result['status'], 'the screen capability is needed too' );
		$this->assertSame( array(), FakeStaffListTable::$seen );
	}

	public function test_REQ_275_bulk_runs_the_handler_per_item_and_counts_skipped(): void {
		$this->user_with( array( FakeStaffListTable::SCREEN_CAP, FakeStaffListTable::TOUCH_CAP ) );
		$table  = new FakeStaffListTable();
		$result = $table->process_bulk(
			$this->request(
				array(
					ListTable::FIELD_ACTION  => '-1',
					ListTable::FIELD_ACTION2 => 'touch',
					ListTable::FIELD_IDS     => array( 1, 2, 3, 3, 0, -4 ),
				)
			)
		);
		$this->assertSame( ListTable::DONE, $result['status'] );
		$this->assertSame( array( 1, 2, 3 ), FakeStaffListTable::$seen['touch'], 'once per id, duplicates and bad ids dropped' );
		$this->assertSame( 2, $result['done'] );
		$this->assertSame( 1, $result['skipped'] );
		$notice = $table->notice_for( $result );
		$this->assertSame( '2 touched, 1 skipped (not in a state that allows this).', $notice['text'] );
		$this->assertSame( 'warning', $notice['type'] );
		$this->assertStringNotContainsString( 'Beta', $notice['text'], 'no names in the notice' );
	}

	public function test_REQ_275_bulk_with_no_rows_or_no_action_does_nothing(): void {
		$this->user_with( array( FakeStaffListTable::SCREEN_CAP, FakeStaffListTable::TOUCH_CAP ) );
		$table = new FakeStaffListTable();
		$this->assertSame( ListTable::NO_ROWS, $table->process_bulk( $this->request( array( ListTable::FIELD_ACTION => 'touch' ) ) )['status'] );
		$this->assertSame( ListTable::UNKNOWN, $table->process_bulk( $this->request( array( ListTable::FIELD_IDS => array( 1 ) ) ) )['status'] );
		$this->assertSame( ListTable::UNKNOWN, $table->process_bulk( $this->request( array( ListTable::FIELD_ACTION => 'explode' ) ) )['status'] );
		$this->assertSame( array(), FakeStaffListTable::$seen );
	}

	public function test_REQ_275_destructive_bulk_needs_confirmation(): void {
		$this->user_with( array( FakeStaffListTable::SCREEN_CAP, FakeStaffListTable::WIPE_CAP ) );
		$table  = new FakeStaffListTable();
		$values = array(
			ListTable::FIELD_ACTION => 'wipe',
			ListTable::FIELD_IDS    => array( 1, 3 ),
		);
		$result = $table->process_bulk( $this->request( $values ) );
		$this->assertSame( ListTable::NEEDS_CONFIRM, $result['status'] );
		$this->assertSame( array(), FakeStaffListTable::$seen, 'nothing runs before the confirmation' );
		$step = $table->step_url( 'wipe', $result['ids'] );
		$this->assertStringContainsString( ListTable::STEP_ACTION . '=wipe', $step );
		$this->assertStringContainsString( ListTable::STEP_IDS . '=1,3', $step );

		$result = $table->process_bulk( $this->request( array_merge( $values, array( ListTable::FIELD_CONFIRM => '1' ) ) ) );
		$this->assertSame( ListTable::DONE, $result['status'] );
		$this->assertSame( array( 1, 3 ), FakeStaffListTable::$seen['wipe'] );
	}

	public function test_REQ_275_the_confirmation_step_lists_rows_and_carries_the_fields(): void {
		$this->user_with( array( FakeStaffListTable::SCREEN_CAP, FakeStaffListTable::TOUCH_CAP ) );
		$html = ( new FakeStaffListTable() )->html(
			new Request(
				array(
					ListTable::STEP_ACTION => 'note',
					ListTable::STEP_IDS    => '1,3',
				)
			)
		);
		$this->assertStringContainsString( 'Alpha &lt;b&gt;Thing&lt;/b&gt;', $html );
		$this->assertStringContainsString( 'Gamma Thing', $html );
		$this->assertStringNotContainsString( 'Beta Thing', $html );
		$this->assertStringContainsString( 'name="note"', $html, 'the extra field' );
		$this->assertStringContainsString( 'name="confirm" value="1"', $html );
		$this->assertStringContainsString( 'name="ids[]" value="3"', $html );

		$result = ( new FakeStaffListTable() )->process_bulk(
			$this->request(
				array(
					ListTable::FIELD_ACTION  => 'note',
					ListTable::FIELD_IDS     => array( 3 ),
					ListTable::FIELD_CONFIRM => '1',
					'note'                   => 'shared reason',
				)
			)
		);
		$this->assertSame( ListTable::DONE, $result['status'] );
		$this->assertSame( array( '3:shared reason' ), FakeStaffListTable::$seen['note'] );
	}

	public function test_REQ_275_dropdown_and_row_actions_show_only_what_the_user_may_use(): void {
		$this->user_with( array( FakeStaffListTable::SCREEN_CAP, FakeStaffListTable::TOUCH_CAP ) );
		$table = new FakeStaffListTable();
		$this->assertSame( array( 'touch', 'note' ), array_keys( $table->allowed_bulk_actions() ) );
		$html = $table->html( new Request( array() ) );
		$this->assertStringContainsString( '<option value="touch">Touch</option>', $html );
		$this->assertStringNotContainsString( 'value="wipe"', $html, 'no destructive action without its capability' );
		$this->assertStringNotContainsString( '>View<', $html, 'a row action without its capability is hidden' );
		$this->assertStringNotContainsString( '>Wipe<', $html );
		$this->assertStringContainsString( 'name="ids[]" value="1"', $html, 'the checkbox column' );
		$this->assertStringContainsString( 'Select Alpha &lt;b&gt;Thing&lt;/b&gt;', $html, 'each checkbox has a label' );
		$this->assertStringContainsString( 'name="action" value="wiw_fake_bulk"', $html );
		$this->assertStringContainsString( 'wiw-pill', $html );
		$this->assertSame( 1, substr_count( $html, 'name="_wpnonce"' ), 'one nonce, the ActionGuard one' );

		$this->user_with( array( FakeStaffListTable::SCREEN_CAP, FakeStaffListTable::VIEW_CAP, FakeStaffListTable::WIPE_CAP ) );
		$html = ( new FakeStaffListTable() )->html( new Request( array() ) );
		$this->assertStringContainsString( '>View<', $html );
		$this->assertStringContainsString( '>Wipe<', $html );
		$this->assertStringContainsString( 'data-wiw-destructive="1"', $html, 'the browser asks before a destructive action' );
		$this->assertStringContainsString( 'class="row-actions"', $html );

		$this->user_with( array( FakeStaffListTable::SCREEN_CAP ) );
		$html = ( new FakeStaffListTable() )->html( new Request( array() ) );
		$this->assertStringNotContainsString( 'name="ids[]"', $html, 'no checkboxes when no bulk action is allowed' );
		$this->assertStringNotContainsString( 'wiw-bulk-select', $html );
	}

	public function test_REQ_275_empty_state_and_paging(): void {
		$this->user_with( array( FakeStaffListTable::SCREEN_CAP ) );
		FakeStaffListTable::$rows = array();
		$html                     = ( new FakeStaffListTable() )->html( new Request( array() ) );
		$this->assertStringContainsString( 'No fake things.', $html );

		for ( $i = 1; $i <= 45; $i++ ) {
			FakeStaffListTable::$rows[] = array(
				'id'    => $i,
				'name'  => 'Row ' . $i,
				'state' => 'x',
			);
		}
		$html = ( new FakeStaffListTable() )->html( new Request( array( 'paged' => 3 ) ) );
		$this->assertStringContainsString( 'Row 41', $html );
		$this->assertStringNotContainsString( 'Row 40<', $html, '20 a page' );
	}
}
