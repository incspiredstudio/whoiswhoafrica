<?php
/**
 * Approve, reject, unpublish, restore and the claimability predicate (REQ-222, REQ-148, REQ-209, REQ-226). Needs the WordPress
 * test suite. Plain TestCase: the machine opens real transactions on real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Review;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\Claimability;
use WhoIsWho\Core\Review\ReviewService;
use WhoIsWho\Core\Review\StateException;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Core\Review\StatePolicy;
use WhoIsWho\Tests\TestCase;

final class ReviewServiceTest extends TestCase {

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
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
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

	private function profile( string $state, array $values = array() ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => 'Review test ' . count( $this->posts ),
				'post_status' => 'draft',
			)
		);
		$this->assertIsInt( $id );
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, $state, array( 'entity_type' => 'organisation' ) + $values );
		return $id;
	}

	private function state( int $id ): ?string {
		return ( new StateRepository() )->pub_state( $id );
	}

	public function test_REQ_222_approve_is_one_flow(): void {
		$reviewer = $this->user( 'wiw_reviewer' );
		$id       = $this->profile( PubState::PUBLISHED_PENDING );
		$queued   = array();
		add_action(
			'wiw_profile_state_changed',
			static function ( int $pid, ?string $from, string $to ) use ( &$queued ): void {
				$queued[] = array( $pid, $from, $to );
			},
			10,
			3
		);
		( new ReviewService() )->approve( $id, $reviewer );
		remove_all_actions( 'wiw_profile_state_changed' );

		$row = ( new StateRepository() )->get( $id );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, $row['pub_state'] );
		$this->assertSame( (string) $reviewer, (string) $row['reviewer_id'] );
		$this->assertNotEmpty( $row['reviewed_at'] );
		$policy = StatePolicy::for_state( PubState::PUBLISHED_REVIEWED );
		$this->assertFalse( $policy->noindex );
		$this->assertTrue( $policy->in_sitemap );
		$this->assertTrue( $policy->has_schema );
		$this->assertSame( array( array( $id, PubState::PUBLISHED_PENDING, PubState::PUBLISHED_REVIEWED ) ), $queued, 'The change is announced once, so the purge queues in the same step.' );
		$this->assertSame( 'publish', get_post_status( $id ) );
	}

	public function test_REQ_222_reject_live_profile_returns_410(): void {
		$reviewer = $this->user( 'wiw_reviewer' );
		$id       = $this->profile( PubState::PUBLISHED_PENDING );
		( new ReviewService() )->reject( $id, 'Not a real organisation', $reviewer );
		$this->assertSame( 410, StatePolicy::for_state( $this->state( $id ) )->status );
		$this->assertSame( ProfileType::STATUS_HIDDEN, get_post_status( $id ) );
	}

	public function test_REQ_222_reject_needs_a_reason_and_hides_never_public_record(): void {
		$reviewer = $this->user( 'wiw_reviewer' );
		$id       = $this->profile( PubState::IMPORTED_NOT_PUBLIC );
		try {
			( new ReviewService() )->reject( $id, '  ', $reviewer );
			$this->fail( 'A reason is required.' );
		} catch ( StateException $e ) {
			$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, $this->state( $id ) );
		}
		( new ReviewService() )->reject( $id, 'Duplicate of another record', $reviewer );
		$this->assertSame( PubState::REJECTED, $this->state( $id ) );
	}

	public function test_REQ_148_importer_cannot_approve_own_import(): void {
		$reviewer = $this->user( 'wiw_reviewer' );
		$other    = $this->user( 'wiw_reviewer' );
		$id       = $this->profile( PubState::PUBLISHED_PENDING, array( 'importer_id' => $reviewer ) );
		try {
			( new ReviewService() )->approve( $id, $reviewer );
			$this->fail( 'The importer must not approve.' );
		} catch ( StateException $e ) {
			$this->assertSame( PubState::PUBLISHED_PENDING, $this->state( $id ) );
		}
		( new ReviewService() )->approve( $id, $other );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, $this->state( $id ) );
	}

	public function test_REQ_222_approve_needs_the_review_capability(): void {
		$subscriber = $this->user( 'subscriber' );
		$id         = $this->profile( PubState::PUBLISHED_PENDING );
		$this->expectException( StateException::class );
		( new ReviewService() )->approve( $id, $subscriber );
	}

	public function test_REQ_209_unpublish_returns_410_with_noindex(): void {
		$editor = $this->user( 'wiw_editor' );
		$id     = $this->profile( PubState::PUBLISHED_PENDING );
		( new ReviewService() )->unpublish( $id, 'Owner asked', $editor );
		$policy = StatePolicy::for_state( $this->state( $id ) );
		$this->assertSame( 410, $policy->status );
		$this->assertTrue( $policy->noindex );
	}

	public function test_REQ_209_a_reviewer_can_unpublish_through_the_bulk_capability(): void {
		$reviewer = $this->user( 'wiw_reviewer' );
		$id       = $this->profile( PubState::PUBLISHED_PENDING );
		( new ReviewService() )->unpublish( $id, 'Emergency', $reviewer );
		$this->assertSame( PubState::UNPUBLISHED, $this->state( $id ) );
	}

	public function test_REQ_209_unpublish_needs_a_reason(): void {
		$editor = $this->user( 'wiw_editor' );
		$id     = $this->profile( PubState::PUBLISHED_PENDING );
		$this->expectException( StateException::class );
		( new ReviewService() )->unpublish( $id, '', $editor );
	}

	public function test_REQ_209_restore_returns_200(): void {
		$editor   = $this->user( 'wiw_editor' );
		$reviewer = $this->user( 'wiw_reviewer' );
		$id       = $this->profile( PubState::PUBLISHED_PENDING );
		( new ReviewService() )->approve( $id, $reviewer );
		( new ReviewService() )->unpublish( $id, 'Check facts', $editor );
		$this->assertSame( 410, StatePolicy::for_state( $this->state( $id ) )->status );
		( new ReviewService() )->restore( $id, 'Facts confirmed', $editor );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, $this->state( $id ) );
		$this->assertSame( 200, StatePolicy::for_state( $this->state( $id ) )->status );
	}

	public function test_REQ_209_restore_of_never_reviewed_profile_goes_back_to_pending(): void {
		$editor = $this->user( 'wiw_editor' );
		$id     = $this->profile( PubState::PUBLISHED_PENDING );
		( new ReviewService() )->unpublish( $id, 'Check', $editor );
		( new ReviewService() )->restore( $id, 'Fine', $editor );
		$this->assertSame( PubState::PUBLISHED_PENDING, $this->state( $id ) );
	}

	public function test_REQ_226_pending_profile_cannot_be_claimed(): void {
		$reviewer = $this->user( 'wiw_reviewer' );
		$id       = $this->profile( PubState::PUBLISHED_PENDING );
		$claim    = new Claimability();
		$this->assertFalse( $claim->can_claim( $id ) );
		( new ReviewService() )->approve( $id, $reviewer );
		$this->assertTrue( $claim->can_claim( $id ) );
		$this->assertFalse( $claim->can_claim( 0 ), 'A profile with no state cannot be claimed.' );
	}

	public function test_REQ_222_every_transition_writes_an_audit_row_with_reason(): void {
		global $wpdb;
		$editor = $this->user( 'wiw_editor' );
		$id     = $this->profile( PubState::PUBLISHED_PENDING );
		( new ReviewService() )->unpublish( $id, 'Because of a dispute', $editor );
		$reason = $wpdb->get_var( $wpdb->prepare( "SELECT reason FROM {$wpdb->prefix}wiw_audit_log WHERE action = 'profile.state_change' AND subject_id = %d ORDER BY id DESC LIMIT 1", $id ) ); // phpcs:ignore WordPress.DB
		$this->assertSame( 'Because of a dispute', $reason );
	}
}
