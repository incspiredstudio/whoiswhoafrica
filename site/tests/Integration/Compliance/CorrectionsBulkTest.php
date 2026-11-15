<?php
/**
 * Bulk actions and row actions on the Corrections list (REQ-275, REQ-271). Bulk Mark resolved and Decline run the single action
 * for each selected correction, which checks wiw_handle_disputes and writes one audit row per item; Decline needs the shared
 * reason, kept as a private note; a user without the capability is refused and sees no deciding row actions. Plain TestCase: real
 * support and notes tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Admin\Corrections\CorrectionsScreen;
use WhoIsWho\Core\Compliance\Admin\Corrections\CorrectionsTable;
use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
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
use WhoIsWho\Tests\Support\MemoryLinkStore;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class CorrectionsBulkTest extends TestCase {

	private const PROBE = 'wiw_corrections_bulk_probe';

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
	}

	protected function tearDown(): void {
		global $wpdb;
		wp_set_current_user( 0 );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		parent::tearDown();
	}

	private function login( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		wp_set_current_user( $id );
		return $id;
	}

	private function table(): CorrectionsTable {
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
		$audit    = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00' );
		return new CorrectionsTable( '', new CorrectionsScreen( new SupportItems( new DueDates( array() ), 'test-key' ), $audit, $notifier, new NotesService( $audit ) ) );
	}

	private function correction(): int {
		return (int) ( new SupportItems( new DueDates( array() ), 'test-key' ) )->create(
			array(
				'type'         => ItemType::CORRECTION,
				'name'         => 'Requester Name',
				'relationship' => 'person',
				'email'        => 'requester@example.test',
				'message'      => 'The head office is in Abuja.',
			)
		)['id'];
	}

	private function audit_count( int $id, string $action ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . self::PROBE . ' WHERE action = %s AND subject_id = %s', $action, (string) $id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed name.
	}

	/**
	 * A bulk request with a valid nonce.
	 *
	 * @param array<string, mixed> $values Values.
	 */
	private function request( array $values ): Request {
		return new Request( array_merge( array( '_wpnonce' => ActionGuard::nonce_for( CorrectionsTable::BULK ) ), $values ) );
	}

	public function test_REQ_275_corrections_bulk_resolve_audits_each_item(): void {
		$this->login( 'wiw_editor' );
		$a      = $this->correction();
		$b      = $this->correction();
		$table  = $this->table();
		$result = $table->process_bulk(
			$this->request(
				array(
					ListTable::FIELD_ACTION => 'resolve',
					ListTable::FIELD_IDS    => array( $a, $b ),
				)
			)
		);
		$this->assertSame( '2 marked resolved.', $table->notice_for( $result )['text'] );
		foreach ( array( $a, $b ) as $id ) {
			$this->assertSame( ItemStatus::RESOLVED, ( new SupportItems() )->get( $id )['status'] );
			$this->assertSame( 1, $this->audit_count( $id, 'correction.resolved' ) );
		}
	}

	public function test_REQ_275_corrections_bulk_decline_needs_the_shared_reason_and_audits_each_item(): void {
		$this->login( 'wiw_editor' );
		$a      = $this->correction();
		$b      = $this->correction();
		$table  = $this->table();
		$values = array(
			ListTable::FIELD_ACTION  => 'decline',
			ListTable::FIELD_IDS     => array( $a, $b ),
			ListTable::FIELD_CONFIRM => '1',
		);
		$this->assertSame( 2, $table->process_bulk( $this->request( $values ) )['skipped'], 'no reason, nothing declined' );
		$result = $table->process_bulk( $this->request( array_merge( $values, array( 'reason' => 'Already correct per the registry' ) ) ) );
		$this->assertSame( 2, $result['done'] );
		foreach ( array( $a, $b ) as $id ) {
			$this->assertSame( ItemStatus::DECLINED, ( new SupportItems() )->get( $id )['status'] );
			$this->assertSame( 1, $this->audit_count( $id, 'correction.declined' ) );
			$notes = ( new NotesService( AuditLogger::for_site() ) )->list( 'enquiry', $id, CorrectionsScreen::CAP_DISPUTES );
			$this->assertStringContainsString( 'Already correct per the registry', $notes[0]['body'] );
		}
	}

	public function test_REQ_275_corrections_bulk_is_refused_without_the_capability(): void {
		$this->login( 'wiw_editor' );
		$a    = $this->correction();
		$user = wp_get_current_user();
		$user->add_cap( CorrectionsScreen::CAP_DISPUTES, false );
		$result = $this->table()->process_bulk(
			$this->request(
				array(
					ListTable::FIELD_ACTION => 'resolve',
					ListTable::FIELD_IDS    => array( $a ),
				)
			)
		);
		$this->assertSame( ListTable::REFUSED_CAP, $result['status'] );
		$this->assertSame( ItemStatus::NEW, ( new SupportItems() )->get( $a )['status'] );
	}

	public function test_REQ_275_corrections_row_actions_follow_the_capability(): void {
		$this->login( 'wiw_editor' );
		$a    = $this->correction();
		$html = $this->table()->html( new Request( array() ) );
		$this->assertStringContainsString( 'name="ids[]" value="' . $a . '"', $html );
		$this->assertStringContainsString( 'wiw_confirm=decline', $html );
		$this->assertStringContainsString( '>Open<', $html );

		$user = wp_get_current_user();
		$user->add_cap( CorrectionsScreen::CAP_DISPUTES, false );
		$item    = array_merge( (array) ( new SupportItems() )->get( $a ), array( 'profile_id' => null ) );
		$actions = $this->table()->visible_row_actions( $item );
		$this->assertSame( array( 'open' ), array_keys( $actions ), 'viewing only: Open, no deciding actions' );
		$this->assertSame( array(), $this->table()->allowed_bulk_actions() );
		$this->assertStringNotContainsString( 'name="ids[]"', $this->table()->html( new Request( array() ) ), 'no checkboxes without a bulk action' );
	}
}
