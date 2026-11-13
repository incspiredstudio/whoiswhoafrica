<?php
/**
 * The Home page settings screen (REQ-257, REQ-244, REQ-051). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Curation;

use WhoIsWho\Core\Content\Curation\Admin\CurationScreen;
use WhoIsWho\Core\Content\Curation\CurationSettings;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class CurationScreenTest extends WpTestCase {

	private const PROBE = 'wiw_curation_screen_audit_probe';

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
	}

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		foreach ( CurationSettings::BLOCKS as $block ) {
			delete_option( CurationSettings::OPTION_PREFIX . $block );
		}
	}

	protected function tearDown(): void {
		foreach ( CurationSettings::BLOCKS as $block ) {
			delete_option( CurationSettings::OPTION_PREFIX . $block );
		}
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function screen_as( string $role ): CurationScreen {
		$user = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user );
		$audit = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00', static fn (): int => $user );
		return new CurationScreen( new CurationSettings( $audit ) );
	}

	public function test_REQ_257_settings_screen_403_without_capability(): void {
		$registry = new ScreenRegistry();
		$registry->register( CurationScreen::SCREEN, 'Home page settings', CurationScreen::CAP, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), CurationScreen::CAP, CurationScreen::CAP );
		AuthorisationMatrix::assert_statuses(
			$this,
			CurationScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 403,
				'editor'        => 200,
				'administrator' => 200,
				'granted'       => 200,
				'revoked'       => 403,
			)
		);
		$this->expectException( \DomainException::class );
		$this->screen_as( 'wiw_reviewer' )->html();
	}

	public function test_REQ_244_wiw_curate_home_is_grantable(): void {
		$reviewer = self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) );
		$this->assertFalse( user_can( $reviewer, CurationScreen::CAP ) );
		( new \WP_User( $reviewer ) )->add_cap( CurationScreen::CAP );
		$this->assertTrue( user_can( $reviewer, CurationScreen::CAP ) );
		wp_set_current_user( $reviewer );
		$audit  = new AuditLogger( new WpdbConnection( self::PROBE ) );
		$screen = new CurationScreen( new CurationSettings( $audit ) );
		$this->assertStringContainsString( 'On the record', $screen->html() );
	}

	public function test_REQ_257_save_writes_audit_row(): void {
		global $wpdb;
		$screen  = $this->screen_as( 'wiw_editor' );
		$message = $screen->handle(
			new Request(
				array(
					'block'          => CurationSettings::STORIES_HOME,
					'mode'           => 'random',
					'count'          => '4',
					'fill'           => 'latest',
					'featured_first' => '1',
					'pinned'         => '',
					'reason'         => 'Spring refresh',
				)
			)
		);
		$this->assertSame( 'Saved.', $message );
		$rows = (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE, ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		$this->assertCount( 1, $rows );
		$this->assertSame( 'curation.change', $rows[0]['action'] );
		$this->assertSame( CurationSettings::STORIES_HOME, $rows[0]['subject_id'] );
		$this->assertNotSame( '0', (string) $rows[0]['actor_id'] );
		$this->assertStringContainsString( '"mode":"random"', (string) $rows[0]['new_value'] );
		$this->assertStringContainsString( '"mode":"latest"', (string) $rows[0]['old_value'] );
		$this->assertSame( 'Spring refresh', $rows[0]['reason'] );
		$this->assertSame( 'random', ( new CurationSettings( new AuditLogger( new WpdbConnection( self::PROBE ) ) ) )->get( CurationSettings::STORIES_HOME )['mode'] );
	}

	public function test_REQ_257_an_invalid_save_reports_the_problem_and_changes_nothing(): void {
		$screen  = $this->screen_as( 'wiw_editor' );
		$message = $screen->handle(
			new Request(
				array(
					'block' => CurationSettings::ON_THE_RECORD,
					'mode'  => 'random',
					'count' => '99',
					'fill'  => 'latest',
				)
			)
		);
		$this->assertStringContainsString( 'Count must be', $message );
		$this->assertFalse( get_option( CurationSettings::OPTION_PREFIX . CurationSettings::ON_THE_RECORD, false ) );
		$this->assertSame( 'Unknown block.', $screen->handle( new Request( array( 'block' => 'nope' ) ) ) );
	}

	/**
	 * Create a story post.
	 *
	 * @param string $title  Title.
	 * @param string $status Post status.
	 */
	private function story( string $title, string $status = 'publish' ): int {
		return (int) self::factory()->post->create(
			array(
				'post_title'  => $title,
				'post_status' => $status,
			)
		);
	}

	public function test_REQ_257_a_save_cannot_add_a_pin_that_is_not_public(): void {
		$screen  = $this->screen_as( 'wiw_editor' );
		$draft   = $this->story( 'Pin Draft Story', 'draft' );
		$live    = $this->story( 'Pin Live Story' );
		$input   = array(
			'block' => CurationSettings::STORIES_HOME,
			'mode'  => 'manual',
			'count' => '3',
			'fill'  => 'latest',
		);
		$message = $screen->handle( new Request( array_merge( $input, array( 'pinned' => (string) $draft ) ) ) );
		$this->assertStringContainsString( 'cannot be pinned', $message );
		$this->assertFalse( get_option( CurationSettings::OPTION_PREFIX . CurationSettings::STORIES_HOME, false ), 'a refused save changes nothing' );
		$this->assertSame( 'Saved.', $screen->handle( new Request( array_merge( $input, array( 'pinned' => (string) $live ) ) ) ) );
	}

	public function test_REQ_257_pin_actions_add_move_and_remove_in_the_chosen_order(): void {
		$screen = $this->screen_as( 'wiw_editor' );
		$one    = $this->story( 'Pin One' );
		$two    = $this->story( 'Pin Two' );
		$three  = $this->story( 'Pin Three' );
		$draft  = $this->story( 'Pin Hidden', 'draft' );
		$block  = CurationSettings::STORIES_PAGE;
		$pins   = static fn (): array => get_option( CurationSettings::OPTION_PREFIX . $block )['pinned'];
		$do     = static fn ( string $op, int $id ): array => array(
			'block' => $block,
			'op'    => $op,
			'id'    => (string) $id,
		);
		foreach ( array( $one, $two, $three ) as $id ) {
			$this->assertSame( 'Saved.', $screen->handle_pin( new Request( $do( 'add', $id ) ) ) );
		}
		$this->assertSame( array( $one, $two, $three ), $pins() );
		$this->assertSame( 'Already pinned.', $screen->handle_pin( new Request( $do( 'add', $two ) ) ) );
		$this->assertStringContainsString( 'cannot be pinned', $screen->handle_pin( new Request( $do( 'add', $draft ) ) ) );
		$screen->handle_pin( new Request( $do( 'up', $three ) ) );
		$this->assertSame( array( $one, $three, $two ), $pins() );
		$screen->handle_pin( new Request( $do( 'down', $one ) ) );
		$this->assertSame( array( $three, $one, $two ), $pins() );
		$screen->handle_pin( new Request( $do( 'up', $three ) ) );
		$this->assertSame( array( $three, $one, $two ), $pins(), 'the first item cannot move up' );
		$screen->handle_pin( new Request( $do( 'remove', $one ) ) );
		$this->assertSame( array( $three, $two ), $pins() );
		$this->assertSame( 'Unknown change.', $screen->handle_pin( new Request( $do( 'sideways', $two ) ) ) );
		$this->assertSame( 'Unknown block.', $screen->handle_pin( new Request( array( 'block' => 'nope' ) ) ) );
	}

	public function test_REQ_257_the_screen_lists_pins_search_results_and_the_block_now(): void {
		$screen = $this->screen_as( 'wiw_editor' );
		$one    = $this->story( 'Findable Gleam Story' );
		$this->story( 'Findable Gleam Draft', 'draft' );
		$screen->handle_pin(
			new Request(
				array(
					'block' => CurationSettings::STORIES_HOME,
					'op'    => 'add',
					'id'    => (string) $one,
				)
			)
		);
		$screen->handle(
			new Request(
				array(
					'block'  => CurationSettings::STORIES_HOME,
					'mode'   => 'manual',
					'count'  => '3',
					'fill'   => 'latest',
					'pinned' => (string) $one,
				)
			)
		);
		$html = $screen->html(
			'',
			new Request(
				array(
					'pick' => CurationSettings::STORIES_HOME,
					'q'    => 'Gleam',
				)
			)
		);
		$this->assertStringContainsString( '<ol class="wiw-pins" data-block="stories_home">', $html );
		$this->assertStringContainsString( 'Findable Gleam Story', $html );
		$this->assertStringNotContainsString( 'Findable Gleam Draft', $html, 'a story that is not public is not offered' );
		$this->assertStringContainsString( '(pinned)', $html );
		$this->assertStringContainsString( 'The block now', $html );
		$this->assertStringContainsString( '<ol class="wiw-preview"><li>Findable Gleam Story</li></ol>', $html );
	}

	public function test_REQ_257_preview_without_saving_shows_the_unsaved_setting_and_saves_nothing(): void {
		$screen = $this->screen_as( 'wiw_editor' );
		$first  = $this->story( 'Preview First Story' );
		$second = $this->story( 'Preview Second Story' );
		$html   = $screen->html(
			'',
			new Request(
				array(
					'preview' => '1',
					'block'   => CurationSettings::STORIES_PAGE,
					'mode'    => 'manual',
					'count'   => '3',
					'fill'    => 'latest',
					'pinned'  => $second . ',' . $first,
				)
			)
		);
		$this->assertStringContainsString( 'Preview of your unsaved changes', $html );
		$this->assertStringContainsString( 'Nothing is saved until you press Save.', $html );
		$this->assertStringContainsString( '<li>Preview Second Story</li><li>Preview First Story</li>', $html );
		$this->assertFalse( get_option( CurationSettings::OPTION_PREFIX . CurationSettings::STORIES_PAGE, false ) );
	}

	public function test_REQ_257_the_printed_screen_keeps_the_pin_controls(): void {
		$screen = $this->screen_as( 'wiw_editor' );
		$one    = $this->story( 'Printed Pin Story' );
		$screen->handle_pin(
			new Request(
				array(
					'block' => CurationSettings::STORIES_HOME,
					'op'    => 'add',
					'id'    => (string) $one,
				)
			)
		);
		ob_start();
		$screen->render( new Request( array() ) );
		$printed = (string) ob_get_clean();
		$this->assertStringContainsString( 'name="op" value="remove"', $printed );
		$this->assertStringContainsString( 'name="action" value="' . CurationScreen::PIN . '"', $printed );
		$this->assertStringContainsString( 'formmethod="get"', $printed );
		$this->assertStringContainsString( 'data-id="' . $one . '"', $printed );
	}

	public function test_REQ_257_the_pin_action_needs_the_curate_capability(): void {
		$check = static function ( string $role ): ?string {
			wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
			$request = new Request( array( '_wpnonce' => ActionGuard::nonce_for( CurationScreen::PIN ) ) );
			return ActionGuard::check( CurationScreen::PIN, CurationScreen::CAP, $request );
		};
		$this->assertNotNull( $check( 'wiw_reviewer' ) );
		$this->assertNull( $check( 'wiw_editor' ) );
	}
}
