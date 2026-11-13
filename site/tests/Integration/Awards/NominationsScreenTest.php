<?php
/**
 * The Nominations screen: the toggle, the filtered list, and the stage, status and remove controls that call NominationService
 * (REQ-194, REQ-267, REQ-202, REQ-031). Needs the WordPress test suite and the real profile and awards tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\NominationList;
use WhoIsWho\Core\Awards\NominationService;
use WhoIsWho\Core\Awards\NominationsScreen;
use WhoIsWho\Core\Awards\NominationsSwitch;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class NominationsScreenTest extends WpTestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		foreach ( array( AwardsTables::NOMINATIONS, AwardsTables::CATEGORIES, AwardsTables::CHANGES ) as $name ) {
			$wpdb->query( 'DELETE FROM ' . AwardsTables::table( $name ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		}
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function screen(): NominationsScreen {
		return new NominationsScreen( new NominationsSwitch( SettingsService::for_site() ), NominationService::for_site() );
	}

	private function as_role( string $role ): int {
		$id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $id );
		return $id;
	}

	private function profile( string $name, bool $reviewed ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		if ( $reviewed ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		}
		return $id;
	}

	private function category( string $title = 'Digital Pioneer' ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::CATEGORIES ),
			array(
				'group_key'  => 'technology-innovation',
				'title'      => $title,
				'created_at' => '2026-11-12 10:00:00',
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function nomination( int $profile_id, int $category_id ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::NOMINATIONS ),
			array(
				'category_id'     => $category_id,
				'profile_id'      => $profile_id,
				'nominator_name'  => 'Nora Nominator',
				'nominator_email' => 'nora.nominator@example.test',
				'contact_email'   => 'press.contact@example.test',
				'created_at'      => '2026-11-12 10:00:00',
				'updated_at'      => '2026-11-12 10:00:00',
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function audit_count( string $action, int $id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wiw_audit_log WHERE action = %s AND subject_id = %s", $action, (string) $id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	public function test_REQ_194_toggle_visible_on_nominations_screen_and_read_only_for_editor(): void {
		$this->as_role( 'administrator' );
		$html = $this->screen()->html();
		$this->assertStringContainsString( 'value="wiw_toggle_nominations"', $html );
		$this->assertStringContainsString( 'Turn Nominations on', $html );

		$this->as_role( 'wiw_editor' );
		$html = $this->screen()->html();
		$this->assertStringContainsString( 'Nominations are off', $html );
		$this->assertStringContainsString( 'Only an Administrator can change this', $html );
		$this->assertStringNotContainsString( 'value="wiw_toggle_nominations"', $html );
	}

	public function test_REQ_267_no_feature_flag_or_shortlist_screen_in_the_menu(): void {
		set_current_screen( 'dashboard' );
		NominationsSwitch::register_admin();
		$screens = ScreenRegistry::shared()->screens();
		$this->assertArrayHasKey( NominationsSwitch::SCREEN, $screens );
		foreach ( $screens as $slug => $screen ) {
			$haystack = strtolower( $slug . ' ' . $screen['title'] );
			$this->assertStringNotContainsString( 'feature', $haystack );
			$this->assertStringNotContainsString( 'shortlist', $haystack );
		}
		set_current_screen( 'front' );
	}

	public function test_REQ_267_nominations_screen_requires_wiw_manage_awards(): void {
		$registry = new ScreenRegistry();
		$registry->register( NominationsSwitch::SCREEN, 'Nominations', NominationsScreen::CAP, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), NominationsScreen::CAP, NominationsScreen::CAP );
		AuthorisationMatrix::assert_statuses(
			$this,
			NominationsSwitch::SCREEN,
			$users,
			array(
				'reviewer'      => 403,
				'editor'        => 200,
				'administrator' => 200,
				'granted'       => 200,
				'revoked'       => 403,
			)
		);
		wp_set_current_user( $users['reviewer'] );
		$this->expectException( \DomainException::class );
		$this->screen()->html();
	}

	public function test_REQ_202_stage_status_controls_call_nomination_service(): void {
		$cat = $this->category();
		$id  = $this->nomination( $this->profile( 'Screen Org Yqz', true ), $cat );
		$this->as_role( 'wiw_editor' );
		$screen = $this->screen();

		$html = $screen->html();
		$this->assertStringContainsString( 'Screen Org Yqz', $html );
		$this->assertStringContainsString( 'name="nomination_id" value="' . $id . '"', $html );
		$this->assertStringNotContainsString( 'Nora', $html, 'the nominator is not on the list' );
		$this->assertStringNotContainsString( 'press.contact', $html );

		$out = $screen->handle(
			new Request(
				array(
					'op'            => 'stage',
					'nomination_id' => $id,
					'value'         => 'winner',
				)
			)
		);
		$this->assertSame( 'Stage saved.', $out['notice'] );
		$out = $screen->handle(
			new Request(
				array(
					'op'            => 'status',
					'nomination_id' => $id,
					'value'         => 'live',
				)
			)
		);
		$this->assertSame( 'Status saved.', $out['notice'] );
		$row = NominationService::for_site()->find( $id );
		$this->assertSame( 'winner', $row['stage'] );
		$this->assertSame( 'live', $row['status'] );
		$this->assertSame( 1, $this->audit_count( 'nomination.stage', $id ) );
		$this->assertSame( 1, $this->audit_count( 'nomination.status', $id ) );

		$bad = $screen->handle(
			new Request(
				array(
					'op'            => 'stage',
					'nomination_id' => $id,
					'value'         => 'finalist',
				)
			)
		);
		$this->assertStringContainsString( 'not allowed', $bad['notice'], 'no finalist step' );
	}

	public function test_REQ_202_pending_review_profile_cannot_be_set_live_from_the_screen(): void {
		$id = $this->nomination( $this->profile( 'Pending Screen Org Yqz', false ), $this->category() );
		$this->as_role( 'wiw_editor' );
		$out = $this->screen()->handle(
			new Request(
				array(
					'op'            => 'status',
					'nomination_id' => $id,
					'value'         => 'live',
				)
			)
		);
		$this->assertStringContainsString( 'reviewed', $out['notice'] );
		$this->assertSame( 'not_live', NominationService::for_site()->find( $id )['status'] );
	}

	public function test_REQ_202_remove_needs_a_confirmation_step_and_is_audited(): void {
		$id = $this->nomination( $this->profile( 'Remove Org Yqz', true ), $this->category() );
		$this->as_role( 'wiw_editor' );
		$screen = $this->screen();
		$ask    = $screen->handle(
			new Request(
				array(
					'op'            => 'remove_ask',
					'nomination_id' => $id,
				)
			)
		);
		$this->assertSame( $id, $ask['confirm'] );
		$this->assertNotNull( NominationService::for_site()->find( $id ), 'asking does not remove' );
		$this->assertStringContainsString( 'Confirm removal', $screen->html( array(), 1, '', $id ) );

		$refused = $screen->handle(
			new Request(
				array(
					'op'            => 'remove',
					'nomination_id' => $id,
					'token'         => 'wrong',
				)
			)
		);
		$this->assertStringContainsString( 'Confirm', $refused['notice'] );
		$this->assertNotNull( NominationService::for_site()->find( $id ) );

		$done = $screen->handle(
			new Request(
				array(
					'op'            => 'remove',
					'nomination_id' => $id,
					'token'         => NominationService::for_site()->confirmation_token( $id ),
				)
			)
		);
		$this->assertSame( 'Nomination removed.', $done['notice'] );
		$this->assertNull( NominationService::for_site()->find( $id ) );
		$this->assertSame( 1, $this->audit_count( 'nomination.remove', $id ) );
	}

	public function test_REQ_031_list_filters_by_stage_status_category_and_source(): void {
		$cat_a = $this->category( 'Category A' );
		$cat_b = $this->category( 'Category B' );
		$one   = $this->nomination( $this->profile( 'Filter One Yqz', true ), $cat_a );
		$two   = $this->nomination( $this->profile( 'Filter Two Yqz', true ), $cat_b );
		global $wpdb;
		$wpdb->update( AwardsTables::table( AwardsTables::NOMINATIONS ), array( 'status' => 'live' ), array( 'id' => $two ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$list = new NominationList();
		$ids  = static fn ( array $filters ): array => array_map( static fn ( $r ) => (int) $r['id'], $list->run( $filters )['rows'] );
		$this->assertEqualsCanonicalizing( array( $one, $two ), $ids( array() ) );
		$this->assertSame( array( $two ), $ids( array( 'status' => 'live' ) ) );
		$this->assertSame( array( $one ), $ids( array( 'category' => (string) $cat_a ) ) );
		$this->assertSame( array(), $ids( array( 'stage' => 'winner' ) ) );
		$this->assertSame( array( $one, $two ), array_reverse( $ids( array( 'source' => 'public_form' ) ) ) );
		$this->assertSame(
			array( $one, $two ),
			array_reverse(
				$ids(
					array(
						'status'   => 'bogus',
						'category' => 'x; DROP',
					)
				)
			),
			'invalid filter values are dropped'
		);
	}
}
