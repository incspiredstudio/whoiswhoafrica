<?php
/**
 * The Requests and reports screen (REQ-270, REQ-059, REQ-090, REQ-158, REQ-244). Plain TestCase: real profile, support and
 * suppression tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Admin\Requests\RequestsScreen;
use WhoIsWho\Core\Compliance\Removals\RemovalDecisions;
use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\Support\MemoryLinkStore;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class RequestsScreenTest extends TestCase {

	private const PROBE = 'wiw_requests_audit_probe';

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
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$wpdb->query( 'TRUNCATE TABLE ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'TRUNCATE TABLE ' . Suppression::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
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

	private function audit(): AuditLogger {
		return new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00' );
	}

	private function screen(): RequestsScreen {
		$runner   = new JobRunner(
			new MemoryQueue(),
			null,
			array(
				'get' => static fn ( string $key, $fallback ) => $fallback,
				'set' => static function (): void {},
			)
		);
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
		$audit    = $this->audit();
		$items    = new SupportItems( new DueDates( array() ), 'test-key' );
		$machine  = new StateMachine( null, $audit );
		return new RequestsScreen( $items, new RemovalDecisions( $items, $machine, new Suppression( $audit ), $notifier, $audit ), $machine, $audit );
	}

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
		return $id;
	}

	/**
	 * Create an item.
	 *
	 * @param string   $type    Item type.
	 * @param int|null $profile Profile id.
	 * @return array<string, mixed>
	 */
	private function item( string $type, ?int $profile = null ): array {
		$input = array(
			'type'    => $type,
			'name'    => 'Requester',
			'email'   => 'requester@example.test',
			'message' => 'Details.',
		);
		if ( null !== $profile ) {
			$input['profile_id'] = $profile;
		}
		return ( new SupportItems( new DueDates( array() ), 'test-key' ) )->create( $input );
	}

	public function test_REQ_270_list_shows_kind_status_and_due_date(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$profile = $this->profile( 'Listed Co' );
		$this->item( ItemType::REMOVAL, $profile );
		$this->item( ItemType::REPORT, $profile );

		$rows = $this->screen()->rows();

		$this->assertCount( 2, $rows );
		$this->assertEqualsCanonicalizing( array( 'Removal', 'Report' ), array_column( $rows, 'kind' ) );
		foreach ( $rows as $row ) {
			$this->assertSame( ItemStatus::NEW, $row['status'] );
			$this->assertNotEmpty( $row['due_at'] );
			$this->assertFalse( $row['breach'] );
		}
		$html = $this->screen()->html( new Request( array() ) );
		$this->assertStringContainsString( 'Removal', $html );
		$this->assertStringContainsString( 'Due', $html );
		$only = $this->screen()->rows( 'report', ItemStatus::NEW );
		$this->assertCount( 1, $only );
		$this->assertSame( 'Report', $only[0]['kind'] );
	}

	public function test_REQ_270_list_leaves_out_enquiries_and_corrections(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$this->item( ItemType::ENQUIRY );
		$this->item( ItemType::CORRECTION, $this->profile( 'Fix Co' ) );

		$this->assertSame( array(), $this->screen()->rows() );
	}

	public function test_REQ_270_requests_service_refuses_without_capability(): void {
		wp_set_current_user( $this->user( 'subscriber' ) );

		$this->expectException( \DomainException::class );
		$this->screen()->rows();
	}

	public function test_REQ_059_data_request_listed_with_removals_and_reports(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$this->item( ItemType::DATA_REQUEST );
		$this->item( ItemType::REMOVAL, $this->profile( 'Gone Co' ) );

		$kinds = array_column( $this->screen()->rows(), 'kind' );

		$this->assertContains( 'Data request', $kinds );
		$this->assertContains( 'Removal', $kinds );
	}

	public function test_REQ_059_data_request_can_be_closed_when_fulfilled(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$item = $this->item( ItemType::DATA_REQUEST );

		$this->screen()->close_data_request( (int) $item['id'] );

		$this->assertSame( ItemStatus::RESOLVED, ( new SupportItems( null, 'test-key' ) )->get( (int) $item['id'] )['status'] );
	}

	public function test_REQ_090_reviewer_can_hide_profile_from_report(): void {
		wp_set_current_user( $this->user( 'wiw_reviewer' ) );
		$profile = $this->profile( 'Reported Co' );
		$item    = $this->item( ItemType::REPORT, $profile );

		$this->screen()->hide_reported_profile( (int) $item['id'] );

		$this->assertSame( PubState::UNPUBLISHED, ( new StateRepository() )->pub_state( $profile ) );
		$this->assertSame( ItemStatus::IN_PROGRESS, ( new SupportItems( null, 'test-key' ) )->get( (int) $item['id'] )['status'] );
	}

	public function test_REQ_090_hiding_needs_an_unpublish_capability(): void {
		wp_set_current_user( $this->user( 'subscriber' ) );
		$profile = $this->profile( 'Safe Co' );
		$item    = $this->item( ItemType::REPORT, $profile );

		try {
			$this->screen()->hide_reported_profile( (int) $item['id'] );
			$this->fail( 'Expected a refusal.' );
		} catch ( \DomainException $e ) {
			$this->assertSame( PubState::PUBLISHED_REVIEWED, ( new StateRepository() )->pub_state( $profile ) );
		}
	}

	public function test_REQ_090_dismiss_and_resolve_a_report(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$profile = $this->profile( 'Both Co' );
		$one     = $this->item( ItemType::REPORT, $profile );
		$two     = $this->item( ItemType::REPORT, $profile );

		$this->screen()->dismiss_report( (int) $one['id'] );
		$this->screen()->resolve_report( (int) $two['id'] );

		$items = new SupportItems( null, 'test-key' );
		$this->assertSame( ItemStatus::DECLINED, $items->get( (int) $one['id'] )['status'] );
		$this->assertSame( ItemStatus::RESOLVED, $items->get( (int) $two['id'] )['status'] );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, ( new StateRepository() )->pub_state( $profile ) );
	}

	public function test_REQ_158_removal_decisions_go_through_the_removal_service(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$profile = $this->profile( 'Taken Co' );
		$item    = $this->item( ItemType::REMOVAL, $profile );

		$this->screen()->uphold_removal( (int) $item['id'] );

		$this->assertSame( PubState::REMOVED_SUPPRESSED, ( new StateRepository() )->pub_state( $profile ) );
	}

	public function test_REQ_158_not_valid_removal_needs_a_reason(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$item = $this->item( ItemType::REMOVAL, $this->profile( 'Kept Co' ) );

		$this->expectException( \InvalidArgumentException::class );
		$this->screen()->decline_removal( (int) $item['id'], '' );
	}

	public function test_REQ_244_escalation_assigns_an_administrator_and_is_audited(): void {
		$editor = $this->user( 'wiw_editor' );
		$this->user( 'administrator' );
		wp_set_current_user( $editor );
		$item = $this->item( ItemType::REMOVAL, $this->profile( 'Dispute Co' ) );

		$this->screen()->escalate( (int) $item['id'] );

		$row = ( new SupportItems( null, 'test-key' ) )->get( (int) $item['id'] );
		$this->assertSame( ItemStatus::IN_PROGRESS, $row['status'] );
		$this->assertGreaterThan( 0, (int) $row['owner_id'] );
		$this->assertTrue( user_can( (int) $row['owner_id'], 'manage_options' ) );
		global $wpdb;
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . self::PROBE . ' WHERE action = %s', 'request.escalated' ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed name.
		$this->assertSame( 1, $count );
	}

	public function test_REQ_270_detail_shows_actions_for_the_kind(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$profile = $this->profile( 'Detail Co' );
		$report  = $this->item( ItemType::REPORT, $profile );

		$html = $this->screen()->html( new Request( array( 'item' => (string) $report['id'] ) ) );

		$this->assertStringContainsString( 'Hide the profile now', $html );
		$this->assertStringContainsString( 'Dismiss', $html );
		$this->assertStringNotContainsString( 'Not valid', $html );
	}

	public function test_REQ_236_detail_offers_to_send_information_when_there_is_an_address(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$profile = $this->profile( 'Info Detail Co' );
		$item    = $this->item( ItemType::DATA_REQUEST, $profile );

		$html = $this->screen()->html( new Request( array( 'item' => (string) $item['id'] ) ) );

		$this->assertStringContainsString( 'Send information to the requester', $html );
		$this->assertStringContainsString( 'name="kind_views"', $html );
		$this->assertStringContainsString( 'value="send_info"', $html );
	}
}
