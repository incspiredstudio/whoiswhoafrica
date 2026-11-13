<?php
/**
 * Information sent to a subject on request (REQ-236). Needs the WordPress test suite and the real support and view tables.
 * Plain TestCase: real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Info\InformationSender;
use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Views\ViewCounter;
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
use WhoIsWho\Core\Review\Duplicates\Matcher;
use WhoIsWho\Tests\Support\MemoryLinkStore;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class InformationSenderTest extends TestCase {

	private const PROBE = 'wiw_info_audit_probe';

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
		$wpdb->query( 'TRUNCATE TABLE ' . ViewCounter::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		$this->queue = new MemoryQueue();
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
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

	private function sender(): InformationSender {
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
		$audit    = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00' );
		return new InformationSender( new SupportItems( new DueDates( array() ), 'test-key' ), new ViewCounter(), $notifier, $audit );
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
		update_post_meta( $id, Matcher::EXTERNAL_IDS_META, wp_json_encode( array( 'rc_number' => 'RC-1' ) ) );
		return $id;
	}

	/**
	 * A support item.
	 *
	 * @param int    $profile Profile id.
	 * @param string $type    Item type.
	 * @return array<string, mixed>
	 */
	private function item( int $profile, string $type = ItemType::DATA_REQUEST ): array {
		return ( new SupportItems( new DueDates( array() ), 'test-key' ) )->create(
			array(
				'type'       => $type,
				'profile_id' => $profile,
				'name'       => 'Requester',
				'email'      => 'requester@example.test',
				'message'    => 'How is my page doing?',
			)
		);
	}

	public function test_REQ_236_info_email_uses_existing_data_only(): void {
		$profile = $this->profile( 'Info Co' );
		$item    = $this->item( $profile );
		$this->item( $profile, ItemType::ENQUIRY );
		$counter = new ViewCounter();
		$counter->record( $profile, 'Mozilla/5.0 Firefox', '2026-11-01' );
		$counter->record( $profile, 'Mozilla/5.0 Firefox', '2026-11-02' );

		$lines = $this->sender()->lines(
			(int) $item['id'],
			array( InformationSender::STATUS, InformationSender::VIEWS, InformationSender::SOURCES, InformationSender::ENQUIRIES )
		);

		$this->assertCount( 4, $lines );
		$this->assertStringContainsString( (string) $item['reference'], $lines[0] );
		$this->assertStringContainsString( '2 times', $lines[1] );
		$this->assertStringContainsString( 'rc_number', $lines[2] );
		$this->assertStringNotContainsString( 'RC-1', $lines[2], 'Source names only, not identifiers.' );
		$this->assertStringContainsString( ': 1.', $lines[3] );
	}

	public function test_REQ_236_information_request_is_logged(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$profile           = $this->profile( 'Logged Co' );
		$item              = $this->item( $profile );
		$this->queue->jobs = array();

		$this->sender()->send( (int) $item['id'], array( InformationSender::VIEWS, InformationSender::STATUS ) );

		global $wpdb;
		$rows = (array) $wpdb->get_results( 'SELECT action, actor_id, new_value FROM ' . $wpdb->prefix . self::PROBE, ARRAY_A ); // phpcs:ignore WordPress.DB
		$this->assertCount( 1, $rows );
		$this->assertSame( 'info.sent', $rows[0]['action'] );
		$this->assertStringContainsString( 'views', (string) $rows[0]['new_value'] );
		$this->assertStringContainsString( 'status', (string) $rows[0]['new_value'] );
		$this->assertSame( (string) get_current_user_id(), (string) $rows[0]['actor_id'] );
		$mail = array_values( array_filter( $this->queue->jobs, static fn ( array $job ): bool => Notifier::JOB === $job['name'] ) );
		$this->assertCount( 1, $mail );
		$this->assertSame( 'information_sent', $mail[0]['args']['event'] );
		$this->assertStringNotContainsString( 'requester@example.test', (string) wp_json_encode( $mail[0]['args'] ) );
	}

	public function test_REQ_236_sending_needs_the_capability_and_a_known_kind(): void {
		$profile = $this->profile( 'Guard Co' );
		$item    = $this->item( $profile );
		try {
			$this->sender()->send( (int) $item['id'], array( InformationSender::STATUS ) );
			$this->fail( 'A visitor must not send information.' );
		} catch ( \DomainException $e ) {
			$this->assertStringContainsString( 'Not allowed', $e->getMessage() );
		}
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$this->expectException( \InvalidArgumentException::class );
		$this->sender()->send( (int) $item['id'], array( 'home_address' ) );
	}
}
