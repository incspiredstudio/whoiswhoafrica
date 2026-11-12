<?php
/**
 * The staff Profiles list (REQ-091, REQ-242, REQ-208, REQ-244). Needs the WordPress test suite. Roles are reset in setUp because
 * other tests commit the transaction.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Admin\ProfilesQuery;
use WhoIsWho\Core\Directory\Admin\ProfilesScreen;
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

final class ProfilesScreenTest extends WpTestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
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

	/**
	 * Make a profile.
	 *
	 * @param string               $name   Name.
	 * @param string               $type   Entity type.
	 * @param string               $state  reviewed, pending, imported or unpublished.
	 * @param array<string, mixed> $values Extra state columns (source, import_batch, origin, claimed).
	 */
	private function profile( string $name, string $type, string $state, array $values = array() ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, 'imported' === $state ? PubState::IMPORTED_NOT_PUBLIC : PubState::PUBLISHED_PENDING, array( 'entity_type' => $type ) );
		if ( 'reviewed' === $state ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		} elseif ( 'unpublished' === $state ) {
			$machine->transition( $id, PubState::UNPUBLISHED, 'test' );
		}
		if ( array() !== $values ) {
			( new StateRepository() )->update( $id, $values );
		}
		return $id;
	}

	public function test_REQ_242_profiles_list_filters_by_source_and_batch(): void {
		$a = $this->profile(
			'List A Qwv',
			'organisation',
			'imported',
			array(
				'source'       => 'wikidata-qwv',
				'import_batch' => 'batch-qwv-1',
			)
		);
		$b = $this->profile(
			'List B Qwv',
			'organisation',
			'imported',
			array(
				'source'       => 'cac-qwv',
				'import_batch' => 'batch-qwv-1',
			)
		);
		$c = $this->profile(
			'List C Qwv',
			'individual',
			'reviewed',
			array(
				'source'       => 'wikidata-qwv',
				'import_batch' => 'batch-qwv-2',
			)
		);
		$q = new ProfilesQuery();

		$by_source = $q->run( array( 'source' => 'wikidata-qwv' ) );
		$this->assertEqualsCanonicalizing( array( $a, $c ), array_column( $by_source['rows'], 'id' ) );
		$by_batch = $q->run( array( 'batch' => 'batch-qwv-1' ) );
		$this->assertEqualsCanonicalizing( array( $a, $b ), array_column( $by_batch['rows'], 'id' ) );
		$both = $q->run(
			array(
				'source' => 'wikidata-qwv',
				'batch'  => 'batch-qwv-1',
			)
		);
		$this->assertSame( array( $a ), array_column( $both['rows'], 'id' ) );
		$this->assertSame(
			array( $c ),
			array_column(
				$q->run(
					array(
						'type'  => 'individual',
						'batch' => 'batch-qwv-2',
					)
				)['rows'],
				'id'
			)
		);
		$this->assertSame( array( $b ), array_column( $q->run( array( 'name' => 'List B Qwv' ) )['rows'], 'id' ) );

		$grouped = $q->run(
			array(
				'group' => 'source',
				'batch' => 'batch-qwv-1',
			)
		)['groups'];
		$counts  = array_column( $grouped, 'count', 'value' );
		$this->assertSame( 1, $counts['wikidata-qwv'] );
		$this->assertSame( 1, $counts['cac-qwv'] );
		$this->assertSame( array( $a, $b ), $q->ids( array( 'batch' => 'batch-qwv-1' ) ), 'The bulk host gets the same set.' );
	}

	public function test_REQ_242_filters_come_from_the_request_only_when_valid(): void {
		$filters = ProfilesQuery::filters_from(
			new Request(
				array(
					'state'  => 'published_pending',
					'type'   => 'wizard',
					'origin' => 'import',
					'source' => '<b>x</b>',
					'group'  => 'batch',
					'other'  => 'ignored',
				)
			)
		);
		$this->assertSame(
			array(
				'source' => 'x',
				'state'  => 'published_pending',
				'origin' => 'import',
				'group'  => 'batch',
			),
			$filters
		);
	}

	public function test_REQ_208_profiles_list_shows_visitor_status_per_row(): void {
		$names = array();
		foreach ( array( 'reviewed', 'pending', 'unpublished', 'imported' ) as $state ) {
			$names[ $state ] = $this->profile( 'Visitor ' . $state . ' Qwv', 'organisation', $state );
		}
		$rows   = ( new ProfilesQuery() )->run( array( 'name' => 'Visitor' ) )['rows'];
		$status = array_column( $rows, 'visitor', 'id' );
		$this->assertSame( 'page', $status[ $names['reviewed'] ] );
		$this->assertSame( 'page', $status[ $names['pending'] ], 'A pending profile answers 200 for a visitor.' );
		$this->assertSame( 'gone', $status[ $names['unpublished'] ] );
		$this->assertSame( 'not public', $status[ $names['imported'] ] );
	}

	public function test_REQ_244_profiles_list_denies_without_capability(): void {
		$registry = new ScreenRegistry();
		ProfilesScreen::register( $registry );
		$registry->hook();
		$users               = AuthorisationMatrix::users( self::factory(), 'wiw_run_imports', 'wiw_review_records' );
		$users['subscriber'] = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		AuthorisationMatrix::assert_statuses(
			$this,
			ProfilesScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 200,
				'editor'        => 200,
				'administrator' => 200,
				'subscriber'    => 403,
			)
		);
		$this->assertContains( ProfilesScreen::SCREEN, AuthorisationMatrix::menu( $users['reviewer'] ) );
		$this->assertNotContains( ProfilesScreen::SCREEN, AuthorisationMatrix::menu( $users['subscriber'] ) );
	}

	public function test_REQ_091_screen_shows_claimed_mark_to_staff_only_and_escapes_names(): void {
		$this->profile( 'Mark <b>Qwv</b>', 'organisation', 'reviewed', array( 'claimed' => 1 ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) ) );
		$_GET = array(
			'page' => ProfilesScreen::SCREEN,
			'name' => 'Mark',
		);
		ob_start();
		( new ProfilesScreen() )->render();
		$html = (string) ob_get_clean();
		$_GET = array();
		$this->assertStringContainsString( '&#10003;', $html );
		$this->assertStringNotContainsString( 'Mark <b>Qwv</b>', $html );
		$this->assertStringContainsString( 'name="name" value="Mark"', $html, 'The filter state is in the form and the address.' );
	}
}
