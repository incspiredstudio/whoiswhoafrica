<?php
/**
 * The Corrections screen (REQ-271, REQ-157, REQ-158, REQ-253, REQ-232, REQ-244). Plain TestCase: real profile, support and
 * notes tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Admin\Corrections\CorrectionsScreen;
use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Directory\Edit\ProfileEditor;
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
use WhoIsWho\Core\Platform\Notes\NotesService;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\Support\MemoryLinkStore;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class CorrectionsScreenTest extends TestCase {

	private const PROBE = 'wiw_corrections_audit_probe';

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

	/**
	 * Queue the screen's notifier uses, so a test can see what was queued.
	 *
	 * @var MemoryQueue
	 */
	private MemoryQueue $queue;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$wpdb->query( 'TRUNCATE TABLE ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'TRUNCATE TABLE ' . NotesService::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
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

	private function screen(): CorrectionsScreen {
		$runner   = new JobRunner(
			$this->queue,
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
		return new CorrectionsScreen( new SupportItems( new DueDates( array() ), 'test-key' ), $audit, $notifier, new NotesService( $audit ) );
	}

	/**
	 * A reviewed organisation.
	 *
	 * @param string               $name         Name.
	 * @param array<string, mixed> $state_values Extra state columns.
	 */
	private function profile( string $name, array $state_values = array() ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => $name,
				'post_status' => 'draft',
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array_merge( array( 'entity_type' => 'organisation' ), $state_values ) );
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		return $id;
	}

	/**
	 * Create a correction.
	 *
	 * @param int|null $profile Profile id.
	 * @return array<string, mixed>
	 */
	private function correction( ?int $profile = null, string $message = 'The head office is in Abuja, not Lagos.' ): array {
		$input = array(
			'type'         => ItemType::CORRECTION,
			'name'         => 'Requester Name',
			'relationship' => 'person',
			'email'        => 'requester@example.test',
			'phone'        => '+2340000000',
			'message'      => $message,
		);
		if ( null !== $profile ) {
			$input['profile_id'] = $profile;
		}
		return ( new SupportItems( new DueDates( array() ), 'test-key' ) )->create( $input );
	}

	private function audit_rows( string $action ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . self::PROBE . ' WHERE action = %s', $action ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed name.
	}

	public function test_REQ_271_corrections_list_shows_due_and_status(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$profile = $this->correction( $this->profile( 'Listed Co' ) );
		$this->correction( $this->profile( 'Other Co' ), 'A different fix.' );
		( new SupportItems() )->create( array( 'type' => ItemType::REMOVAL ) );

		$rows = $this->screen()->rows();

		$this->assertCount( 2, $rows, 'only corrections are listed' );
		foreach ( $rows as $row ) {
			$this->assertSame( ItemStatus::NEW, $row['status'] );
			$this->assertNotEmpty( $row['due_at'] );
			$this->assertFalse( $row['breach'] );
			$this->assertNotSame( '', $row['profile_name'] );
			$this->assertNotSame( '', $row['suggestion'] );
		}
		$html = $this->screen()->html( new Request( array() ) );
		foreach ( array( 'Reference', 'Profile', 'Suggestion', 'Received', 'Due', 'Status', (string) $profile['reference'] ) as $word ) {
			$this->assertStringContainsString( $word, $html );
		}
		$only = $this->screen()->rows( ItemStatus::RESOLVED );
		$this->assertSame( array(), $only, 'the status filter narrows the list' );
	}

	public function test_REQ_271_an_overdue_correction_is_flagged(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$late = ( new SupportItems( new DueDates( array() ), 'test-key' ) )->create(
			array(
				'type'    => ItemType::CORRECTION,
				'message' => 'Old request.',
			),
			new \DateTimeImmutable( '2020-01-01', new \DateTimeZone( 'UTC' ) )
		);
		$rows = $this->screen()->rows();
		$this->assertTrue( $rows[0]['breach'] );
		$this->assertStringContainsString( 'Overdue', $this->screen()->html( new Request( array() ) ) );
		unset( $late );
	}

	public function test_REQ_271_detail_shows_everything_the_requester_sent_to_staff(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$item = $this->correction( $this->profile( 'Detail Co' ) );
		$html = $this->screen()->html( new Request( array( 'item' => (string) $item['id'] ) ) );
		foreach ( array( 'Requester Name', 'requester@example.test', '+2340000000', 'person', 'The head office is in Abuja, not Lagos.', 'Detail Co' ) as $text ) {
			$this->assertStringContainsString( $text, $html );
		}
	}

	public function test_REQ_158_apply_opens_the_profile_edit_screen_with_the_reference_as_the_reason(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$profile = $this->profile( 'Apply Co' );
		$item    = $this->correction( $profile );
		$screen  = $this->screen();
		$url     = $screen->apply_url( $item );
		$this->assertStringContainsString( 'page=wiw-profile-edit', $url );
		$this->assertStringContainsString( 'profile_id=' . $profile, $url );
		$this->assertStringContainsString( 'resolves=' . $item['id'], $url );
		$this->assertStringContainsString( rawurlencode( (string) $item['reference'] ), $url );
		$this->assertStringContainsString( 'Apply', $screen->html( new Request( array( 'item' => (string) $item['id'] ) ) ) );
	}

	public function test_REQ_232_apply_closes_linked_item(): void {
		$editor  = $this->user( 'wiw_editor' );
		$profile = $this->profile( 'Edited Co' );
		$item    = $this->correction( $profile );
		( new ProfileEditor() )->edit( $profile, array( 'hq_city' => 'Abuja' ), 'Per ' . $item['reference'], $editor, (int) $item['id'] );
		wp_set_current_user( $editor );
		$this->assertSame( ItemStatus::RESOLVED, $this->screen()->item( (int) $item['id'] )['status'] );
	}

	public function test_REQ_253_apply_on_claimed_profile_waits_for_approval(): void {
		// The assigned manager's edit waits for review; the screen says so, and a staff edit applies at once (owner decision).
		$manager = $this->user( 'wiw_editor' );
		$staff   = $this->user( 'wiw_editor' );
		$profile = $this->profile(
			'Claimed Co',
			array(
				'claimed'          => 1,
				'assigned_user_id' => $manager,
			)
		);
		$item    = $this->correction( $profile );
		wp_set_current_user( $staff );
		$html = $this->screen()->html( new Request( array( 'item' => (string) $item['id'] ) ) );
		$this->assertStringContainsString( 'This profile is claimed', $html );
		$this->assertStringContainsString( 'waits for review', $html );
		$this->assertFalse( ( new ProfileEditor() )->edit( $profile, array( 'hq_city' => 'Abuja' ), 'Manager edit', $manager )['applied'] );
		$this->assertTrue( ( new ProfileEditor() )->edit( $profile, array( 'hq_city' => 'Abuja' ), 'Staff edit', $staff )['applied'] );
	}

	public function test_REQ_157_mark_resolved_closes_audits_and_emails_the_requester(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$item = $this->correction( $this->profile( 'Resolved Co' ) );
		$this->screen()->mark_resolved( (int) $item['id'] );
		$this->assertSame( ItemStatus::RESOLVED, ( new SupportItems() )->get( (int) $item['id'] )['status'] );
		$this->assertSame( 1, $this->audit_rows( 'correction.resolved' ) );
		$this->assertCount( 1, $this->queue->due( PHP_INT_MAX, 100 ), 'the requester email is queued' );
		$this->expectException( \InvalidArgumentException::class );
		$this->screen()->mark_resolved( (int) $item['id'] );
	}

	public function test_REQ_157_decline_requires_private_reason(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$item = $this->correction( $this->profile( 'Declined Co' ) );
		try {
			$this->screen()->decline( (int) $item['id'], '   ' );
			$this->fail( 'A decline without a reason must be refused.' );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( ItemStatus::NEW, ( new SupportItems() )->get( (int) $item['id'] )['status'] );
		}
		$this->screen()->decline( (int) $item['id'], 'The cited source is a forum post, not a record.' );
		$this->assertSame( ItemStatus::DECLINED, ( new SupportItems() )->get( (int) $item['id'] )['status'] );
		$this->assertSame( 1, $this->audit_rows( 'correction.declined' ) );
		$notes = ( new NotesService( $this->audit() ) )->list( 'enquiry', (int) $item['id'], 'wiw_handle_disputes' );
		$this->assertStringContainsString( 'forum post', $notes[0]['body'], 'the reason is kept as a private staff note' );
		$job = wp_json_encode( $this->queue->due( PHP_INT_MAX, 100 ) );
		$this->assertStringNotContainsString( 'forum post', (string) $job, 'the reason is never sent to the requester' );
	}

	public function test_REQ_244_corrections_screen_denies_without_capability(): void {
		$item = $this->correction( $this->profile( 'Guarded Co' ) );
		wp_set_current_user( $this->user( 'wiw_reviewer' ) );
		try {
			$this->screen()->rows();
			$this->fail( 'A reviewer has no wiw_support_inbox.' );
		} catch ( \DomainException $e ) {
			$this->assertSame( 'Not allowed to use the corrections screen.', $e->getMessage() );
		}
		$this->expectException( \DomainException::class );
		$this->screen()->decline( (int) $item['id'], 'No.' );
	}
}
