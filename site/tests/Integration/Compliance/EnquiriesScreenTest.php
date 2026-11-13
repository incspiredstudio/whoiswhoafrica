<?php
/**
 * The Enquiries screen (REQ-089, REQ-177, REQ-187, REQ-244). Needs the WordPress test suite and the real support and notes tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Admin\EnquiriesScreen;
use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Notes\NotesService;
use WhoIsWho\Tests\TestCase;

/**
 * Plain TestCase on purpose (real tables); tables are emptied in setUp.
 */
final class EnquiriesScreenTest extends TestCase {

	private const PROBE = 'wiw_enquiries_audit_probe';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$wpdb->query( 'TRUNCATE TABLE ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'TRUNCATE TABLE ' . NotesService::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		delete_option( 'wiw_enquiry_sequence' );
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

	private function screen(): EnquiriesScreen {
		$audit = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00' );
		return new EnquiriesScreen( new SupportItems( new DueDates( array() ), 'test-key' ), $audit, new NotesService( $audit ) );
	}

	/**
	 * Create an enquiry.
	 *
	 * @param string $source  Source.
	 * @param string $name    Name.
	 * @param string $created Receipt time.
	 * @return array<string, mixed>
	 */
	private function enquiry( string $source, string $name, string $created = 'now' ): array {
		return ( new SupportItems( new DueDates( array() ), 'test-key' ) )->create(
			array(
				'type'    => ItemType::ENQUIRY,
				'name'    => $name,
				'email'   => strtolower( str_replace( ' ', '.', $name ) ) . '@example.test',
				'message' => 'Hello, I would like to talk.',
				'source'  => $source,
			),
			new \DateTimeImmutable( $created, new \DateTimeZone( 'UTC' ) )
		);
	}

	/**
	 * Audit rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function audit_rows(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public function test_REQ_089_enquiries_screen_lists_items_with_age(): void {
		$this->login( 'wiw_editor' );
		$this->enquiry( 'get-listed', 'Ada Obi', '-3 days' );
		$this->enquiry( 'contact', 'Ben Eze' );
		$screen = $this->screen();
		$rows   = $screen->rows();
		$this->assertSame( array( 'Ben Eze', 'Ada Obi' ), array_column( $rows, 'requester_name' ), 'newest first' );
		$this->assertSame( 3, EnquiriesScreen::age_days( $rows[1], time() ) );
		$this->assertSame( 0, EnquiriesScreen::age_days( $rows[0], time() ) );
		$this->assertSame( array( 'Ada Obi' ), array_column( $screen->rows( '', 'get-listed' ), 'requester_name' ), 'source filter' );
		$this->assertSame( array(), $screen->rows( ItemStatus::RESOLVED ), 'status filter' );
		$html = $screen->html( new Request( array() ) );
		$this->assertStringContainsString( 'ENQ-', $html );
		$this->assertStringContainsString( '3 days', $html );
		$this->assertStringNotContainsString( 'Due', $html, 'an enquiry has no due date' );
	}

	public function test_REQ_177_enquiry_owner_and_status_can_be_set(): void {
		$owner = $this->login( 'wiw_editor' );
		$item  = $this->enquiry( 'contact', 'Ada Obi' );
		$this->screen()->update( (int) $item['id'], ItemStatus::IN_PROGRESS, $owner );
		$stored = ( new SupportItems() )->get( (int) $item['id'] );
		$this->assertNotNull( $stored );
		$this->assertSame( ItemStatus::IN_PROGRESS, $stored['status'] );
		$this->assertSame( $owner, (int) $stored['owner_id'] );
		$this->assertNotNull( $stored['acknowledged_at'], 'working an item acknowledges it' );
		$rows = $this->audit_rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'enquiry.update', $rows[0]['action'] );
		$this->assertStringNotContainsString( 'example.test', (string) wp_json_encode( $rows ), 'no personal data in the audit row' );
		$this->screen()->update( (int) $item['id'], ItemStatus::IN_PROGRESS, $owner );
		$this->assertCount( 1, $this->audit_rows(), 'no change, no audit row' );

		$this->expectException( \InvalidArgumentException::class );
		$reviewer = $this->login( 'wiw_reviewer' );
		wp_set_current_user( $owner );
		$this->screen()->update( (int) $item['id'], ItemStatus::NEW, $reviewer );
	}

	public function test_REQ_089_enquiries_screen_requires_wiw_support_inbox(): void {
		$item = $this->enquiry( 'contact', 'Ada Obi' );
		$this->login( 'wiw_reviewer' );
		foreach ( array(
			'rows'   => fn () => $this->screen()->rows(),
			'item'   => fn () => $this->screen()->item( (int) $item['id'] ),
			'update' => fn () => $this->screen()->update( (int) $item['id'], ItemStatus::RESOLVED, 0 ),
			'note'   => fn () => $this->screen()->add_note( (int) $item['id'], 'Sneaky.' ),
		) as $what => $call ) {
			try {
				$call();
				$this->fail( $what . ' must be refused without wiw_support_inbox' );
			} catch ( \DomainException $e ) {
				$this->assertStringContainsString( 'Not allowed', $e->getMessage(), $what );
			}
		}
		$this->assertSame( ItemStatus::NEW, ( new SupportItems() )->get( (int) $item['id'] )['status'] );
	}

	public function test_REQ_187_enquiry_notes_not_public(): void {
		$this->login( 'wiw_editor' );
		$item   = $this->enquiry( 'contact', 'Ada Obi' );
		$screen = $this->screen();
		$screen->add_note( (int) $item['id'], 'Spoke to her by phone, wants a sponsored story.' );
		$html = $screen->html( new Request( array( 'item' => (string) $item['id'] ) ) );
		$this->assertStringContainsString( 'wants a sponsored story', $html );
		$this->assertStringContainsString( 'never public', $html );
		$this->assertSame( 1, preg_match_all( '/Spoke to her/', (string) wp_json_encode( ( new NotesService( new AuditLogger( new WpdbConnection( self::PROBE ) ) ) )->list( 'enquiry', (int) $item['id'], 'wiw_support_inbox' ) ) ) );
		wp_set_current_user( 0 );
		$this->assertStringNotContainsString(
			'wants a sponsored story',
			(string) wp_json_encode(
				( new \WP_Query(
					array(
						's'           => 'sponsored story',
						'post_type'   => 'any',
						'post_status' => 'publish',
					)
				) )->posts
			),
			'notes are not posts and appear in no public query'
		);
		$rows = $this->audit_rows();
		$this->assertStringNotContainsString( 'sponsored story', (string) wp_json_encode( $rows ), 'the audit row holds no note text' );
	}
}
