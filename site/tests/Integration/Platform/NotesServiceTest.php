<?php
/**
 * Internal notes (REQ-187, REQ-188). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Content\PublicStory;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Notes\NotesService;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Tests\TestCase;

/**
 * Plain TestCase on purpose (real tables, see ProfileTablesTest); the table is emptied in setUp.
 */
final class NotesServiceTest extends TestCase {

	private const PROBE = 'wiw_notes_audit_probe';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
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

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		return $id;
	}

	private function service( int $actor ): NotesService {
		wp_set_current_user( 0 );
		wp_set_current_user( $actor );
		return new NotesService( new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-11 10:00:00', static fn (): int => $actor ) );
	}

	/**
	 * Audit rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function rows(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public function test_REQ_187_notes_attach_to_a_record_type_and_id(): void {
		$editor  = $this->user( 'wiw_editor' );
		$service = $this->service( $editor );
		$service->add( 'profile', 12, 'Called the office; waiting for a reply.', 'wiw_support_inbox' );
		$service->add( 'profile', 12, 'Reply received.', 'wiw_support_inbox' );
		$service->add( 'customer', 12, 'Other record, same id.', 'wiw_support_inbox' );

		$notes = $service->list( 'profile', 12, 'wiw_support_inbox' );
		$this->assertCount( 2, $notes );
		$this->assertSame( 'Called the office; waiting for a reply.', $notes[0]['body'] );
		$this->assertSame( $editor, $notes[0]['author_id'] );
		$this->assertCount( 1, $service->list( 'customer', 12, 'wiw_support_inbox' ) );
		$this->assertCount( 0, $service->list( 'nomination', 12, 'wiw_support_inbox' ) );
	}

	public function test_REQ_188_adding_a_note_is_audited(): void {
		$service = $this->service( $this->user( 'wiw_editor' ) );
		$note    = $service->add( 'profile', 7, 'Private detail about a person.', 'wiw_support_inbox' );
		$rows    = $this->rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'note.add', $rows[0]['action'] );
		$this->assertSame( 'profile', $rows[0]['subject_type'] );
		$this->assertSame( '7', $rows[0]['subject_id'] );
		$this->assertStringContainsString( '"note":' . $note, (string) $rows[0]['new_value'] );
		$this->assertStringNotContainsString( 'Private detail', wp_json_encode( $rows ), 'the body stays out of the audit log' );
	}

	public function test_REQ_188_the_calling_modules_capability_is_required(): void {
		$reviewer = $this->service( $this->user( 'wiw_reviewer' ) );
		try {
			$reviewer->add( 'profile', 1, 'x', 'wiw_manage_register' );
			$this->fail( 'a user without the capability must be refused' );
		} catch ( \DomainException $e ) {
			$this->assertSame( array(), $this->rows() );
		}
		foreach ( array( '', 'edit_posts', 'manage_options' ) as $not_a_wiw_capability ) {
			try {
				$this->service( $this->user( 'administrator' ) )->add( 'profile', 1, 'x', $not_a_wiw_capability );
				$this->fail( 'only named wiw_ capabilities count' );
			} catch ( \DomainException $e ) {
				$this->assertTrue( true );
			}
		}
		$this->expectException( \DomainException::class );
		$this->service( $this->user( 'wiw_reviewer' ) )->list( 'profile', 1, 'wiw_support_inbox' );
	}

	public function test_REQ_187_bad_input_is_refused(): void {
		$service = $this->service( $this->user( 'wiw_editor' ) );
		foreach ( array( array( 'bogus', 1, 'x' ), array( 'profile', 0, 'x' ), array( 'profile', 1, '   ' ), array( 'profile', 1, str_repeat( 'a', 5001 ) ) ) as $case ) {
			try {
				$service->add( $case[0], $case[1], $case[2], 'wiw_support_inbox' );
				$this->fail( 'bad input must be refused' );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertTrue( true );
			}
		}
	}

	public function test_REQ_187_note_is_never_returned_by_public_read_models(): void {
		$story = wp_insert_post(
			array(
				'post_title'   => 'A story',
				'post_content' => 'Public text.',
				'post_status'  => 'publish',
			)
		);
		$this->service( $this->user( 'wiw_editor' ) )->add( 'profile', (int) $story, 'SECRET-NOTE-TEXT', 'wiw_support_inbox' );
		wp_set_current_user( 0 );
		$found = PublicStory::find( (int) $story );
		$this->assertNotNull( $found );
		$this->assertStringNotContainsString( 'SECRET-NOTE-TEXT', (string) wp_json_encode( $found ) );
		$this->assertStringNotContainsString( 'SECRET-NOTE-TEXT', (string) wp_json_encode( PublicStory::list( array() ) ) );
	}

	public function test_REQ_187_the_notes_body_is_in_the_pii_catalogue(): void {
		$this->assertTrue( ( new PiiCatalogue() )->has( 'wiw_notes.body' ) );
	}
}
