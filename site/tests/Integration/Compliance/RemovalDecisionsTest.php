<?php
/**
 * Taking a profile down on a valid removal and suppressing it (REQ-209, REQ-147, REQ-207, REQ-158). Needs the WordPress test
 * suite and the real profile, support and suppression tables. Plain TestCase: real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Removals\RemovalDecisions;
use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Purge\PurgeQueue;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Platform\Log\LogSink;
use WhoIsWho\Core\Platform\Log\Logger;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Core\Review\Duplicates\Matcher;
use WhoIsWho\Core\Review\StatePolicy;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\Support\MemoryLinkStore;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class RemovalDecisionsTest extends TestCase {

	private const PROBE = 'wiw_removal_audit_probe';

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

	private MemoryQueue $queue;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$wpdb->query( 'TRUNCATE TABLE ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'TRUNCATE TABLE ' . Suppression::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		$this->queue = new MemoryQueue();
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
		remove_all_actions( 'wiw_profile_state_changed' );
		remove_all_actions( 'wiw_removal_upheld' );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	private function service(): RemovalDecisions {
		$runner = new JobRunner(
			$this->queue,
			null,
			array(
				'get' => static fn ( string $key, $fallback ) => $fallback,
				'set' => static function (): void {},
			)
		);
		$sink   = new class() implements LogSink {
			/** @param string $line Line. */
			public function write( string $line ): void {
				unset( $line );
			}
		};
		( new PurgeQueue( $runner, new Logger( $sink, new PiiCatalogue() ) ) )->register();
		$log      = new class() implements EmailLogStore {
			/**
			 * Ignore.
			 *
			 * @param string $message_id    Id.
			 * @param string $event         Event.
			 * @param string $recipient_ref Ref.
			 * @param string $type          Type.
			 * @param string $result        Result.
			 */
			public function add( string $message_id, string $event, string $recipient_ref, string $type, string $result ): void {
				unset( $message_id, $event, $recipient_ref, $type, $result );
			}
		};
		$notifier = new Notifier( $runner, $log, static fn (): array => array(), static fn (): bool => true, 'hash-key', new SignedLinks( new MemoryLinkStore(), static fn (): int => 1800000000 ) );
		$audit    = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00' );
		return new RemovalDecisions( new SupportItems( new DueDates( array() ), 'test-key' ), new StateMachine( null, $audit ), new Suppression( $audit ), $notifier, $audit );
	}

	/**
	 * A reviewed, public profile.
	 *
	 * @param string $name Name.
	 * @return int Id.
	 */
	private function profile( string $name ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => $name,
				'post_status' => 'draft',
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		update_post_meta( $id, Matcher::EXTERNAL_IDS_META, wp_json_encode( array( 'rc_number' => 'RC-777' ) ) );
		return $id;
	}

	/**
	 * A removal item for a profile.
	 *
	 * @param int    $profile Profile id.
	 * @param string $type    Item type.
	 * @return array<string, mixed>
	 */
	private function item( int $profile, string $type = ItemType::REMOVAL ): array {
		return ( new SupportItems( new DueDates( array() ), 'test-key' ) )->create(
			array(
				'type'       => $type,
				'profile_id' => $profile,
				'name'       => 'Requester',
				'email'      => 'requester@example.test',
				'message'    => 'Please remove it.',
			)
		);
	}

	public function test_REQ_209_valid_removal_returns_410_in_same_request(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$profile = $this->profile( 'Removal Co' );
		$item    = $this->item( $profile );

		$this->service()->uphold( (int) $item['id'] );

		$state  = ( new StateRepository() )->pub_state( $profile );
		$policy = StatePolicy::for_state( (string) $state );
		$this->assertSame( PubState::REMOVED_SUPPRESSED, $state );
		$this->assertSame( 410, $policy->status );
		$this->assertTrue( $policy->noindex );
		$this->assertSame( ProfileType::STATUS_HIDDEN, get_post_status( $profile ) );
		$this->assertSame( ItemStatus::RESOLVED, ( new SupportItems( null, 'test-key' ) )->get( (int) $item['id'] )['status'] );
	}

	public function test_REQ_209_valid_removal_queues_a_cache_purge(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$profile           = $this->profile( 'Purge Co' );
		$item              = $this->item( $profile );
		$this->queue->jobs = array();

		$this->service()->uphold( (int) $item['id'] );

		$purges = array_filter( $this->queue->jobs, static fn ( array $job ): bool => PurgeQueue::JOB === $job['name'] );
		$this->assertNotEmpty( $purges );
	}

	public function test_REQ_147_valid_removal_adds_suppression_entry(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$profile = $this->profile( 'Suppressed Co' );
		$item    = $this->item( $profile );

		$this->service()->uphold( (int) $item['id'] );

		$match = ( new Suppression( new AuditLogger( new WpdbConnection( self::PROBE ) ) ) )->match( 'Suppressed Co', 'organisation', array() );
		$this->assertNotNull( $match );
		$by_id = ( new Suppression( new AuditLogger( new WpdbConnection( self::PROBE ) ) ) )->match( 'Another Name', 'organisation', array( 'rc_number' => 'RC-777' ) );
		$this->assertNotNull( $by_id );
		$this->assertSame( 'external_id', $by_id['by'] );
	}

	public function test_REQ_207_reimport_after_suppression_does_not_recreate(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$profile = $this->profile( 'Reimport Co' );
		$item    = $this->item( $profile );

		$this->service()->uphold( (int) $item['id'] );

		$refusal = ( new Suppression( new AuditLogger( new WpdbConnection( self::PROBE ) ) ) )->refusal_for_import(
			array(
				'name'         => 'Reimport Co',
				'entity_type'  => 'organisation',
				'external_ids' => array( 'rc_number' => 'RC-777' ),
			)
		);
		$this->assertSame( Suppression::REFUSAL_CODE, $refusal );
	}

	public function test_REQ_158_removal_requester_is_told_without_storing_the_address_in_the_job(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$profile           = $this->profile( 'Mail Co' );
		$item              = $this->item( $profile );
		$this->queue->jobs = array();

		$this->service()->uphold( (int) $item['id'] );

		$mail = array_values( array_filter( $this->queue->jobs, static fn ( array $job ): bool => Notifier::JOB === $job['name'] ) );
		$this->assertCount( 1, $mail );
		$this->assertSame( 'request_resolved', $mail[0]['args']['event'] );
		$this->assertStringNotContainsString( 'requester@example.test', (string) wp_json_encode( $mail[0]['args'] ) );
	}

	public function test_REQ_158_upheld_removal_is_audited_and_announced(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$profile = $this->profile( 'Announce Co' );
		$item    = $this->item( $profile );
		$seen    = array();
		add_action(
			'wiw_removal_upheld',
			static function ( int $item_id, int $profile_id, string $name, string $entity_type, array $external_ids ) use ( &$seen ): void {
				$seen = array( $item_id, $profile_id, $name, $entity_type, $external_ids );
			},
			10,
			5
		);

		$this->service()->uphold( (int) $item['id'] );

		$this->assertSame( array( (int) $item['id'], $profile, 'Announce Co', 'organisation', array( 'rc_number' => 'RC-777' ) ), $seen );
		global $wpdb;
		$actions = (array) $wpdb->get_col( 'SELECT action FROM ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		$this->assertContains( 'removal.upheld', $actions );
		$this->assertContains( 'suppression.add', $actions );
		$this->assertContains( 'profile.state_change', $actions );
	}

	public function test_REQ_158_not_valid_removal_declines_with_reason_and_leaves_the_profile(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$profile = $this->profile( 'Stays Co' );
		$item    = $this->item( $profile );

		$this->service()->decline( (int) $item['id'], 'The details are public record.' );

		$this->assertSame( PubState::PUBLISHED_REVIEWED, ( new StateRepository() )->pub_state( $profile ) );
		$this->assertSame( ItemStatus::DECLINED, ( new SupportItems( null, 'test-key' ) )->get( (int) $item['id'] )['status'] );
		$this->assertNull( ( new Suppression( new AuditLogger( new WpdbConnection( self::PROBE ) ) ) )->match( 'Stays Co', 'organisation', array() ) );
	}

	public function test_REQ_158_not_valid_needs_a_reason(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$item = $this->item( $this->profile( 'Reasonless Co' ) );
		$this->expectException( \InvalidArgumentException::class );
		$this->service()->decline( (int) $item['id'], '  ' );
	}

	public function test_REQ_158_deciding_needs_the_disputes_capability(): void {
		$item = $this->item( $this->profile( 'Denied Co' ) );
		wp_set_current_user( $this->user( 'wiw_reviewer' ) );
		$this->expectException( \DomainException::class );
		$this->service()->uphold( (int) $item['id'] );
	}

	public function test_REQ_158_only_removal_items_can_be_upheld_and_a_closed_item_cannot_be_decided_again(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$profile = $this->profile( 'Twice Co' );
		$report  = $this->item( $profile, ItemType::REPORT );
		try {
			$this->service()->uphold( (int) $report['id'] );
			$this->fail( 'A report is not a removal.' );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( PubState::PUBLISHED_REVIEWED, ( new StateRepository() )->pub_state( $profile ) );
		}
		$item = $this->item( $profile );
		$this->service()->decline( (int) $item['id'], 'Not valid.' );
		$this->expectException( \InvalidArgumentException::class );
		$this->service()->uphold( (int) $item['id'] );
	}
}
