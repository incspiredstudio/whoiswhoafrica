<?php
/**
 * Human merge of probable duplicates (REQ-123, REQ-145, REQ-146). Needs the WordPress test suite. Plain TestCase: the state
 * machine opens real transactions on real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Review;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Directory\Url\ProfileRoute;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\Duplicates\DuplicateMerge;
use WhoIsWho\Core\Review\Duplicates\DuplicateRepository;
use WhoIsWho\Core\Review\StateException;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class DuplicateMergeTest extends TestCase {

	/**
	 * Posts created.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Users created.
	 *
	 * @var int[]
	 */
	private array $users = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
		$wpdb->query( 'DELETE FROM ' . DuplicateRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
		$wpdb->query( 'DELETE FROM ' . DuplicateRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$this->posts = array();
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'user_email' => wp_generate_password( 8, false ) . '@example.test',
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	private function profile( string $title, string $state, array $meta = array() ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'draft',
			)
		);
		$this->assertIsInt( $id );
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_REVIEWED === $state ? PubState::PUBLISHED_PENDING : $state, array( 'entity_type' => 'organisation' ) );
		if ( PubState::PUBLISHED_REVIEWED === $state ) {
			$machine->transition( $id, $state );
		}
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, 'wiw_' . $key, $value );
		}
		return $id;
	}

	/**
	 * Two flagged profiles: the keeper has a sector, the other has a mandate and a different sector.
	 *
	 * @return array{0: int, 1: int}
	 */
	private function pair(): array {
		$keep = $this->profile( 'Acme Holdings', PubState::PUBLISHED_REVIEWED, array( 'sector' => 'Banking' ) );
		$drop = $this->profile(
			'Acme Holdings Ltd',
			PubState::PUBLISHED_PENDING,
			array(
				'sector'  => 'Finance',
				'mandate' => 'Lend money',
			)
		);
		( new DuplicateRepository() )->flag( $keep, $drop, 90, 'name_org_location' );
		return array( $keep, $drop );
	}

	public function test_REQ_146_merge_is_by_human_action_only(): void {
		list( $keep, $drop ) = $this->pair();
		$this->assertSame( PubState::PUBLISHED_REVIEWED, ( new StateRepository() )->pub_state( $keep ), 'flagging merges nothing' );
		$this->assertSame( PubState::PUBLISHED_PENDING, ( new StateRepository() )->pub_state( $drop ) );
		$this->assertCount( 1, ( new DuplicateRepository() )->with_status( 'open' ) );

		$this->expectException( StateException::class );
		( new DuplicateMerge() )->merge( $keep, $drop, array( 'mandate' ), 'Same company.', $this->user( 'subscriber' ) );
	}

	public function test_REQ_123_merge_writes_version_and_audit_and_redirects_the_old_slug(): void {
		global $wpdb;
		list( $keep, $drop ) = $this->pair();
		$old_slug            = (string) get_post_field( 'post_name', $drop );
		$reviewer            = $this->user( 'wiw_reviewer' );
		$audit_before        = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wiw_audit_log' ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.

		$versions_before = count( ( new VersionRepository() )->for_profile( $keep ) );
		( new DuplicateMerge() )->merge( $keep, $drop, array( 'mandate' ), 'Same company, one record.', $reviewer );

		$this->assertSame( 'Lend money', get_post_meta( $keep, 'wiw_mandate', true ), 'the chosen field came across' );
		$this->assertSame( 'Banking', get_post_meta( $keep, 'wiw_sector', true ), 'a field not chosen is kept' );

		$versions = ( new VersionRepository() )->for_profile( $keep );
		$this->assertCount( $versions_before + 1, $versions, 'one version is added (the table is append-only, so earlier runs may leave rows)' );
		$this->assertSame( 'Same company, one record.', $versions[0]['reason'] );
		$this->assertSame( $reviewer, (int) $versions[0]['author_id'] );

		$audit_after = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wiw_audit_log' ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$this->assertGreaterThan( $audit_before, $audit_after );
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wiw_audit_log WHERE action = %s AND subject_id = %d', 'duplicates.merge', $keep ) ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.

		$this->assertSame( PubState::UNPUBLISHED, ( new StateRepository() )->pub_state( $drop ), 'the merged-away profile is taken down' );
		$pair = ( new DuplicateRepository() )->get( $keep, $drop );
		$this->assertSame( 'merged', $pair['status'] );

		$result = ( new ProfileRoute() )->resolve( $old_slug );
		$this->assertSame( 'redirect', $result['action'] );
		$this->assertSame( 301, $result['status'] );
		$this->assertStringContainsString( (string) get_post_field( 'post_name', $keep ), (string) $result['location'] );
	}

	public function test_REQ_146_a_reason_is_required_to_merge(): void {
		list( $keep, $drop ) = $this->pair();
		$this->expectException( StateException::class );
		( new DuplicateMerge() )->merge( $keep, $drop, array(), '  ', $this->user( 'wiw_reviewer' ) );
	}

	public function test_REQ_145_marking_not_a_duplicate_stores_a_reason(): void {
		list( $keep, $drop ) = $this->pair();
		$reviewer            = $this->user( 'wiw_reviewer' );
		( new DuplicateMerge() )->dismiss( $keep, $drop, 'Different branches of the same group.', $reviewer );
		$pair = ( new DuplicateRepository() )->get( $keep, $drop );
		$this->assertSame( 'dismissed', $pair['status'] );
		$this->assertSame( 'Different branches of the same group.', $pair['reason'] );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, ( new StateRepository() )->pub_state( $keep ) );

		list( $keep2, $drop2 ) = $this->pair();
		$this->expectException( StateException::class );
		( new DuplicateMerge() )->dismiss( $keep2, $drop2, '', $reviewer );
	}
}
