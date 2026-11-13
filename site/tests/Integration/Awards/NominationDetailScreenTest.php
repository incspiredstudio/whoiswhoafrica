<?php
/**
 * The Nomination detail screen (REQ-031, REQ-196, REQ-202): nominator, nominee, evidence, change history, duplicate warnings and
 * internal notes, for staff with wiw_manage_awards only. Needs the WordPress test suite and the real profile and awards tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\NominationDetailScreen;
use WhoIsWho\Core\Awards\NominationService;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class NominationDetailScreenTest extends WpTestCase {

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

	private function screen(): NominationDetailScreen {
		return new NominationDetailScreen( NominationService::for_site() );
	}

	private function as_role( string $role, string $name = '' ): int {
		$args = array( 'role' => $role );
		if ( '' !== $name ) {
			$args['display_name'] = $name;
		}
		$id = self::factory()->user->create( $args );
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

	/**
	 * A public-form nomination.
	 *
	 * @param int                  $category_id Category.
	 * @param array<string, mixed> $extra       Column overrides.
	 */
	private function nomination( int $category_id, array $extra = array() ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::NOMINATIONS ),
			array_merge(
				array(
					'category_id'     => $category_id,
					'nominator_name'  => 'Nora Nominator',
					'nominator_email' => 'nora.nominator@example.test',
					'nominee_name'    => 'Ada Obi',
					'nominee_org'     => 'Obi Labs',
					'evidence'        => 'Built the clinic network. See the press kit.',
					'created_at'      => '2026-11-12 10:00:00',
					'updated_at'      => '2026-11-12 10:00:00',
				),
				$extra
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function audit_rows( string $action, int $id ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wiw_audit_log WHERE action = %s AND subject_id = %s", $action, (string) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	public function test_REQ_031_nomination_detail_requires_wiw_manage_awards(): void {
		$registry = new ScreenRegistry();
		$registry->register( NominationDetailScreen::SCREEN, 'Nomination', NominationDetailScreen::CAP, '__return_null', true );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), NominationDetailScreen::CAP, NominationDetailScreen::CAP );
		AuthorisationMatrix::assert_statuses(
			$this,
			NominationDetailScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 403,
				'editor'        => 200,
				'administrator' => 200,
				'granted'       => 200,
				'revoked'       => 403,
			)
		);
		$id = $this->nomination( $this->category() );
		wp_set_current_user( $users['reviewer'] );
		$this->expectException( \DomainException::class );
		$this->screen()->html( $id );
	}

	public function test_REQ_031_staff_see_nominator_nominee_and_evidence(): void {
		$id = $this->nomination( $this->category() );
		$this->as_role( 'wiw_editor' );
		$html = $this->screen()->html( $id );
		$this->assertStringContainsString( 'Nora Nominator', $html );
		$this->assertStringContainsString( 'nora.nominator@example.test', $html );
		$this->assertStringContainsString( 'Ada Obi', $html );
		$this->assertStringContainsString( 'Obi Labs', $html );
		$this->assertStringContainsString( 'Built the clinic network', $html );
		$this->assertStringContainsString( 'Digital Pioneer', $html );
	}

	public function test_REQ_202_change_history_shows_staff_member_for_each_change(): void {
		$profile = $this->profile( 'History Org Qzx', true );
		$id      = $this->nomination( $this->category(), array( 'profile_id' => $profile ) );
		$this->as_role( 'wiw_editor', 'Edna Editor' );
		NominationService::for_site()->set_stage( $id, 'winner' );
		NominationService::for_site()->set_status( $id, 'live' );
		$this->as_role( 'administrator', 'Adam Admin' );
		NominationService::for_site()->set_stage( $id, 'nominated' );

		$html = $this->screen()->html( $id );
		$this->assertStringContainsString( 'Edna Editor', $html );
		$this->assertStringContainsString( 'Adam Admin', $html );
		$this->assertSame( 2, substr_count( $html, 'Edna Editor' ) );
		$this->assertStringContainsString( 'Nominated', $html );
		$this->assertStringContainsString( 'Winner', $html );
	}

	public function test_REQ_196_duplicate_warning_is_shown_to_staff_without_naming_the_other_nominator(): void {
		$cat   = $this->category();
		$first = $this->nomination(
			$cat,
			array(
				'nominator_name'  => 'First Nominator',
				'nominator_email' => 'first.nominator@example.test',
			)
		);
		$other = $this->nomination(
			$cat,
			array(
				'nominator_name'  => 'Second Nominator',
				'nominator_email' => 'second.nominator@example.test',
			)
		);
		$this->as_role( 'wiw_editor' );
		$html = $this->screen()->html( $other );
		$this->assertStringContainsString( 'Possible duplicate', $html );
		$this->assertStringContainsString( 'nomination #' . $first, $html, 'the other nomination is named by number only' );
		$this->assertStringNotContainsString( 'First Nominator', $html );
		$this->assertStringNotContainsString( 'first.nominator@example.test', $html );

		$lonely = $this->nomination( $this->category( 'Other Category' ), array( 'nominee_name' => 'Unique Person Zed' ) );
		$this->assertStringNotContainsString( 'Possible duplicate', $this->screen()->html( $lonely ) );
	}

	public function test_REQ_031_internal_notes_are_saved_audited_without_text_and_never_public(): void {
		$id = $this->nomination( $this->category() );
		$this->as_role( 'wiw_editor' );
		$out = $this->screen()->handle(
			new Request(
				array(
					'op'            => 'notes',
					'nomination_id' => $id,
					'notes'         => 'Phone call with the press office <b>secret-note-zq</b>',
				)
			)
		);
		$this->assertSame( 'Notes saved.', $out );
		$this->assertStringContainsString( 'secret-note-zq', $this->screen()->html( $id ) );
		$rows = $this->audit_rows( 'nomination.notes', $id );
		$this->assertCount( 1, $rows );
		$this->assertStringNotContainsString( 'secret-note-zq', (string) wp_json_encode( $rows[0] ) );

		$export = new \ReflectionClassConstant( \WhoIsWho\Core\Awards\NominationExport::class, 'COLUMNS' );
		$this->assertNotContains( 'notes', array_values( (array) $export->getValue() ), 'notes are never in the export' );
	}

	public function test_REQ_031_a_public_form_nomination_can_be_linked_to_an_existing_profile_once(): void {
		$id      = $this->nomination( $this->category() );
		$profile = $this->profile( 'Link Target Org', true );
		$other   = $this->profile( 'Second Target Org', true );
		$this->as_role( 'wiw_editor' );
		$screen = $this->screen();
		$out    = $screen->handle(
			new Request(
				array(
					'op'            => 'link',
					'nomination_id' => $id,
					'profile_id'    => $profile,
				)
			)
		);
		$this->assertSame( 'The nomination is linked to the profile.', $out );
		$this->assertSame( $profile, (int) NominationService::for_site()->find( $id )['profile_id'] );
		$this->assertCount( 1, $this->audit_rows( 'nomination.link_profile', $id ) );
		$this->assertStringContainsString( 'Link Target Org', $screen->html( $id ) );

		$again = $screen->handle(
			new Request(
				array(
					'op'            => 'link',
					'nomination_id' => $id,
					'profile_id'    => $other,
				)
			)
		);
		$this->assertSame( 'This nomination is already linked to a profile.', $again );
	}

	public function test_REQ_031_unknown_nomination_and_unknown_op_change_nothing(): void {
		$this->as_role( 'wiw_editor' );
		$this->assertSame(
			'That nomination does not exist.',
			$this->screen()->handle(
				new Request(
					array(
						'op'            => 'notes',
						'nomination_id' => 999999,
						'notes'         => 'x',
					)
				)
			)
		);
		$id = $this->nomination( $this->category() );
		$this->assertSame(
			'Nothing was changed.',
			$this->screen()->handle(
				new Request(
					array(
						'op'            => 'bogus',
						'nomination_id' => $id,
					)
				)
			)
		);
		$this->assertStringContainsString( 'does not exist', $this->screen()->html( 999999 ) );
	}
}
