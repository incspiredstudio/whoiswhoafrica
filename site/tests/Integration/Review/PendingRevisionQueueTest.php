<?php
/**
 * A pending edit to a claimed profile in the review queue (REQ-253, REQ-261): the item shows what changed, a holder of
 * wiw_review_records other than the author can approve or return it, the author sees no action, and the author is told the outcome.
 * Plain TestCase with the real tables, cleaning up after itself.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Review;

use WhoIsWho\Core\Directory\Edit\ProfileEditor;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Purge\PurgeQueue;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Log\LogSink;
use WhoIsWho\Core\Platform\Log\Logger;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Core\Review\Admin\ReviewItemScreen;
use WhoIsWho\Core\Review\Admin\ReviewQueue;
use WhoIsWho\Core\Review\ReviewService;
use WhoIsWho\Core\Review\Revisions\RevisionService;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class PendingRevisionQueueTest extends TestCase {

	/**
	 * Posts.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Users.
	 *
	 * @var int[]
	 */
	private array $users = array();

	/**
	 * Notifications sent.
	 *
	 * @var array<int, array{int, string, string, int}>
	 */
	private array $notified = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! post_type_exists( ProfileType::POST_TYPE ) ) {
			( new ProfileType() )->register();
		}
		if ( ! taxonomy_exists( Taxonomies::ORG_TYPE ) ) {
			( new Taxonomies() )->register();
		}
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
		$this->notified = array();
		// The versions table is append-only and test profiles are deleted, so a reused post id would inherit old pending rows:
		// start the ids above every profile that ever had a version.
		global $wpdb;
		$top = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(profile_id),0) FROM ' . VersionRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
		$wpdb->query( 'ALTER TABLE ' . $wpdb->posts . ' AUTO_INCREMENT = ' . ( $top + 1000 ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
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

	/**
	 * A user with a role.
	 *
	 * @param string $role Role.
	 */
	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'prq_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	/**
	 * A reviewed, claimed profile whose assigned manager makes one pending edit.
	 *
	 * @return array{int, int, int} Profile, manager and the pending revision id.
	 */
	private function profile_with_pending_edit(): array {
		$manager       = $this->user( 'wiw_editor' );
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Queue revision bank',
			)
		);
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => 'organisation' ) );
		( new ReviewService() )->approve( $id, $this->user( 'wiw_reviewer' ) );
		update_post_meta( $id, 'wiw_hq_city', 'Lagos' );
		( new StateRepository() )->update(
			$id,
			array(
				'claimed'          => 1,
				'assigned_user_id' => $manager,
			)
		);
		$result = ( new ProfileEditor() )->edit( $id, array( 'hq_city' => 'Abuja' ), 'Moved the head office', $manager );
		$this->assertFalse( $result['applied'] );
		return array( $id, $manager, $result['version_id'] );
	}

	private function service(): RevisionService {
		$sink  = new class() implements LogSink {
			/** @param string $line Line. */
			public function write( string $line ): void {
				unset( $line );
			}
		};
		$store = new \ArrayObject();
		$jobs  = new JobRunner(
			new MemoryQueue(),
			null,
			array(
				'get' => static fn ( string $key, $fallback ) => $store[ $key ] ?? $fallback,
				'set' => static function ( string $key, $value ) use ( $store ): void {
					$store[ $key ] = $value;
				},
			)
		);
		return new RevisionService(
			null,
			function ( int $user, string $outcome, string $reason, int $profile ): void {
				$this->notified[] = array( $user, $outcome, $reason, $profile );
			},
			new PurgeQueue( $jobs, new Logger( $sink, new PiiCatalogue() ) )
		);
	}

	public function test_REQ_253_pending_edit_listed_in_review_queue_with_diff(): void {
		list( $id, , $version ) = $this->profile_with_pending_edit();
		wp_set_current_user( $this->user( 'wiw_reviewer' ) );

		$mine = array_values( array_filter( ( new ReviewQueue() )->items( 1 )['items'], static fn ( array $item ): bool => $item['profile_id'] === $id ) );
		$this->assertCount( 1, $mine );
		$this->assertSame( ReviewQueue::KIND_REVISION, $mine[0]['kind'] );
		$this->assertSame( $version, $mine[0]['version_id'] );

		$html = ( new ReviewItemScreen() )->html( new Request( array( 'profile_id' => (string) $id ) ) );
		$this->assertStringContainsString( 'hq_city', $html, 'The item names the field that changed.' );
		$this->assertStringContainsString( 'Lagos', $html, 'The item shows the value before.' );
		$this->assertStringContainsString( 'Abuja', $html, 'The item shows the value after.' );
		$this->assertStringContainsString( 'Pending change from the profile manager', $html );
	}

	public function test_REQ_261_assigned_user_emailed_on_approval_or_return(): void {
		list( $id, $manager, $version ) = $this->profile_with_pending_edit();
		$reviewer                       = $this->user( 'wiw_reviewer' );
		$service                        = $this->service();
		$service->approve( $version, $reviewer );
		$this->assertSame( array( array( $manager, 'approved', '', $id ) ), $this->notified );

		$this->notified = array();
		$second         = ( new ProfileEditor() )->edit( $id, array( 'hq_city' => 'Kano' ), 'Another move', $manager )['version_id'];
		$service->reject( $second, 'Please cite a source.', $reviewer );
		$this->assertSame( array( array( $manager, 'rejected', 'Please cite a source.', $id ) ), $this->notified, 'A returned edit tells the author the note.' );
	}

	public function test_REQ_253_review_queue_hides_actions_from_author(): void {
		list( $id, $manager ) = $this->profile_with_pending_edit();

		wp_set_current_user( $manager );
		$own = ( new ReviewItemScreen() )->html( new Request( array( 'profile_id' => (string) $id ) ) );
		$this->assertStringNotContainsString( 'Approve this change', $own, 'The author gets no approve button.' );
		$this->assertStringNotContainsString( 'Reject this change', $own, 'The author gets no return button.' );
		$this->assertStringContainsString( 'another reviewer must decide it', $own );

		wp_set_current_user( $this->user( 'wiw_reviewer' ) );
		$other = ( new ReviewItemScreen() )->html( new Request( array( 'profile_id' => (string) $id ) ) );
		$this->assertStringContainsString( 'Approve this change', $other );
		$this->assertStringContainsString( 'Reject this change', $other );
	}

	public function test_REQ_188_pending_revision_decisions_audited(): void {
		global $wpdb;
		list( $id, $manager, $version ) = $this->profile_with_pending_edit();
		$reviewer                       = $this->user( 'wiw_reviewer' );
		wp_set_current_user( $reviewer );
		$floor   = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(id),0) FROM ' . $wpdb->prefix . 'wiw_audit_log' ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
		$service = $this->service();
		$service->reject( $version, 'Please cite a source.', $reviewer );
		$second = ( new ProfileEditor() )->edit( $id, array( 'hq_city' => 'Kano' ), 'Another move', $manager )['version_id'];
		$service->approve( $second, $reviewer );

		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT action, actor_id, reason FROM ' . $wpdb->prefix . 'wiw_audit_log WHERE id > %d AND action IN (%s, %s) ORDER BY id', $floor, 'profile.revision_rejected', 'profile.revision_approved' ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
		$this->assertSame( array( 'profile.revision_rejected', 'profile.revision_approved' ), array_column( $rows, 'action' ) );
		$this->assertSame( array( (string) $reviewer, (string) $reviewer ), array_column( $rows, 'actor_id' ), 'Each decision records who decided.' );
		$this->assertSame( 'Please cite a source.', $rows[0]['reason'], 'A return records its reason.' );
	}
}
